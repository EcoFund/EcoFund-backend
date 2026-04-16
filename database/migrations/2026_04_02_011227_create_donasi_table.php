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
     Schema::create('donasi', function (Blueprint $table) {
    $table->id('id_donasi');

    $table->foreignId('id_campaign')->constrained('campaigns','id_campaign')->cascadeOnDelete();

    $table->string('nama_donatur');
    $table->string('email');
    $table->string('no_hp', 20)->nullable();

    $table->bigInteger('jumlah');
    $table->boolean('is_anonymous')->default(false);

    $table->enum('status', ['pending','berhasil','gagal'])->default('pending');

    $table->text('pesan')->nullable();

    $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donasi');
    }
};
