<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jaminan level-DB: maksimal SATU payment attempt yang masih "open"
 * (creating/pending) per order.
 *
 * Kolom generated `active_guard` bernilai order_id selama attempt open, dan NULL
 * saat tidak. MySQL memperlakukan NULL sebagai unik secara terpisah, sehingga
 * banyak attempt non-open boleh ada, tetapi hanya satu yang open per order.
 *
 * Sengaja VIRTUAL (bukan STORED): MySQL melarang kolom generated STORED merujuk
 * kolom basis (order_id) yang punya foreign key ON DELETE CASCADE (error 1215).
 * Unique index pada kolom virtual tetap didukung MySQL 8.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->unsignedBigInteger('active_guard')
                ->nullable()
                ->virtualAs("CASE WHEN `status` IN ('creating', 'pending') THEN `order_id` END");

            $table->unique('active_guard', 'payment_attempts_active_guard_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->dropUnique('payment_attempts_active_guard_unique');
            $table->dropColumn('active_guard');
        });
    }
};
