<?php

namespace App\Providers;

use App\Services\Ocr\CompositeDocumentTextExtractor;
use App\Services\Ocr\DocumentTextExtractor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DocumentTextExtractor::class, CompositeDocumentTextExtractor::class);
    }

    public function boot(): void
    {
        if (
            ! $this->app->environment('local', 'testing')
            && str_starts_with((string) config('app.url'), 'https://')
        ) {
            URL::forceScheme('https');
        }

        RateLimiter::for('auth', function (Request $request) {
            $identifier = $request->filled('phone')
                ? $request->string('phone')->toString()
                : $request->string('email')->toString();

            return Limit::perMinute(5)->by(
                Str::transliterate(Str::lower($identifier)).'|'.$request->ip()
            );
        });

        RateLimiter::for('simulations', function (Request $request) {
            return Limit::perMinute(20)->by(($request->user()?->id ?? $request->ip()).'|simulations');
        });

        RateLimiter::for('passwords', function (Request $request) {
            return Limit::perMinute(5)->by(($request->user()?->id ?? $request->ip()).'|passwords');
        });
    }
}
