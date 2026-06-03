<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            // Tambahkan kolom payment_method jika belum ada
            if (!Schema::hasColumn('campaigns', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('gambar');
            }
            
            // Tambahkan kolom bank_account_number
            if (!Schema::hasColumn('campaigns', 'bank_account_number')) {
                $table->string('bank_account_number')->nullable()->after('payment_method');
            }
            
            // Tambahkan kolom bank_account_name
            if (!Schema::hasColumn('campaigns', 'bank_account_name')) {
                $table->string('bank_account_name')->nullable()->after('bank_account_number');
            }
            
            // Tambahkan kolom phone_number
            if (!Schema::hasColumn('campaigns', 'phone_number')) {
                $table->string('phone_number')->nullable()->after('bank_account_name');
            }
            
            // Tambahkan kolom qris_image
            if (!Schema::hasColumn('campaigns', 'qris_image')) {
                $table->string('qris_image')->nullable()->after('phone_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method',
                'bank_account_number',
                'bank_account_name',
                'phone_number',
                'qris_image'
            ]);
        });
    }
};