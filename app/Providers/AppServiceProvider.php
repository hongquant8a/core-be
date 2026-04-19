<?php

namespace App\Providers;

use App\Modules\Core\Services\SettingService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Knuckles\Scribe\Scribe;
use Throwable;

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
        Scribe::afterGenerating(function (array $paths) {
            if (! empty($paths['postman']) && file_exists($paths['postman'])) {
                $json = json_decode(file_get_contents($paths['postman']), true);
                file_put_contents(
                    $paths['postman'],
                    json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                );
            }
        });

        View::composer('emails.notification-layout', function ($view) {
            $logoPath = null;
            $appName = null;
            $copyright = null;

            try {
                $settings = app(SettingService::class);
                $logoPath = $settings->getByKey('logo')['value'] ?? null;
                $appName = $settings->getByKey('admin_app_name')['value'] ?? null;
                $copyright = $settings->getByKey('copyright')['value'] ?? null;
            } catch (Throwable) {
                // Setting service unavailable — render with fallbacks.
            }

            $appUrl = rtrim((string) config('app.url', ''), '/');
            $logoUrl = $logoPath ? $appUrl.$logoPath : null;

            $view->with([
                'logoUrl' => $logoUrl,
                'appName' => $appName ?: config('app.name', 'Hệ thống'),
                'copyright' => $copyright ?: null,
            ]);
        });
    }
}
