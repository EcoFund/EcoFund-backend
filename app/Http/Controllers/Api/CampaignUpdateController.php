<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignUpdate;
use Illuminate\Http\Request;

class CampaignUpdateController extends Controller
{
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
    }
}
