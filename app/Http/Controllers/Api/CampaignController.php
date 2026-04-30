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
    public function index(Request $request)
    {
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

        $sort = $request->get('sort', 'latest');
        match ($sort) {
            'ending' => $query->orderBy('tanggal_selesai'),
            default => $query->latest(),
        };

        return response()->json($query->paginate($request->get('per_page', 9)));
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function show(Campaign $campaign)
    {
        $campaign->load('user:id_user,nama,email,no_hp,foto,role', 'kategori:id_kategori,nama_kategori');

        return response()->json($campaign);
    }

    #[OA\Post(
        path: '/campaigns',
        operationId: 'createCampaign',
        summary: 'Buat campaign baru',
        security: [['sanctum' => []]],
        tags: ['Campaigns'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['kategori_id', 'identities', 'judul', 'deskripsi', 'target_donasi', 'payment_method'],
                    properties: [
                        new OA\Property(property: 'kategori_id', type: 'integer', example: 1),
                        new OA\Property(
                            property: 'identities',
                            type: 'string',
                            example: 'other_people_or_community',
                            enum: ['for_yourself', 'organization_or_company', 'other_people_or_community']
                        ),
                        new OA\Property(property: 'judul', type: 'string', example: 'Bantu Renovasi Sekolah'),
                        new OA\Property(property: 'deskripsi', type: 'string', example: 'Penggalangan dana untuk renovasi sekolah desa.'),
                        new OA\Property(property: 'target_donasi', type: 'integer', example: 50000000),
                        new OA\Property(property: 'lokasi', type: 'string', example: 'Bandung'),
                        new OA\Property(property: 'payment_method', type: 'string', example: 'bank_transfer'),
                        new OA\Property(property: 'gambar', type: 'string', format: 'binary'),
                        new OA\Property(property: 'supporting_document', type: 'string', format: 'binary'),
                        new OA\Property(property: 'tanggal_mulai', type: 'string', format: 'date', example: '2026-04-24'),
                        new OA\Property(property: 'tanggal_selesai', type: 'string', format: 'date', example: '2026-05-30'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Campaign berhasil dibuat'),
            new OA\Response(response: 401, description: 'Belum login'),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ]
    )]
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
        dd($request->all());

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
            'reason.min'      => 'Alasan penolakan minimal 10 karakter.',
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

        $campaign->delete();

        return response()->json(['message' => 'Kampanye dihapus.']);
    }

    public function myCampaigns(Request $request)
    {
        $campaigns = Campaign::where(
            'id_user',
            $request->user()->getKey()
        )
            ->latest()
            ->get();

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
}
