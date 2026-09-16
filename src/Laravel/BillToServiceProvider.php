<?php

declare(strict_types=1);

namespace BillTo\Laravel;

use BillTo\BillTo;
use BillTo\Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Optional Laravel integration. Auto-discovered via composer `extra.laravel`.
 *
 * Configuration (config/billto.php, publish with `vendor:publish --tag=billto-config`):
 * `token`, `base_url`, `max_retries`, `auto_idempotency`.
 */
final class BillToServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/billto.php', 'billto');

        $this->app->singleton(BillTo::class, static function (Application $app): BillTo {
            /** @var array{token: string|null, base_url: string|null, max_retries: int|null, auto_idempotency: bool|null} $config */
            $config = $app->make('config')->get('billto', []);

            $sdkConfig = new Config(
                token: (string) ($config['token'] ?? ''),
                baseUrl: $config['base_url'] ?? Config::DEFAULT_BASE_URL,
                maxRetries: (int) ($config['max_retries'] ?? 2),
                autoIdempotency: (bool) ($config['auto_idempotency'] ?? true),
            );

            return BillTo::fromConfig($sdkConfig);
        });

        $this->app->alias(BillTo::class, 'billto');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/config/billto.php' => $this->app->configPath('billto.php')], 'billto-config');
        }
    }
}
