<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
   Schema::create('campaigns', function (Blueprint $table) {
    $table->id('id_campaign');

    $table->foreignId('id_user')->constrained('users','id_user')->cascadeOnDelete();
    $table->foreignId('kategori_id')->constrained('kategori','id_kategori')->cascadeOnDelete();

    $table->string('judul');
    $table->string('slug')->unique();
    $table->text('deskripsi');

    $table->bigInteger('target_donasi');
    $table->bigInteger('dana_terkumpul')->default(0);

    $table->string('lokasi')->nullable();
    $table->string('gambar')->nullable();

    $table->enum('status', ['pending','aktif','selesai','ditolak'])->default('pending');

    $table->date('tanggal_mulai')->nullable();
    $table->date('tanggal_selesai')->nullable();

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
