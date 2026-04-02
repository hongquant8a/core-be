<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Knuckles\Scribe\Scribe;

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
    }
}
