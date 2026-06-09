<?php

namespace App\Providers;

use App\Models\User;
use App\Services\WishlistService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Palet warna email transaksional dibagikan ke SEMUA view emails.* —
        // termasuk @section & partial. Tidak bisa hanya didefinisikan di layout:
        // isi @section dibuffer sebelum layout induk dijalankan, sehingga variabel
        // @php milik layout tidak terlihat di dalam section/partial.
        View::composer('emails.*', function ($view): void {
            $view->with([
                'accent' => '#d97706', // amber-600, selaras storefront
                'ink' => '#0f172a',    // slate-900
                'muted' => '#64748b',  // slate-500
                'line' => '#e2e8f0',   // slate-200
            ]);
        });

        // Saat user login, gabungkan favorit yang dibuat sebagai tamu (session)
        // ke wishlist persisten miliknya, lalu bersihkan session.
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                app(WishlistService::class)->migrateGuestToUser($event->user);
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Lokalisasi tanggal ke Bahasa Indonesia (PRD §4.1).
        Carbon::setLocale('id');
        CarbonImmutable::setLocale('id');

        // Deteksi dini bug data di luar produksi (lazy loading, atribut hilang).
        Model::shouldBeStrict(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
