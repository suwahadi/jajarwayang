<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Slide extends Model
{
    /** Batas slide aktif yang boleh tampil di hero beranda. */
    public const MAX_ACTIVE = 6;

    protected $fillable = [
        'title',
        'image',
        'content',
        'button_label',
        'url',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Bersihkan file webp fisik saat row dihapus.
        static::deleting(function (Slide $slide): void {
            delete_webp($slide->image);
        });
    }

    /**
     * URL publik gambar (disk 'public' eksplisit).
     *
     * Catatan: dinamai `image_url` (bukan `url`) agar tidak menabrak kolom
     * `url` yang menyimpan tautan tombol slide.
     *
     * @return Attribute<string, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(
            fn (): string => Storage::disk('public')->url($this->image),
        );
    }

    /**
     * @param  Builder<Slide>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
