<?php

namespace App\Providers;

use App\Support\SchoolSettings;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
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

        // URL berbahasa Indonesia: /siswa/tambah, /siswa/1/ubah.
        Route::resourceVerbs(['create' => 'tambah', 'edit' => 'ubah']);

        Paginator::defaultView('partials.pagination');
        Paginator::defaultSimpleView('partials.pagination');

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('device', fn (Request $request) => Limit::perMinute(config('presensi.device_rate_limit', 120))
            ->by('device:'.($request->attributes->get('device')?->id ?? $request->ip())));

        RateLimiter::for('tap', fn (Request $request) => Limit::perMinute(120)
            ->by('tap:'.$request->user()?->id.'|'.$request->ip()));

        // Nama sekolah & pengaturan tersedia di semua tampilan (dibaca saat dipakai saja).
        View::composer('*', function ($view) {
            $view->with('schoolSettings', $this->app->make(SchoolSettings::class));
        });
    }
}
