<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nik', 16)->nullable()->after('no_hp');
            $table->string('foto_ktp')->nullable()->after('nik');
            $table->enum('status_verifikasi', ['belum_diverifikasi', 'terverifikasi', 'ditolak'])
                ->default('belum_diverifikasi')
                ->after('foto_ktp');
            $table->text('catatan_verifikasi')->nullable()->after('status_verifikasi');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nik', 'foto_ktp', 'status_verifikasi', 'catatan_verifikasi']);
        });
    }
};
