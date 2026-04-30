<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Donation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        // IDs kampanye milik user ini
        $campaignIds = Campaign::where('user_id', $userId)->pluck('id');

        // Total donasi yang masuk ke semua kampanye user
        $totalDonations = Donation::whereIn('campaign_id', $campaignIds)
            ->where('status', 'paid')
            ->sum('amount');

        // Jumlah kampanye aktif
        $activeCampaigns = Campaign::where('user_id', $userId)
            ->where('status', 'active')
            ->count();

        // Jumlah donor unik
        $totalDonors = Donation::whereIn('campaign_id', $campaignIds)
            ->where('status', 'paid')
            ->distinct('user_id')
            ->count('user_id');

        // Dana yang sudah dicairkan (status disbursed)
        $fundDisbursed = Donation::whereIn('campaign_id', $campaignIds)
            ->where('status', 'disbursed')
            ->sum('amount');

        // Donasi terbaru (5 terakhir)
        $recentDonations = Donation::with([
            'user:id,name,photo',
            'campaign:id,title,slug',
        ])
            ->whereIn('campaign_id', $campaignIds)
            ->where('status', 'paid')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($d) {
                if ($d->anonymous) {
                    $d->user = (object) ['name' => 'Anonim', 'photo' => null];
                }
                return $d;
            });

        // Kampanye milik user dengan progress
        $myCampaigns = Campaign::where('user_id', $userId)
            ->withSum(['donations' => fn($q) => $q->where('status', 'paid')], 'amount')
            ->withCount('donations')
            ->latest()
            ->get()
            ->map(function ($c) {
                $c->percentage = $c->goal_amount > 0
                    ? min(100, round(($c->donations_sum_amount / $c->goal_amount) * 100))
                    : 0;
                return $c;
            });

        // Grafik donasi per bulan (12 bulan terakhir)
        $chartData = Donation::whereIn('campaign_id', $campaignIds)
            ->where('status', 'paid')
            ->where('created_at', '>=', now()->subMonths(12))
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'stats' => [
                'total_donations' => $totalDonations,
                'active_campaigns' => $activeCampaigns,
                'total_donors' => $totalDonors,
                'fund_disbursed' => $fundDisbursed,
            ],
            'recent_donations' => $recentDonations,
            'my_campaigns' => $myCampaigns,
            'chart_data' => $chartData,
        ]);
    }

    public function activity(Request $request)
    {
        $userId = $request->user()->id;
        $campaignIds = Campaign::where('user_id', $userId)->pluck('id');

        // Gabungkan berbagai jenis aktivitas
        $donations = Donation::with(['user:id,name', 'campaign:id,title'])
            ->whereIn('campaign_id', $campaignIds)
            ->where('status', 'paid')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn($d) => [
                'type' => 'donation',
                'icon' => 'ðŸ’š',
                'message' => ($d->anonymous ? 'Anonim' : $d->user->name)
                    . ' berdonasi Rp ' . number_format($d->amount, 0, ',', '.')
                    . ' ke ' . $d->campaign->title,
                'time' => $d->created_at->diffForHumans(),
                'read' => false,
            ]);

        $milestones = Campaign::where('user_id', $userId)
            ->where('updated_at', '>=', now()->subDays(30))
            ->whereIn('status', ['completed', 'active'])
            ->get()
            ->map(fn($c) => [
                'type' => 'milestone',
                'icon' => 'ðŸ“£',
                'message' => 'Kampanye "' . $c->title . '" mencapai status ' . $c->status,
                'time' => $c->updated_at->diffForHumans(),
                'read' => true,
            ]);

        $activities = collect($donations)
            ->merge($milestones)
            ->sortByDesc('time')
            ->values();

        return response()->json($activities);
    }
}
