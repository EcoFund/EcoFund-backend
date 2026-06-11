<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->string('nama_bank')->after('jumlah');
            $table->string('nomor_rekening')->after('nama_bank');
            $table->string('atas_nama')->after('nomor_rekening');
            $table->text('catatan_admin')->nullable()->after('atas_nama');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn(['nama_bank', 'nomor_rekening', 'atas_nama', 'catatan_admin']);
        });
    }
};
