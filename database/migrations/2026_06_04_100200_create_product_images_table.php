<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Galeri produk: maks 6 gambar/produk, satu ditandai main thumbnail.
        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('path')->comment('Path relatif disk public, mis. products/abc123.webp');
            $table->boolean('is_main')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // variants (100003) sudah ada; tambahkan FK setelah product_images dibuat.
        // Varian boleh opsional mereferensikan satu gambar produk sebagai thumbnail-nya.
        Schema::table('variants', function (Blueprint $table): void {
            $table->foreignId('image_id')->nullable()->after('weight')
                ->constrained('product_images')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('variants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('image_id');
        });

        Schema::dropIfExists('product_images');
    }
};
