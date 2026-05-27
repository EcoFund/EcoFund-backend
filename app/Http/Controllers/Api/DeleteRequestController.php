<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\DeleteRequest;
use Illuminate\Http\Request;

class DeleteRequestController extends Controller
{
     public function store(Request $request, Campaign $campaign)
    {
        $request->validate([
            'alasan' => 'required|string|min:10',
        ]);

        if ($campaign->id_user !== $request->user()->getKey()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Cek apakah sudah ada request pending
        $existing = DeleteRequest::where('id_campaign', $campaign->id_campaign)
            ->where('status', 'pending')->first();

        if ($existing) {
            return response()->json(['message' => 'Request delete sudah ada dan masih pending.'], 422);
        }

        $dr = DeleteRequest::create([
            'id_campaign' => $campaign->id_campaign,
            'id_user'     => $request->user()->getKey(),
            'alasan'      => $request->alasan,
        ]);

        return response()->json($dr, 201);
    }

    // Admin: lihat semua delete requests
    public function index()
    {
        $requests = DeleteRequest::with(['campaign:id_campaign,judul,slug', 'user:id_user,nama,email'])
            ->where('status', 'pending')
            ->latest()
            ->get();
        return response()->json($requests);
    }

    // Admin: approve delete
    public function approve(Request $request, DeleteRequest $deleteRequest)
    {
        $campaign = $deleteRequest->campaign;
        $campaign->delete();
        $deleteRequest->update(['status' => 'approved']);
        return response()->json(['message' => 'Campaign berhasil dihapus.']);
    }

    // Admin: reject delete request
    public function reject(Request $request, DeleteRequest $deleteRequest)
    {
        $deleteRequest->update(['status' => 'rejected']);
        return response()->json(['message' => 'Request delete ditolak.']);
    }
}
