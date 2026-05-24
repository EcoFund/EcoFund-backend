<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CampaignImageController extends Controller
{
    /**
     * GET /campaigns/{campaign}/images
     * Daftar semua gambar milik campaign (publik)
     */
    public function index(Campaign $campaign)
    {
        return response()->json($campaign->images);
    }

    /**
     * POST /campaigns/{campaign}/images
     * Upload satu atau lebih gambar (hanya pemilik campaign)
     */
    public function store(Request $request, Campaign $campaign)
    {
        // Pastikan yang upload adalah pemilik campaign
        if ($request->user()->getKey() !== $campaign->id_user) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $request->validate([
            'images'   => 'required|array|min:1|max:10',
            'images.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $saved = [];
        foreach ($request->file('images') as $file) {
            $path  = $file->store('campaigns/images', 'public');
            $image = CampaignImage::create([
                'id_campaign' => $campaign->id_campaign,
                'image_url'   => $path,
            ]);
            $saved[] = $image;
        }

        return response()->json([
            'message' => count($saved) . ' gambar berhasil diupload.',
            'images'  => $saved,
        ], 201);
    }

    /**
     * DELETE /campaigns/{campaign}/images/{image}
     * Hapus satu gambar (hanya pemilik campaign)
     */
    public function destroy(Request $request, Campaign $campaign, CampaignImage $image)
    {
        if ($request->user()->getKey() !== $campaign->id_user) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($image->id_campaign !== $campaign->id_campaign) {
            return response()->json(['message' => 'Gambar tidak ditemukan pada campaign ini.'], 404);
        }

        Storage::disk('public')->delete($image->image_url);
        $image->delete();

        return response()->json(['message' => 'Gambar berhasil dihapus.']);
    }
}
