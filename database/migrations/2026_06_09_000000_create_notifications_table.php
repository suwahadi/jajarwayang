<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel notifikasi bawaan Laravel (channel database) — mengaktifkan trait
 * Notifiable yang sudah ada di model User untuk fitur lonceng in-web.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');            // FQCN kelas App\Notifications\*
            $table->morphs('notifiable');      // notifiable_type + notifiable_id (+index)
            $table->text('data');              // payload JSON dari toDatabase()
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Query badge "belum dibaca milik user X" agar tetap cepat saat tabel membesar.
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
