<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            // Label kuratif opsional untuk kartu produk: 'new' (Baru) atau 'hot' (Terlaris).
            // Badge "Diskon" diturunkan otomatis dari harga promo, jadi tidak disimpan di sini.
            $table->string('badge', 20)->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('badge');
        });
    }
};
