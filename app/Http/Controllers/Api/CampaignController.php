<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CampaignController extends Controller
{
    // â”€â”€ Index â€“ semua campaign (publik, dengan filter & search) â”€â”€
    public function index(Request $request)
    {
        $query = Campaign::with('user:id,name,photo')
            ->withCount('donations')
            ->withSum('donations', 'amount');

        // Search
        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        // Filter kategori
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Filter status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Urutan
        $sort = $request->get('sort', 'latest');
        match ($sort) {
            'popular' => $query->orderByDesc('donations_count'),
            'ending'  => $query->orderBy('deadline'),
            default   => $query->latest(),
        };

        $campaigns = $query->paginate($request->get('per_page', 9));

        return response()->json($campaigns);
    }

    // â”€â”€ Show â€“ detail satu campaign â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function show(Campaign $campaign)
    {
        $campaign->load([
            'user:id,name,photo,role',
            'donations.user:id,name,photo',
        ]);

        $campaign->loadCount('donations');
        $campaign->loadSum('donations', 'amount');

        return response()->json($campaign);
    }

    // â”€â”€ Store â€“ buat campaign baru (harus login) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'required|string',
            'category'    => 'required|string|max:100',
            'goal_amount' => 'required|numeric|min:10000',
            'deadline'    => 'required|date|after:today',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('campaigns', 'public');
        }

        $campaign = Campaign::create([
            'user_id'     => $request->user()->id,
            'title'       => $request->title,
            'slug'        => Str::slug($request->title) . '-' . Str::random(6),
            'description' => $request->description,
            'category'    => $request->category,
            'goal_amount' => $request->goal_amount,
            'deadline'    => $request->deadline,
            'image'       => $imagePath,
            'status'      => 'pending', // butuh review admin dulu
        ]);

        return response()->json([
            'message'  => 'Kampanye berhasil dibuat, menunggu review admin.',
            'campaign' => $campaign,
        ], 201);
    }

    // â”€â”€ Update â€“ edit campaign milik sendiri â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function update(Request $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'category'    => 'sometimes|string|max:100',
            'goal_amount' => 'sometimes|numeric|min:10000',
            'deadline'    => 'sometimes|date|after:today',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        if ($request->hasFile('image')) {
            if ($campaign->image) Storage::disk('public')->delete($campaign->image);
            $campaign->image = $request->file('image')->store('campaigns', 'public');
        }

        $campaign->fill($request->only('title', 'description', 'category', 'goal_amount', 'deadline'));
        if ($request->has('title')) {
            $campaign->slug = Str::slug($request->title) . '-' . Str::random(6);
        }
        $campaign->save();

        return response()->json(['message' => 'Kampanye diperbarui.', 'campaign' => $campaign]);
    }

    // â”€â”€ Destroy â€“ hapus campaign milik sendiri â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function destroy(Campaign $campaign)
    {
        $this->authorize('delete', $campaign);

        if ($campaign->image) Storage::disk('public')->delete($campaign->image);
        $campaign->delete();

        return response()->json(['message' => 'Kampanye dihapus.']);
    }

    // â”€â”€ MyCampaigns â€“ campaign milik user login â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function myCampaigns(Request $request)
    {
        $campaigns = Campaign::where('user_id', $request->user()->id)
            ->withSum('donations', 'amount')
            ->withCount('donations')
            ->latest()
            ->get();

        return response()->json($campaigns);
    }

    // â”€â”€ Categories â€“ daftar kategori unik â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function categories()
    {
        $cats = Campaign::select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return response()->json($cats);
    }
}
