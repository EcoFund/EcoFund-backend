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
       Schema::create('campaign_updates', function (Blueprint $table) {
    $table->id('id_update');

    $table->foreignId('id_campaign')->constrained('campaigns','id_campaign')->cascadeOnDelete();

    $table->string('judul');
    $table->text('deskripsi');

    $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns_updates');
    }
};
