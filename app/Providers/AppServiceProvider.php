<?php

namespace App\Providers;

use App\Support\SchoolSettings;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SchoolSettings::class);
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        Paginator::defaultView('partials.pagination');
        Paginator::defaultSimpleView('partials.pagination');

        // Coba-coba kata sandi dibatasi per username+IP, dan per username dari mana pun.
        RateLimiter::for('login', function (Request $request) {
            $username = mb_strtolower(trim((string) $request->input('username')));

            return [
                Limit::perMinute(5)->by('login:'.$username.'|'.$request->ip()),
                Limit::perMinute(20)->by('login-user:'.$username),
            ];
        });

        // Tombol presensi & pengajuan izin: cukup longgar untuk mencoba ulang saat GPS belum akurat.
        RateLimiter::for('presence', fn (Request $request) => Limit::perMinute(12)
            ->by('presence:'.$request->user()?->id));

        // Nama sekolah & pengaturan tersedia di semua tampilan (dibaca saat dipakai saja).
        View::composer('*', function ($view) {
            $view->with('schoolSettings', $this->app->make(SchoolSettings::class));
        });
    }
}
