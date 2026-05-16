<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // ✅ TAMBAH INI

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable; // ✅ TAMBAH DI SINI

    protected $primaryKey = 'id_user';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'nama',
        'email',
        'no_hp',
        'foto',
        'password',
        'role',
        'google_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }
}
