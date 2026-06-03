<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Donation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Xendit\Configuration;
use Xendit\Invoice\InvoiceApi;
use Xendit\Invoice\CreateInvoiceRequest;

class DonationController extends Controller
{
    // â”€â”€ Donasi ke campaign â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function store(Request $request, Campaign $campaign)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1000',
            'anonymous' => 'boolean',
            'nama_donatur' => 'nullable|string|max:255'
        ]);

        Campaign::deactivateExpired();
        $campaign->refresh();

        if (now()->startOfDay()->isAfter($campaign->tanggal_selesai)) {
            return response()->json(['message' => 'Kampanye sudah berakhir.'], 422);
        }

        if ($campaign->status !== 'aktif') {
            return response()->json(['message' => 'Kampanye tidak aktif.'], 422);
        }

        Configuration::setXenditKey(config('services.xendit.secret_key'));
        $client = new \GuzzleHttp\Client(['verify' => false]);
        $apiInstance = new InvoiceApi($client);
        
        $externalId = 'DONASI-' . uniqid();

        // Buat record donasi terlebih dahulu agar kita punya ID-nya untuk dimasukkan ke redirect_url
        $isAnonymous = $request->boolean('anonymous', false);
        $namaDonatur = $isAnonymous ? null : ($request->nama_donatur ?? 'Donatur');

        $donation = Donation::create([
            'id_campaign'       => $campaign->id_campaign,
            'nama_donatur'      => $namaDonatur,
            'jumlah'            => $request->amount,
            'is_anonymous'      => $isAnonymous,
            'status'            => 'pending', 
            'payment_url'       => null,
            'xendit_invoice_id' => null,
        ]);

        $redirectUrl = env('FRONTEND_URL', 'http://localhost:5173') . '/campaigns/' . $campaign->slug . '?verify=' . $donation->id_donasi;

        $createInvoiceRequest = new CreateInvoiceRequest([
            'external_id' => $externalId,
            'description' => 'Donasi untuk ' . $campaign->judul,
            'amount' => $request->amount,
            'invoice_duration' => 86400,
            'success_redirect_url' => $redirectUrl,
            'failure_redirect_url' => env('FRONTEND_URL', 'http://localhost:5173') . '/campaigns/' . $campaign->slug,
        ]);

        try {
            $result = $apiInstance->createInvoice($createInvoiceRequest);
            $donation->update([
                'payment_url' => $result['invoice_url'],
                'xendit_invoice_id' => $result['id']
            ]);
        } catch (\Exception $e) {
            $donation->delete();
            return response()->json(['message' => 'Gagal membuat tagihan pembayaran: ' . $e->getMessage(), 'error' => $e->getMessage()], 500);
        }

        // Cek apakah goal tercapai (bisa diabaikan saat masih pending)
        $totalRaised = $campaign->donations()->where('status', 'berhasil')->sum('jumlah');
        if ($totalRaised >= $campaign->target_donasi) {
            $campaign->update(['status' => 'selesai']);
        }

        return response()->json([
            'message'  => 'Donasi berhasil dicatat, mengarahkan ke pembayaran.',
            'donation' => $donation,
            'payment_url' => $result['invoice_url']
        ], 201);
    }

    // ── Riwayat donasi user login ─────────────────────────────
    public function myDonations(Request $request)
    {
        $donations = Donation::with('campaign:id_campaign,judul,gambar,slug')
            ->where('nama_donatur', $request->user()->nama)
            ->where('is_anonymous', false)
            ->latest()
            ->paginate(10);

        return response()->json($donations);
    }

    // ── Donasi masuk ke semua campaign milik fundraiser ──────
    // Dipakai di dashboard fundraiser untuk "Recent Donations"
    public function fundraiserDonations(Request $request)
    {
        $campaignIds = Campaign::where('id_user', $request->user()->getKey())
            ->pluck('id_campaign');

        $donations = Donation::with('campaign:id_campaign,judul,slug')
            ->whereIn('id_campaign', $campaignIds)
            ->where('status', 'berhasil')
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($d) {
                return [
                    'id_donasi'    => $d->id_donasi,
                    'nama_donatur' => $d->is_anonymous ? 'Anonim 🌿' : ($d->nama_donatur ?? 'Donatur'),
                    'jumlah'       => $d->jumlah,
                    'status'       => $d->status,
                    'campaign'     => $d->campaign
                                        ? ['judul' => $d->campaign->judul, 'slug' => $d->campaign->slug]
                                        : null,
                    'created_at'   => $d->created_at,
                ];
            });

        return response()->json($donations);
    }

    // ── Data statistik donasi untuk chart Fundraiser ──────
    public function fundraiserStats(Request $request)
    {
        $period = $request->query('period', 'Minggu');
        $campaignIds = Campaign::where('id_user', $request->user()->getKey())->pluck('id_campaign');
        
        $query = Donation::whereIn('id_campaign', $campaignIds)
            ->where('status', 'berhasil');

        $data = [];
        $now = now();

        if ($period === 'Minggu') {
            $monday = $now->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
            $donations = $query->where('created_at', '>=', $monday->startOfDay())->get();
            $dayNames = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
            for ($i = 0; $i <= 6; $i++) {
                $date = $monday->copy()->addDays($i);
                $sum = $donations->where('created_at', '>=', $date->copy()->startOfDay())
                                 ->where('created_at', '<=', $date->copy()->endOfDay())
                                 ->sum('jumlah');
                $data[] = ['label' => $dayNames[$i], 'val' => (int)$sum];
            }
        } elseif ($period === 'Bulan') {
            $start = $now->copy()->subDays(27)->startOfDay();
            $donations = $query->where('created_at', '>=', $start)->get();
            for ($i = 3; $i >= 0; $i--) {
                $weekStart = $now->copy()->subDays($i * 7 + 6)->startOfDay();
                $weekEnd = $now->copy()->subDays($i * 7)->endOfDay();
                $sum = $donations->where('created_at', '>=', $weekStart)
                                 ->where('created_at', '<=', $weekEnd)
                                 ->sum('jumlah');
                $data[] = ['label' => 'Mg ' . (4 - $i), 'val' => (int)$sum];
            }
        } elseif ($period === 'Tahun') {
            // Tampilkan Jan–Des tahun kalender berjalan (bukan rolling 12 bulan)
            $yearStart = $now->copy()->startOfYear();
            $yearEnd   = $now->copy()->endOfYear();
            $donations = $query->whereYear('created_at', $now->year)->get();
            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            for ($m = 1; $m <= 12; $m++) {
                $monthStart = $yearStart->copy()->month($m)->startOfMonth();
                $monthEnd   = $yearStart->copy()->month($m)->endOfMonth();
                $sum = $donations->where('created_at', '>=', $monthStart)
                                 ->where('created_at', '<=', $monthEnd)
                                 ->sum('jumlah');
                $data[] = ['label' => $months[$m - 1], 'val' => (int)$sum];
            }
        }

        return response()->json($data);
    }

    // ─── Daftar donasi di satu campaign (publik) ──────────────────
    public function campaignDonations(Campaign $campaign)
    {
        $donations = Donation::where('id_campaign', $campaign->id_campaign)
            ->where('status', 'berhasil')          // hanya tampilkan yg sudah berhasil
            ->latest()
            ->paginate(20)
            ->through(function ($d) {
                return [
                    'id_donasi'    => $d->id_donasi,
                    'nama_donatur' => $d->is_anonymous ? 'Anonim 🌿' : ($d->nama_donatur ?? 'Donatur'),
                    'jumlah'       => $d->jumlah,
                    'is_pending'   => false,
                    'anonymous'    => $d->is_anonymous,
                    'created_at'   => $d->created_at,
                ];
            });

        return response()->json($donations);
    }

    // ── Cek & konfirmasi donasi setelah redirect dari Xendit ────
    public function checkStatus(Donation $donation)
    {
        // Sudah berhasil – kembalikan status tanpa ubah apapun
        if ($donation->status === 'berhasil') {
            return response()->json(['status' => 'berhasil', 'message' => 'Donasi sudah berhasil.']);
        }

        // Mode Test/Sandbox: langsung set berhasil tanpa verify ke Xendit
        $donation->update(['status' => 'berhasil']);

        $campaign = $donation->campaign;

        // Hitung ulang dari DB agar tidak double-count
        $actualRaised = $campaign->donations()->where('status', 'berhasil')->sum('jumlah');
        $campaign->update(['dana_terkumpul' => $actualRaised]);

        if ($actualRaised >= $campaign->target_donasi) {
            $campaign->update(['status' => 'selesai']);
        }

        return response()->json(['status' => 'berhasil', 'message' => 'Donasi berhasil dikonfirmasi.']);
    }



    // â”€â”€ Webhook / konfirmasi pembayaran â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Dipanggil oleh payment gateway (Midtrans, dll.)
    public function confirm(Request $request)
    {
        $xenditXCallbackToken = $request->header('x-callback-token');
        if ($xenditXCallbackToken !== config('services.xendit.webhook_token')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $donation = Donation::where('xendit_invoice_id', $request->id)->first();
        if (!$donation) {
            return response()->json(['message' => 'Donasi tidak ditemukan.'], 404);
        }

        $status = 'pending';
        if ($request->status === 'PAID' || $request->status === 'SETTLED') {
            $status = 'berhasil';
        } elseif ($request->status === 'EXPIRED' || $request->status === 'FAILED') {
            $status = 'gagal';
        }

        if ($donation->status === $status) {
            return response()->json(['message' => 'Status sudah tersinkronisasi.']);
        }

        $donation->update(['status' => $status]);

        if ($status === 'berhasil') {
            // Periksa goal campaign
            $campaign = $donation->campaign;
            $campaign->increment('dana_terkumpul', $donation->jumlah);
            
            if ($campaign->dana_terkumpul >= $campaign->target_donasi) {
                $campaign->update(['status' => 'selesai']);
            }
        }

        return response()->json(['message' => 'Status donasi diperbarui.']);
    }
}
