<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignUpdate;
use Illuminate\Http\Request;

class CampaignUpdateController extends Controller
{
<<<<<<< HEAD
     public function index(Campaign $campaign)
    {
        return response()->json(
            $campaign->updates()->latest()->get()
        );
    }

    public function store(Request $request, Campaign $campaign)
{
    $request->validate([
        'judul' => 'required|string|max:255',
        'deskripsi' => 'required|string',
        'gambar' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120'
    ]);

    if ($campaign->id_user !== $request->user()->getKey()) {
        return response()->json([
            'message' => 'Unauthorized'
        ], 403);
    }

    $gambarPath = null;

    if ($request->hasFile('gambar')) {
        $gambarPath = $request
            ->file('gambar')
            ->store('campaign_updates', 'public');

   
    }

    $update = CampaignUpdate::create([
        'id_campaign' => $campaign->id_campaign,
        'judul' => $request->judul,
        'deskripsi' => $request->deskripsi,
        'gambar' => $gambarPath
    ]);

    return response()->json($update, 201);
}

    public function destroy(Request $request, Campaign $campaign, CampaignUpdate $update)
    {
        if ($campaign->id_user !== $request->user()->getKey()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $update->delete();
        return response()->json(['message' => 'Update dihapus.']);
=======
    /**
     * GET /campaigns/{campaign}/updates
     * Daftar semua update campaign (publik, terbaru duluan)
     */
    public function index(Campaign $campaign)
    {
        return response()->json($campaign->updates()->get());
    }

    /**
     * POST /campaigns/{campaign}/updates
     * Buat update baru (hanya pemilik campaign)
     */
    public function store(Request $request, Campaign $campaign)
    {
        if ($request->user()->getKey() !== $campaign->id_user) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $request->validate([
            'judul'     => 'required|string|max:255',
            'deskripsi' => 'required|string',
        ]);

        $update = CampaignUpdate::create([
            'id_campaign' => $campaign->id_campaign,
            'judul'       => $request->judul,
            'deskripsi'   => $request->deskripsi,
        ]);

        return response()->json([
            'message' => 'Update berhasil dibuat.',
            'update'  => $update,
        ], 201);
    }

    /**
     * PUT /campaigns/{campaign}/updates/{update}
     * Edit update (hanya pemilik campaign)
     */
    public function update(Request $request, Campaign $campaign, CampaignUpdate $update)
    {
        if ($request->user()->getKey() !== $campaign->id_user) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($update->id_campaign !== $campaign->id_campaign) {
            return response()->json(['message' => 'Update tidak ditemukan pada campaign ini.'], 404);
        }

        $request->validate([
            'judul'     => 'sometimes|string|max:255',
            'deskripsi' => 'sometimes|string',
        ]);

        $update->fill($request->only(['judul', 'deskripsi']))->save();

        return response()->json([
            'message' => 'Update berhasil diperbarui.',
            'update'  => $update,
        ]);
    }

    /**
     * DELETE /campaigns/{campaign}/updates/{update}
     * Hapus update (hanya pemilik campaign)
     */
    public function destroy(Request $request, Campaign $campaign, CampaignUpdate $update)
    {
        if ($request->user()->getKey() !== $campaign->id_user) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($update->id_campaign !== $campaign->id_campaign) {
            return response()->json(['message' => 'Update tidak ditemukan pada campaign ini.'], 404);
        }

        $update->delete();

        return response()->json(['message' => 'Update berhasil dihapus.']);
>>>>>>> 69639d6685384b02e0fdfa32d80d01c137f030ba
    }
}
