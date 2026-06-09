<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Akses konfigurasi situs dinamis (tabel `settings`) dengan caching (PRD §3.5).
 */
class SettingService
{
    private const CACHE_PREFIX = 'setting.';

    /**
     * Ambil nilai setting; di-cache selamanya hingga di-invalidasi via set()/forget().
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever(
            self::CACHE_PREFIX.$key,
            fn (): ?string => Setting::query()->where('key', $key)->value('value'),
        );

        return $value ?? $default;
    }

    /**
     * Simpan/perbarui nilai setting dan segarkan cache.
     */
    public static function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );

        Cache::forget(self::CACHE_PREFIX.$key);
        Cache::rememberForever(self::CACHE_PREFIX.$key, fn (): mixed => $value);
    }

    /**
     * Hapus cache satu key (dipanggil mis. setelah update massal di admin).
     */
    public static function forget(string $key): void
    {
        Cache::forget(self::CACHE_PREFIX.$key);
    }
}
