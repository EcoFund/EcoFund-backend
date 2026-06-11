<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\DeleteRequest;
use App\Models\Donation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * GET /api/admin/stats
     * Dashboard stats for the admin panel.
     * Requires auth:sanctum + admin role.
     */
    public function stats(Request $request)
    {
        Campaign::deactivateExpired();

        // Campaign counts by status
        $statusCounts = Campaign::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $active    = $statusCounts['aktif']    ?? 0;
        $pending   = $statusCounts['pending']  ?? 0;
        $selesai   = $statusCounts['selesai']  ?? 0;
        $ditolak   = $statusCounts['ditolak']  ?? 0;
        $totalCamp = $active + $pending + $selesai + $ditolak;

        // Donation totals
        $totalDonasi  = Donation::where('status', 'berhasil')->sum('jumlah');
        $totalDonatur = Donation::where('status', 'berhasil')
            ->whereNotNull('nama_donatur')
            ->where('is_anonymous', false)
            ->distinct('nama_donatur')
            ->count('nama_donatur');

        // Top 4 donors (non-anonymous, by total donation amount)
        $topDonors = Donation::where('status', 'berhasil')
            ->where('is_anonymous', false)
            ->whereNotNull('nama_donatur')
            ->where('nama_donatur', '!=', '')
            ->select('nama_donatur', DB::raw('SUM(jumlah) as total_donasi'))
            ->groupBy('nama_donatur')
            ->orderByDesc('total_donasi')
            ->limit(4)
            ->get();

        // Calculate combined anonymous donations
        $anonSum = Donation::where('status', 'berhasil')
            ->where(function($query) {
                $query->where('is_anonymous', true)
                      ->orWhereNull('nama_donatur')
                      ->orWhere('nama_donatur', '');
            })
            ->sum('jumlah');

        if ($anonSum > 0) {
            $topDonors->push((object)[
                'nama_donatur' => 'Anonim',
                'total_donasi' => $anonSum
            ]);
            // Re-sort to put Anonim in the right place
            $topDonors = $topDonors->sortByDesc('total_donasi')->values()->take(5);
        }

        // 5 latest successful donations
        $recentDonations = Donation::with('campaign:id_campaign,judul,slug')
            ->where('status', 'berhasil')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($d) {
                return [
                    'id_donasi'    => $d->id_donasi,
                    'nama_donatur' => $d->is_anonymous ? 'Anonim' : ($d->nama_donatur ?? 'Anonim'),
                    'jumlah'       => $d->jumlah,
                    'campaign'     => $d->campaign ? ['judul' => $d->campaign->judul, 'slug' => $d->campaign->slug] : null,
                    'created_at'   => $d->created_at,
                ];
            });

        // Delete requests pending
        $deleteRequests = DeleteRequest::with([
            'campaign:id_campaign,judul,slug',
            'user:id_user,nama,email',
        ])
            ->where('status', 'pending')
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn($dr) => [
                'id'         => $dr->id,
                'alasan'     => $dr->alasan,
                'created_at' => $dr->created_at,
                'campaign'   => $dr->campaign ? ['judul' => $dr->campaign->judul, 'slug' => $dr->campaign->slug] : null,
                'user'       => $dr->user   ? ['nama'  => $dr->user->nama,       'email'=> $dr->user->email]   : null,
            ]);

        return response()->json([
            'campaigns' => [
                'total'    => $totalCamp,
                'aktif'    => $active,
                'pending'  => $pending,
                'selesai'  => $selesai,
                'ditolak'  => $ditolak,
            ],
            'donasi' => [
                'total'    => $totalDonasi,
                'donatur'  => $totalDonatur,
            ],
            'top_donors'       => $topDonors,
            'recent_donations' => $recentDonations,
            'delete_requests'  => $deleteRequests,
        ]);
    }

    /**
     * GET /api/admin/chart?period=Minggu|Bulan|Tahun
     * Period-based chart data for admin Donation Overview.
     */
    public function adminChart(Request $request)
    {
        $period = $request->query('period', 'Minggu');
        $query  = Donation::where('status', 'berhasil');
        $now    = now();
        $data   = [];

        if ($period === 'Minggu') {
            $monday    = $now->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
            $donations = $query->where('created_at', '>=', $monday->startOfDay())->get();
            $dayNames  = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
            for ($i = 0; $i <= 6; $i++) {
                $date = $monday->copy()->addDays($i);
                $sum  = $donations->where('created_at', '>=', $date->copy()->startOfDay())
                                  ->where('created_at', '<=', $date->copy()->endOfDay())
                                  ->sum('jumlah');
                $data[] = ['label' => $dayNames[$i], 'val' => (int)$sum];
            }
        } elseif ($period === 'Bulan') {
            $start     = $now->copy()->subDays(27)->startOfDay();
            $donations = $query->where('created_at', '>=', $start)->get();
            for ($i = 3; $i >= 0; $i--) {
                $weekStart = $now->copy()->subDays($i * 7 + 6)->startOfDay();
                $weekEnd   = $now->copy()->subDays($i * 7)->endOfDay();
                $sum = $donations->where('created_at', '>=', $weekStart)
                                 ->where('created_at', '<=', $weekEnd)
                                 ->sum('jumlah');
                $data[] = ['label' => 'Mg ' . (4 - $i), 'val' => (int)$sum];
            }
        } elseif ($period === 'Tahun') {
            $yearStart = $now->copy()->startOfYear();
            $donations = $query->whereYear('created_at', $now->year)->get();
            $months    = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
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

    /**
     * GET /api/admin/users
     * List all users (admin only).
     */
    public function users(Request $request)
    {
        $query = User::select('id_user', 'nama', 'email', 'no_hp', 'foto', 'role', 'created_at')
            ->latest();

        // Filter by role if provided
        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        // Search by name or email
        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', $search)
                  ->orWhere('email', 'like', $search);
            });
        }

        $users = $query->paginate($request->input('per_page', 10));

        return response()->json($users);
    }

    /**
     * PATCH /api/admin/users/{id}/role
     * Update user role.
     */
    public function updateRole(Request $request, $id)
    {
        $request->validate([
            'role' => 'required|string|in:admin,campaigner,donatur',
        ]);

        $user = User::findOrFail($id);

        if ($user->id_user === $request->user()->id_user) {
            return response()->json(['message' => 'Anda tidak bisa mengubah role Anda sendiri.'], 422);
        }

        $user->update(['role' => $request->role]);

        return response()->json([
            'message' => 'Role user berhasil diperbarui.',
            'user' => $user
        ]);
    }

    /**
     * DELETE /api/admin/users/{id}
     * Delete user.
     */
    public function destroyUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->id_user === $request->user()->id_user) {
            return response()->json(['message' => 'Anda tidak bisa menghapus akun Anda sendiri.'], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'User berhasil dihapus.'
        ]);
    }

    /**
     * GET /api/admin/users/{id}
     * Show full fundraiser profile (including KTP info).
     */
    public function showUser($id)
    {
        $user = User::select(
            'id_user', 'nama', 'email', 'no_hp', 'foto', 'role',
            'nik', 'foto_ktp', 'status_verifikasi', 'catatan_verifikasi', 'created_at'
        )->findOrFail($id);

        return response()->json($user);
    }

    /**
     * PATCH /api/admin/users/{id}/verify
     * Update fundraiser verification status.
     */
    public function verifyUser(Request $request, $id)
    {
        $request->validate([
            'status_verifikasi'  => 'required|in:belum_diverifikasi,terverifikasi,ditolak',
            'catatan_verifikasi' => 'nullable|string|max:500',
        ]);

        $user = User::findOrFail($id);

        $user->update([
            'status_verifikasi'  => $request->status_verifikasi,
            'catatan_verifikasi' => $request->catatan_verifikasi,
        ]);

        return response()->json([
            'message' => 'Status verifikasi berhasil diperbarui.',
            'user'    => $user,
        ]);
    }
}
