<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Donation;
use Illuminate\Http\Request;

class DonationController extends Controller
{
    // â”€â”€ Donasi ke campaign â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function store(Request $request, Campaign $campaign)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1000',
            'message' => 'nullable|string|max:500',
            'anonymous' => 'boolean',
        ]);

        if ($campaign->status !== 'active') {
            return response()->json(['message' => 'Kampanye tidak aktif.'], 422);
        }

        if (now()->isAfter($campaign->deadline)) {
            return response()->json(['message' => 'Kampanye sudah berakhir.'], 422);
        }

        $donation = Donation::create([
            'campaign_id' => $campaign->id,
            'user_id'     => $request->user()->id,
            'amount'      => $request->amount,
            'message'     => $request->message,
            'anonymous'   => $request->boolean('anonymous', false),
            'status'      => 'pending', // pending sampai payment gateway konfirmasi
        ]);

        // Cek apakah goal tercapai
        $totalRaised = $campaign->donations()->where('status', 'paid')->sum('amount');
        if ($totalRaised >= $campaign->goal_amount) {
            $campaign->update(['status' => 'completed']);
        }

        return response()->json([
            'message'  => 'Donasi berhasil dicatat, menunggu pembayaran.',
            'donation' => $donation,
        ], 201);
    }

    // â”€â”€ Riwayat donasi user login â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function myDonations(Request $request)
    {
        $donations = Donation::with('campaign:id,title,image,slug')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return response()->json($donations);
    }

    // â”€â”€ Daftar donasi di satu campaign (publik) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function campaignDonations(Campaign $campaign)
    {
        $donations = Donation::with('user:id,name,photo')
            ->where('campaign_id', $campaign->id)
            ->where('status', 'paid')
            ->latest()
            ->paginate(10)
            ->through(function ($d) {
                // Sembunyikan nama jika anonymous
                if ($d->anonymous) {
                    $d->user = ['name' => 'Anonim', 'photo' => null];
                }
                return $d;
            });

        return response()->json($donations);
    }

    // â”€â”€ Webhook / konfirmasi pembayaran â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Dipanggil oleh payment gateway (Midtrans, dll.)
    public function confirm(Request $request)
    {
        $request->validate([
            'donation_id' => 'required|exists:donations,id',
            'status'      => 'required|in:paid,failed',
        ]);

        $donation = Donation::findOrFail($request->donation_id);
        $donation->update(['status' => $request->status]);

        if ($request->status === 'paid') {
            // Periksa goal campaign
            $campaign = $donation->campaign;
            $totalRaised = $campaign->donations()->where('status', 'paid')->sum('amount');
            if ($totalRaised >= $campaign->goal_amount) {
                $campaign->update(['status' => 'completed']);
            }
        }

        return response()->json(['message' => 'Status donasi diperbarui.', 'donation' => $donation]);
    }
}
