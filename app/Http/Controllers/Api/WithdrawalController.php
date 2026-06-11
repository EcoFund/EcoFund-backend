<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Withdrawal;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    // ── Fundraiser: request pencairan dana ─────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'id_campaign'    => 'required|exists:campaigns,id_campaign',
            'jumlah'         => 'required|numeric|min:10000',
            'nama_bank'      => 'required|string|max:100',
            'nomor_rekening' => 'required|string|max:50',
            'atas_nama'      => 'required|string|max:100',
        ]);

        $campaign = Campaign::findOrFail($request->id_campaign);

        // Pastikan campaign milik fundraiser yang login
        if ($campaign->id_user !== $request->user()->getKey()) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        // Hanya bisa request jika campaign sudah selesai
        $isExpired  = $campaign->tanggal_selesai && now()->startOfDay()->gt($campaign->tanggal_selesai);
        $isFinished = $campaign->status === 'selesai';

        if (!$isExpired && !$isFinished) {
            return response()->json([
                'message' => 'Pencairan hanya bisa dilakukan setelah kampanye selesai atau tanggal berakhir.',
            ], 422);
        }

        // Hitung total sudah dicairkan (approved)
        $totalWithdrawn = Withdrawal::where('id_campaign', $campaign->id_campaign)
            ->where('status', 'approved')
            ->sum('jumlah');

        $sisaDana = $campaign->dana_terkumpul - $totalWithdrawn;

        if ($request->jumlah > $sisaDana) {
            return response()->json([
                'message' => "Jumlah melebihi sisa dana yang tersedia (Rp " . number_format($sisaDana, 0, ',', '.') . ").",
            ], 422);
        }

        $withdrawal = Withdrawal::create([
            'id_campaign'    => $campaign->id_campaign,
            'jumlah'         => $request->jumlah,
            'nama_bank'      => $request->nama_bank,
            'nomor_rekening' => $request->nomor_rekening,
            'atas_nama'      => $request->atas_nama,
            'status'         => 'pending',
        ]);

        return response()->json([
            'message'    => 'Request pencairan berhasil diajukan.',
            'withdrawal' => $withdrawal,
        ], 201);
    }

    // ── Fundraiser: lihat riwayat withdrawal miliknya ───────────────
    public function myWithdrawals(Request $request)
    {
        $campaignId = $request->query('campaign_id');

        $query = Withdrawal::with('campaign:id_campaign,judul,slug,dana_terkumpul,tanggal_selesai,status')
            ->whereHas('campaign', fn($q) => $q->where('id_user', $request->user()->getKey()));

        if ($campaignId) {
            $query->where('id_campaign', $campaignId);
        }

        $withdrawals = $query->latest()->get()->map(function ($w) {
            $totalApproved = Withdrawal::where('id_campaign', $w->id_campaign)
                ->where('status', 'approved')
                ->sum('jumlah');

            return [
                'id_withdraw'    => $w->id_withdraw,
                'id_campaign'    => $w->id_campaign,
                'campaign_judul' => $w->campaign?->judul,
                'jumlah'         => $w->jumlah,
                'nama_bank'      => $w->nama_bank,
                'nomor_rekening' => $w->nomor_rekening,
                'atas_nama'      => $w->atas_nama,
                'status'         => $w->status,
                'catatan_admin'  => $w->catatan_admin,
                'created_at'     => $w->created_at,
                'dana_terkumpul' => $w->campaign?->dana_terkumpul,
                'sisa_dana'      => ($w->campaign?->dana_terkumpul ?? 0) - $totalApproved,
            ];
        });

        return response()->json($withdrawals);
    }

    // ── Admin: lihat semua withdrawal request ──────────────────────
    public function index(Request $request)
    {
        $status = $request->query('status'); // pending, approved, rejected, all

        $query = Withdrawal::with([
            'campaign:id_campaign,judul,slug,dana_terkumpul,id_user',
            'campaign.user:id_user,nama,email',
        ])->latest();

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $withdrawals = $query->paginate(20)->through(function ($w) {
            return [
                'id_withdraw'    => $w->id_withdraw,
                'jumlah'         => $w->jumlah,
                'nama_bank'      => $w->nama_bank,
                'nomor_rekening' => $w->nomor_rekening,
                'atas_nama'      => $w->atas_nama,
                'status'         => $w->status,
                'catatan_admin'  => $w->catatan_admin,
                'created_at'     => $w->created_at,
                'campaign'       => [
                    'id_campaign'    => $w->campaign?->id_campaign,
                    'judul'          => $w->campaign?->judul,
                    'slug'           => $w->campaign?->slug,
                    'dana_terkumpul' => $w->campaign?->dana_terkumpul,
                ],
                'fundraiser' => [
                    'nama'  => $w->campaign?->user?->nama,
                    'email' => $w->campaign?->user?->email,
                ],
            ];
        });

        return response()->json($withdrawals);
    }

    // ── Admin: approve withdrawal ───────────────────────────────────
    public function approve(Request $request, Withdrawal $withdrawal)
    {
        if ($withdrawal->status !== 'pending') {
            return response()->json(['message' => 'Request ini sudah diproses.'], 422);
        }

        $withdrawal->update(['status' => 'approved']);

        return response()->json(['message' => 'Request pencairan disetujui.', 'withdrawal' => $withdrawal]);
    }

    // ── Admin: reject withdrawal ────────────────────────────────────
    public function reject(Request $request, Withdrawal $withdrawal)
    {
        $request->validate([
            'catatan_admin' => 'nullable|string|max:500',
        ]);

        if ($withdrawal->status !== 'pending') {
            return response()->json(['message' => 'Request ini sudah diproses.'], 422);
        }

        $withdrawal->update([
            'status'        => 'rejected',
            'catatan_admin' => $request->catatan_admin,
        ]);

        return response()->json(['message' => 'Request pencairan ditolak.', 'withdrawal' => $withdrawal]);
    }
}
