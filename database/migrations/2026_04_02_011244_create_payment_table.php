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
     Schema::create('pembayaran', function (Blueprint $table) {
    $table->id('id_pembayaran');

    $table->foreignId('id_donasi')->constrained('donasi','id_donasi')->cascadeOnDelete();

    $table->string('payment_gateway')->nullable();
    $table->string('transaction_id')->nullable();
    $table->string('snap_token')->nullable();

    $table->string('status')->nullable(); // dari gateway

    $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment');
    }
};
