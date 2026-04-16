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
    Schema::create('campaign_images', function (Blueprint $table) {
    $table->id('id_image');

    $table->foreignId('id_campaign')->constrained('campaigns','id_campaign')->cascadeOnDelete();

    $table->string('image_url');

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns_images');
    }
};
