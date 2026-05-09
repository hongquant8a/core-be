<?php

namespace App\Providers;

use App\Modules\Core\Models\User;
use App\Modules\Core\Observers\UserObserver;
use App\Modules\Core\Services\SettingService;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingVoteTopic;
use App\Modules\Meeting\Policies\MeetingPolicy;
use App\Modules\Meeting\Policies\MeetingVoteTopicPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Knuckles\Scribe\Scribe;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
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
        // Auto-create UserProfile mỗi khi tạo User.
        User::observe(UserObserver::class);

        // Register policies cho in-meeting control actions (Spatie permission vẫn giữ cho catalog/CRUD).
        Gate::policy(Meeting::class, MeetingPolicy::class);
        Gate::policy(MeetingVoteTopic::class, MeetingVoteTopicPolicy::class);

        // Super Admin bypass tất cả policy — return non-null từ Gate::before.
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('Super Admin')) {
                return true;
            }

            return null; // Không quyết định, để policy xử lý.
        });

        // Giữ nguyên header Excel khi import (không lowercase/snake_case).
        // Cho phép import dùng header tiếng Việt giống hệt template export.
        // Mỗi Import class tự dịch label → field key trong prepareForValidation.
        HeadingRowFormatter::default('none');

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
