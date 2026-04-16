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
     Schema::create('withdrawals', function (Blueprint $table) {
    $table->id('id_withdraw');

    $table->foreignId('id_campaign')->constrained('campaigns','id_campaign')->cascadeOnDelete();

    $table->bigInteger('jumlah');

    $table->enum('status', ['pending','approved','rejected'])->default('pending');

    $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawal');
    }
};
