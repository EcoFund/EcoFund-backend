<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class CampaignController extends Controller
{
    public function stats()
    {
        Campaign::deactivateExpired();

        $totalRaised = \App\Models\Donation::where('status', 'berhasil')->sum('jumlah');
        $totalDonors = \App\Models\Donation::where('status', 'berhasil')
            ->whereNotNull('nama_donatur')
            ->distinct('nama_donatur')
            ->count('nama_donatur');
        $totalCampaigns = Campaign::whereIn('status', ['aktif', 'selesai'])->count();

        // Calculate success rate: completed campaigns divided by total (avoid division by zero)
        $fullyFundedCount = Campaign::whereIn('status', ['aktif', 'selesai'])
            ->whereColumn('dana_terkumpul', '>=', 'target_donasi')
            ->count();
            
        $successRate = $totalCampaigns > 0 ? round(($fullyFundedCount / $totalCampaigns) * 100) : 0;

        return response()->json([
            'total_raised' => $totalRaised,
            'total_donors' => $totalDonors,
            'total_campaigns' => $totalCampaigns,
            'success_rate' => $successRate
        ]);
    }

    public function index(Request $request)
    {
        Campaign::deactivateExpired();
        $query = Campaign::with('user:id_user,nama,foto');

        if ($request->filled('search')) {
            $query->where('judul', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('kategori_id')) {
            $query->where('kategori_id', $request->kategori_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sort = $request->input('sort', 'latest');
        match ($sort) {
            'ending' => $query->orderBy('tanggal_selesai'),
            default => $query->latest(),
        };

        return response()->json($query->paginate($request->input('per_page', 9)));
    }

    public function show(Campaign $campaign)
    {
        Campaign::deactivateExpired();
        $campaign->refresh();
        $campaign->load('user:id_user,nama,email,no_hp,foto,role', 'kategori:id_kategori,nama_kategori', 'images');

        // Hitung ulang dana_terkumpul dari donasi berhasil yang sesungguhnya
        // (agar progress bar selalu akurat meski webhook belum terpanggil di dev)
        $actualRaised = $campaign->donations()->where('status', 'berhasil')->sum('jumlah');
        if ((int)$actualRaised !== (int)$campaign->dana_terkumpul) {
            $campaign->update(['dana_terkumpul' => $actualRaised]);
            $campaign->dana_terkumpul = $actualRaised;
        }

        return response()->json($campaign);
    }


    public function store(Request $request)
    {
        $request->validate([
            'kategori_id' => 'required|exists:kategori,id_kategori',
            'identities' => 'required|in:for_yourself,organization_or_company,other_people_or_community',
            'judul' => 'required|string|max:255',
            'deskripsi' => 'required|string',
            'target_donasi' => 'required|numeric|min:10000',
            'lokasi' => 'nullable|string|max:255',
            'payment_method' => 'required|string|max:100',
            'gambar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'supporting_document' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'tanggal_mulai' => 'nullable|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
        ]);

        $gambarPath = null;
        if ($request->hasFile('gambar')) {
            $gambarPath = $request->file('gambar')->store('campaigns', 'public');
        }

        $supportingDocumentPath = null;
        if ($request->hasFile('supporting_document')) {
            $supportingDocumentPath = $request->file('supporting_document')->store('campaigns/supporting-documents', 'public');
        }

        $campaign = Campaign::create([
            'id_user' => $request->user()->getKey(),
            'kategori_id' => $request->kategori_id,
            'identities' => $request->identities,
            'judul' => $request->judul,
            'slug' => Str::slug($request->judul) . '-' . Str::lower(Str::random(6)),
            'deskripsi' => $request->deskripsi,
            'target_donasi' => $request->target_donasi,
            'dana_terkumpul' => 0,
            'lokasi' => $request->lokasi,
            'gambar' => $gambarPath,
            'payment_method' => $request->payment_method,
            'supporting_document' => $supportingDocumentPath,
            'status' => 'pending',
            'tanggal_mulai' => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
        ]);

        return response()->json([
            'message' => 'Kampanye berhasil dibuat, menunggu review admin.',
            'campaign' => $campaign,
        ], 201);
    }

    public function update(Request $request, Campaign $campaign)
    {
        $request->validate([
            'kategori_id' => 'sometimes|exists:kategori,id_kategori',
            'identities' => 'sometimes|in:for_yourself,organization_or_company,other_people_or_community',
            'judul' => 'sometimes|string|max:255',
            'deskripsi' => 'sometimes|string',
            'target_donasi' => 'sometimes|numeric|min:10000',
            'lokasi' => 'nullable|string|max:255',
            'payment_method' => 'sometimes|string|max:100',
            'gambar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'supporting_document' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'status' => 'sometimes|in:pending,aktif,selesai,ditolak',
            'tanggal_mulai' => 'nullable|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
        ]);

        if ($request->hasFile('gambar')) {
            if ($campaign->gambar) {
                Storage::disk('public')->delete($campaign->gambar);
            }
            $campaign->gambar = $request->file('gambar')->store('campaigns', 'public');
        }

        if ($request->hasFile('supporting_document')) {
            if ($campaign->supporting_document) {
                Storage::disk('public')->delete($campaign->supporting_document);
            }
            $campaign->supporting_document = $request->file('supporting_document')->store('campaigns/supporting-documents', 'public');
        }

        $campaign->fill($request->only([
            'kategori_id',
            'identities',
            'judul',
            'deskripsi',
            'target_donasi',
            'lokasi',
            'payment_method',
            'status',
            'tanggal_mulai',
            'tanggal_selesai',
        ]));

        if ($request->filled('judul')) {
            $campaign->slug = Str::slug($request->judul) . '-' . Str::lower(Str::random(6));
        }

        $campaign->save();

        return response()->json([
            'message' => 'Kampanye diperbarui.',
            'campaign' => $campaign,
        ]);
    }

    public function approve(Campaign $campaign)
    {
        $campaign->update([
            'status' => 'aktif',
            'reason' => null,
        ]);

        return response()->json([
            'message' => 'Kampanye berhasil di-approve.',
            'campaign' => $campaign,
        ]);
    }

    public function reject(Request $request, Campaign $campaign)
    {
        $request->validate([
            'reason' => 'required|string|min:10',
        ], [
            'reason.required' => 'Alasan penolakan wajib diisi.',
            'reason.min' => 'Alasan penolakan minimal 10 karakter.',
        ]);

        $campaign->update([
            'status' => 'ditolak',
            'reason' => $request->reason,
        ]);

        return response()->json([
            'message' => 'Kampanye berhasil ditolak.',
            'campaign' => $campaign,
        ]);
    }

    public function destroy(Campaign $campaign)
    {
        if ($campaign->gambar) {
            Storage::disk('public')->delete($campaign->gambar);
        }

        if ($campaign->supporting_document) {
            Storage::disk('public')->delete($campaign->supporting_document);
        }

        $imagePaths = DB::table('campaign_images')
            ->where('id_campaign', $campaign->id_campaign)
            ->pluck('image_url');

        foreach ($imagePaths as $path) {
            Storage::disk('public')->delete($path);
        }

        $updateImages = DB::table('campaign_updates')
            ->where('id_campaign', $campaign->id_campaign)
            ->whereNotNull('gambar')
            ->pluck('gambar');

        foreach ($updateImages as $path) {
            Storage::disk('public')->delete($path);
        }

        $campaign->delete();

        return response()->json(['message' => 'Kampanye dihapus.']);
    }

    public function myCampaigns(Request $request)
    {
        Campaign::deactivateExpired();

        $campaigns = Campaign::where('id_user', $request->user()->getKey())
            ->withSum(['donations' => fn ($q) => $q->where('status', 'berhasil')], 'jumlah')
            ->latest()
            ->get()
            ->map(function ($c) {
                // Sinkronisasi dana_terkumpul dengan sum donasi berhasil nyata
                $actual = (int) ($c->donations_sum_jumlah ?? 0);
                if ($actual !== (int) $c->dana_terkumpul) {
                    $c->update(['dana_terkumpul' => $actual]);
                }
                $c->dana_terkumpul = $actual;
                return $c;
            });

        return response()->json($campaigns);
    }

    public function categories()
    {
        $categories = DB::table('kategori')
            ->select('id_kategori', 'nama_kategori')
            ->orderBy('nama_kategori')
            ->get();

        return response()->json($categories);
    }

    public function imagesIndex(Campaign $campaign)
    {
        $images = DB::table('campaign_images')
            ->where('id_campaign', $campaign->id_campaign)
            ->latest()
            ->get();

        return response()->json($images);
    }

    public function imagesStore(Request $request, Campaign $campaign)
    {
        if ($request->user()->getKey() !== $campaign->id_user) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $request->validate([
            'images' => 'required|array|min:1|max:10',
            'images.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $saved = [];
        foreach ($request->file('images') as $file) {
            $path = $file->store('campaigns/images', 'public');

            $id = DB::table('campaign_images')->insertGetId([
                'id_campaign' => $campaign->id_campaign,
                'image_url' => $path,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'id_image');

            $saved[] = DB::table('campaign_images')->where('id_image', $id)->first();
        }

        return response()->json([
            'message' => count($saved) . ' gambar berhasil diupload.',
            'images' => $saved,
        ], 201);
    }

    public function imagesDestroy(Request $request, Campaign $campaign, int $image)
    {
        if ($request->user()->getKey() !== $campaign->id_user) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $imageRow = DB::table('campaign_images')
            ->where('id_image', $image)
            ->where('id_campaign', $campaign->id_campaign)
            ->first();

        if (! $imageRow) {
            return response()->json(['message' => 'Gambar tidak ditemukan pada campaign ini.'], 404);
        }

        Storage::disk('public')->delete($imageRow->image_url);
        DB::table('campaign_images')->where('id_image', $image)->delete();

        return response()->json(['message' => 'Gambar berhasil dihapus.']);
    }

    public function updatesIndex(Campaign $campaign)
    {
        $updates = DB::table('campaign_updates')
            ->where('id_campaign', $campaign->id_campaign)
            ->latest()
            ->get();

        return response()->json($updates);
    }

    public function updatesStore(Request $request, Campaign $campaign)
    {
        if ($request->user()->getKey() !== $campaign->id_user) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $request->validate([
            'judul' => 'required|string|max:255',
            'deskripsi' => 'required|string',
            'gambar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $gambarPath = null;
        if ($request->hasFile('gambar')) {
            $gambarPath = $request->file('gambar')->store('campaign_updates', 'public');
        }

        $id = DB::table('campaign_updates')->insertGetId([
            'id_campaign' => $campaign->id_campaign,
            'judul' => $request->judul,
            'deskripsi' => $request->deskripsi,
            'gambar' => $gambarPath,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_update');

        return response()->json([
            'message' => 'Update berhasil dibuat.',
            'update' => DB::table('campaign_updates')->where('id_update', $id)->first(),
        ], 201);
    }

    public function updatesUpdate(Request $request, Campaign $campaign, int $update)
    {
        if ($request->user()->getKey() !== $campaign->id_user) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $updateRow = DB::table('campaign_updates')
            ->where('id_update', $update)
            ->where('id_campaign', $campaign->id_campaign)
            ->first();

        if (! $updateRow) {
            return response()->json(['message' => 'Update tidak ditemukan pada campaign ini.'], 404);
        }

        $request->validate([
            'judul' => 'sometimes|string|max:255',
            'deskripsi' => 'sometimes|string',
            'gambar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $payload = $request->only(['judul', 'deskripsi']);

        if ($request->hasFile('gambar')) {
            if ($updateRow->gambar) {
                Storage::disk('public')->delete($updateRow->gambar);
            }
            $payload['gambar'] = $request->file('gambar')->store('campaign_updates', 'public');
        }

        $payload['updated_at'] = now();

        DB::table('campaign_updates')
            ->where('id_update', $update)
            ->update($payload);

        return response()->json([
            'message' => 'Update berhasil diperbarui.',
            'update' => DB::table('campaign_updates')->where('id_update', $update)->first(),
        ]);
    }

    public function updatesDestroy(Request $request, Campaign $campaign, int $update)
    {
        if ($request->user()->getKey() !== $campaign->id_user) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $updateRow = DB::table('campaign_updates')
            ->where('id_update', $update)
            ->where('id_campaign', $campaign->id_campaign)
            ->first();

        if (! $updateRow) {
            return response()->json(['message' => 'Update tidak ditemukan pada campaign ini.'], 404);
        }

        if ($updateRow->gambar) {
            Storage::disk('public')->delete($updateRow->gambar);
        }

        DB::table('campaign_updates')->where('id_update', $update)->delete();

        return response()->json(['message' => 'Update berhasil dihapus.']);
    }
}
