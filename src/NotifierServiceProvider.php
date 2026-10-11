<?php

namespace Usamamuneerchaudhary\Notifier;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Usamamuneerchaudhary\Notifier\Commands\CleanupAnalyticsCommand;
use Usamamuneerchaudhary\Notifier\Commands\NotifierInstallCommand;
use Usamamuneerchaudhary\Notifier\Commands\SendTestNotificationCommand;
use Usamamuneerchaudhary\Notifier\Http\Controllers\NotificationPreferenceController;
use Usamamuneerchaudhary\Notifier\Http\Controllers\NotificationTrackingController;
use Usamamuneerchaudhary\Notifier\Http\Controllers\NotificationWebhookController;
use Usamamuneerchaudhary\Notifier\Http\Controllers\UnsubscribeController;
use Usamamuneerchaudhary\Notifier\Livewire\NotificationPreferencesPanel;
use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Models\NotificationChannel;
use Usamamuneerchaudhary\Notifier\Models\NotificationEvent;
use Usamamuneerchaudhary\Notifier\Models\NotificationPreference;
use Usamamuneerchaudhary\Notifier\Models\NotificationTemplate;
use Usamamuneerchaudhary\Notifier\Services\AnalyticsService;
use Usamamuneerchaudhary\Notifier\Services\ChannelDriverFactory;
use Usamamuneerchaudhary\Notifier\Services\DeliveryStatusService;
use Usamamuneerchaudhary\Notifier\Services\NotificationRepository;
use Usamamuneerchaudhary\Notifier\Services\NotifierManager;
use Usamamuneerchaudhary\Notifier\Services\PreferenceService;
use Usamamuneerchaudhary\Notifier\Services\UnsubscribeService;
use Usamamuneerchaudhary\Notifier\Services\UrlTrackingService;
use Usamamuneerchaudhary\Notifier\Services\Webhooks\WebhookRegistry;

class NotifierServiceProvider extends PackageServiceProvider
{
    public static string $name = 'notifier';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations([
                '2025_02_19_154211_create_notification_channels_table',
                '2025_02_19_154225_create_notification_events_table',
                '2025_02_19_154251_create_notification_templates',
                '2025_02_19_154304_create_notification_preferences',
                '2025_02_19_154314_create_notifications',
                '2025_02_20_000000_add_settings_to_notification_events_table',
                '2025_02_23_104342_create_notification_settings_table',
                '2025_02_24_000000_add_analytics_to_notifications',
                '2026_10_11_000001_add_tracking_token_to_notifications',
                '2026_10_11_000002_add_delivery_columns_to_notifications',
            ])
            ->hasCommands([
                NotifierInstallCommand::class,
                SendTestNotificationCommand::class,
                CleanupAnalyticsCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton('notifier', function () {
            return new NotifierManager;
        });
        $this->app->alias('notifier', NotifierManager::class);

        $this->app->bind('notifier.channel', NotificationChannel::class);
        $this->app->bind('notifier.event', NotificationEvent::class);
        $this->app->bind('notifier.template', NotificationTemplate::class);
        $this->app->bind('notifier.preference', NotificationPreference::class);
        $this->app->bind('notifier.notification', Notification::class);

        $this->app->singleton(PreferenceService::class);
        $this->app->singleton(AnalyticsService::class);
        $this->app->singleton(UrlTrackingService::class);
        $this->app->singleton(NotificationRepository::class);
        $this->app->singleton(UnsubscribeService::class);
        $this->app->singleton(DeliveryStatusService::class);
        $this->app->singleton(WebhookRegistry::class);
    }

    public function packageBooted(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'notifier');

        $this->registerChannelsFromDatabase();
        $this->registerApiRoutes();
        $this->registerTrackingRoutes();
        $this->registerUnsubscribeRoutes();
        $this->registerWebhookRoutes();
        $this->registerLivewireComponents();
    }

    /**
     * Register API routes for user preferences
     */
    protected function registerApiRoutes(): void
    {
        if (! config('notifier.api.enabled', true)) {
            return;
        }

        Route::prefix(trim((string) config('notifier.api.prefix', 'api/notifier/preferences'), '/'))
            ->middleware($this->apiMiddleware())
            ->name('notifier.api.preferences.')
            ->group(function () {
                Route::get('/', [NotificationPreferenceController::class, 'index'])->name('index');
                Route::get('/available', [NotificationPreferenceController::class, 'available'])->name('available');
                Route::get('/{eventKey}', [NotificationPreferenceController::class, 'show'])->name('show');
                Route::put('/{eventKey}', [NotificationPreferenceController::class, 'update'])->name('update');
            });
    }

    /**
     * Register tracking routes for analytics ---- public routes
     */
    protected function registerTrackingRoutes(): void
    {
        Route::prefix('notifier/track')->name('notifier.track.')->group(function () {
            Route::get('/open/{token}', [NotificationTrackingController::class, 'trackOpen'])->name('open');
            Route::get('/click/{token}', [NotificationTrackingController::class, 'trackClick'])->name('click');
        });
    }

    /**
     * Signed one-click unsubscribe links. GET confirms, POST unsubscribes (RFC 8058).
     */
    protected function registerUnsubscribeRoutes(): void
    {
        Route::get('notifier/unsubscribe/{user}', [UnsubscribeController::class, 'show'])->name('notifier.unsubscribe');
        Route::post('notifier/unsubscribe/{user}', [UnsubscribeController::class, 'store']);
    }

    /**
     * Inbound provider delivery webhooks (no CSRF or session middleware; each handler verifies its own signature).
     */
    protected function registerWebhookRoutes(): void
    {
        if (! config('notifier.webhooks.enabled', true)) {
            return;
        }

        Route::prefix(trim((string) config('notifier.webhooks.prefix', 'notifier/webhooks'), '/'))
            ->middleware((array) config('notifier.webhooks.middleware', []))
            ->group(function () {
                Route::post('/{provider}', [NotificationWebhookController::class, 'handle'])->name('notifier.webhooks');
            });
    }

    protected function registerLivewireComponents(): void
    {
        if ($this->app->providerIsLoaded(LivewireServiceProvider::class)) {
            Livewire::component('notifier-preferences', NotificationPreferencesPanel::class);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function apiMiddleware(): array
    {
        $configured = config('notifier.api.middleware');

        if (is_array($configured) && $configured !== []) {
            return $configured;
        }

        return class_exists(Sanctum::class)
            ? ['api', 'auth:sanctum']
            : ['web', 'auth'];
    }

    protected function registerChannelsFromDatabase(): void
    {
        try {
            if (! Schema::hasTable('notifier_channels')) {
                return;
            }

            $notifier = $this->app->make('notifier');
            $channels = NotificationChannel::where('is_active', true)->get();

            $driverFactory = new ChannelDriverFactory;
            foreach ($channels as $channel) {
                $driver = $driverFactory->create($channel->type);
                if ($driver) {
                    $notifier->registerChannel($channel->type, $driver);
                }
            }
        } catch (\Exception $e) {
            Log::info($e->getMessage());
            // Silently fail
        }
    }
}
