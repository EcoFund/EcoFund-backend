<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/auth/register',
        operationId: 'registerUser',
        summary: 'Registrasi user baru',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['nama', 'email', 'no_hp', 'password', 'password_confirmation'],
                    properties: [
                        new OA\Property(property: 'nama', type: 'string', example: 'Budi Santoso'),
                        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'budi@example.com'),
                        new OA\Property(property: 'no_hp', type: 'string', example: '081234567890'),
                        new OA\Property(property: 'password', type: 'string', format: 'password', example: 'secret123'),
                        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'secret123'),
                        new OA\Property(property: 'foto', type: 'string', format: 'binary'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Registrasi berhasil'),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ]
    )]
    // ── Register ──────────────────────────────────────────
    public function register(Request $request)
    {
        $request->validate([
            'nama'                  => 'required|string|max:255',
            'email'                 => 'required|email|unique:users,email',
            'no_hp'                 => 'required|string|max:15',
            'password'              => 'required|string|min:8|confirmed',
            'foto'                 => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        $photoPath = null;
        if ($request->hasFile('foto')) {
            $photoPath = $request->file('foto')->store('avatars', 'public');
        } else {
            $photoPath = $this->generateDefaultAvatar($request->nama);
        }

        $user = User::create([
            'nama'     => $request->nama,
            'email'    => $request->email,
            'no_hp'    => $request->no_hp,
            'password' => Hash::make($request->password),
            'role'     => 'campaigner',
            'foto'    => $photoPath,
        ]);

        $token = $user->createToken('ecofund-token')->plainTextToken;

        return response()->json([
            'message' => 'Registrasi berhasil.',
            'user'    => $user,
            'token'   => $token,
        ], 201);
    }

    // ── Login ─────────────────────────────────────────────
    #[OA\Post(
        path: '/auth/login',
        operationId: 'loginUser',
        summary: 'Login dengan email atau nomor HP',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['login', 'password'],
                properties: [
                    new OA\Property(property: 'login', type: 'string', example: '081234567890'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'secret123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login berhasil'),
            new OA\Response(response: 422, description: 'Login gagal'),
        ]
    )]
    public function login(Request $request)
    {
        $request->validate([
            'login'    => 'nullable|string',
            'email'    => 'nullable|string',
            'no_hp'    => 'nullable|string',
            'password' => 'required|string',
        ]);

        $identifier = $request->input('login')
            ?? $request->input('email')
            ?? $request->input('no_hp');

        if (! $identifier) {
            throw ValidationException::withMessages([
                'login' => ['Email atau nomor HP wajib diisi.'],
            ]);
        }

        $matches = User::query()
            ->where('email', $identifier)
            ->orWhere('no_hp', $identifier)
            ->get();

        if ($matches->isEmpty()) {
            throw ValidationException::withMessages([
                'login' => ['Akun tidak ditemukan.'],
            ]);
        }

        if ($matches->count() > 1) {
            throw ValidationException::withMessages([
                'login' => ['Login dengan nomor HP ambigu. Silakan gunakan email.'],
            ]);
        }

        $user = $matches->first();

        if (! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Email, nomor HP, atau password salah.'],
            ]);
        }

        Auth::login($user);

        $token = $user->createToken('ecofund-token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'user'    => $user,
            'token'   => $token,
        ]);
    }

    // ── Google OAuth ──────────────────────────────────────
    public function googleAuth(Request $request)
    {
        $request->validate([
            'credential' => 'required|string',
        ]);

        // Verifikasi ID token ke Google tokeninfo endpoint
        // withoutVerifying() hanya aktif di local (XAMPP Windows tidak punya CA bundle)
        $http = app()->isLocal()
            ? Http::withoutVerifying()
            : Http::withOptions([]);

        $response = $http->get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $request->credential,
        ]);

        if ($response->failed()) {
            return response()->json(['message' => 'Token Google tidak valid.'], 401);
        }

        $payload = $response->json();

        // Validasi audience (client_id) agar token bukan dari aplikasi lain
        $clientId = config('services.google.client_id');
        if ($clientId && ($payload['aud'] ?? '') !== $clientId) {
            return response()->json(['message' => 'Token Google tidak valid untuk aplikasi ini.'], 401);
        }

        $googleId = $payload['sub'] ?? null;
        $email    = $payload['email'] ?? null;
        $nama     = $payload['name'] ?? ($payload['given_name'] ?? 'EcoFund User');
        $foto     = $payload['picture'] ?? null;

        if (! $googleId || ! $email) {
            return response()->json(['message' => 'Data Google tidak lengkap.'], 422);
        }

        // Cari user berdasarkan google_id, lalu email, atau buat baru
        $user = User::where('google_id', $googleId)->first()
            ?? User::where('email', $email)->first();

        if ($user) {
            // Update google_id jika belum tersimpan
            if (! $user->google_id) {
                $user->update(['google_id' => $googleId]);
            }
        } else {
            // Buat user baru
            $user = User::create([
                'nama'      => $nama,
                'email'     => $email,
                'google_id' => $googleId,
                'foto'      => $foto,          // URL foto Google (bukan path lokal)
                'role'      => 'campaigner',
                'password'  => null,
            ]);
        }

        Auth::login($user);
        $token = $user->createToken('ecofund-google')->plainTextToken;

        return response()->json([
            'message' => 'Login dengan Google berhasil.',
            'user'    => $user,
            'token'   => $token,
        ]);
    }

    // ── Logout ────────────────────────────────────────────
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    // ── Me (profil saat ini) ──────────────────────────────
    public function me(Request $request)
    {
        return response()->json($request->user()->load('campaigns'));
    }

    // ── Update profil ─────────────────────────────────────
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'nama'  => 'sometimes|string|max:255',
            'no_hp' => 'sometimes|string|max:15',
            'foto' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        if ($request->hasFile('foto')) {
            if ($user->foto) Storage::disk('public')->delete($user->foto);
            $user->foto = $request->file('foto')->store('avatars', 'public');
        }

        $user->fill($request->only('nama', 'no_hp'))->save();

        return response()->json(['message' => 'Profil diperbarui.', 'user' => $user]);
    }

    private function generateDefaultAvatar(string $name): string
    {
        $initials = $this->extractInitials($name);
        $background = $this->colorFromName($name);
        $filename = 'avatars/default-'.Str::uuid().'.svg';

        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 256 256" role="img" aria-label="Avatar {$initials}">
        <rect width="256" height="256" fill="{$background}" rx="48" />
        <text x="50%" y="50%" dy=".1em" text-anchor="middle" fill="#FFFFFF" font-family="Arial, Helvetica, sans-serif" font-size="96" font-weight="700">{$initials}</text>
        </svg>
        SVG;

        Storage::disk('public')->put($filename, $svg);

        return $filename;
    }

    private function extractInitials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return 'U';
        }

        $initials = collect($parts)
            ->take(2)
            ->map(fn (string $part) => Str::upper(mb_substr($part, 0, 1)))
            ->implode('');

        return $initials ?: 'U';
    }

    private function colorFromName(string $name): string
    {
        $palette = [
            '#0F766E',
            '#1D4ED8',
            '#7C3AED',
            '#BE185D',
            '#C2410C',
            '#15803D',
            '#B45309',
            '#334155',
        ];

        return $palette[crc32(mb_strtolower($name)) % count($palette)];
    }
}
