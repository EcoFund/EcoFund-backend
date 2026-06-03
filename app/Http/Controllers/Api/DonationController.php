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
            'nama_donatur' => 'required_without:anonymous|string|max:255',
            'bukti_pembayaran' => 'required|image|mimes:jpeg,png,jpg|max:2048', // Optional, max 2MB
        ], [
            'nama_donatur.required_if' => 'Nama donatur wajib diisi jika tidak anonim.',
            'amount.required' => 'Jumlah donasi wajib diisi.',
            'amount.min' => 'Jumlah donasi minimal adalah 1000.',
            'bukti_pembayaran.required' => 'Bukti pembayaran wajib diupload.',
        ]);


       if ($campaign->status !== 'active' && $campaign->status !== 'aktif') {
    return response()->json([
        'message' => 'Kampanye tidak aktif.',
         'status_dari_db' => $campaign->status,
            'status_yang_diharapkan' => 'aktif'
            ], 422);
}

        if ($campaign->tanggal_selesai && now()->isAfter($campaign->tanggal_selesai)) {
            return response()->json(['message' => 'Kampanye sudah berakhir.'], 422);
        }

        $isAnonymous = $request->boolean('anonymous', false);
        $user = $request->user();

        $namaDonatur = $isAnonymous ? 'Anonim' : ($request->nama_donatur ?: ($user->nama ?? $user->name ?? 'Donatur'));
        $buktiPath = $request->file('bukti_pembayaran')->store('bukti_pembayaran', 'public');



        $donation = Donation::create([
            'id_campaign' => $campaign->id_campaign,
            'nama_donatur'     => $namaDonatur,
            'jumlah'      => $request->amount,
            'pesan'     => $request->message,
            'is_anonymous'   => $isAnonymous,
            'bukti_pembayaran' => $buktiPath,
            'status'      => 'berhasil', // pending sampai payment gateway konfirmasi
        ]);

        $campaign->increment('dana_terkumpul', $request->amount);

        // Cek apakah goal tercapai
        if ($campaign->fresh()->dana_terkumpul >= $campaign->target_donasi) {
            $campaign->update(['status' => 'selesai']);
        }

        return response()->json([
            'message'  => 'Donasi berhasil! Terima Kasih.',
            
            'donation' => $donation,
        ], 201);

        
    }

    // â”€â”€ Riwayat donasi user login â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function myDonations(Request $request)
    {
        $donations = Donation::with('campaign:id_campaign,title,image,slug')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return response()->json($donations);
    }




    // â”€â”€ Daftar donasi di satu campaign (publik) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function campaignDonations(Campaign $campaign)
    {
        try {
            $donations = Donation::where('id_campaign', $campaign->id_campaign)
                ->where('status', 'berhasil')
                ->latest()
                ->get()
                ->map(fn($d) =>[
                    'id_donasi' => $d->id_donasi,
                    'nama_donatur' => $d->is_anonymous ? 'Anonim' : $d->nama_donatur,
                    'jumlah' => $d->jumlah,
                    'created_at' => $d->created_at,
                    'anonymous' => (bool) $d->is_anonymous,
                    'amount' => $d->jumlah,
                    'message' => $d->pesan,
                ]
                
            );

        return response()->json($donations);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }}

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
