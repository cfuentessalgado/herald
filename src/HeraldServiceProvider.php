<?php

namespace Assetplan\Herald;

use Assetplan\Herald\Commands\HeraldWorkCommand;
use Illuminate\Support\ServiceProvider;

class HeraldServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/herald.php',
            'herald'
        );

        $this->app->singleton(HeraldManager::class, function ($app) {
            return new HeraldManager($app['config']['herald']);
        });

        $this->app->alias(HeraldManager::class, 'herald');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish config (optional - most users won't need this)
            $this->publishes([
                __DIR__.'/../config/herald.php' => config_path('herald.php'),
            ], 'herald-config');

            // Register console commands
            $this->commands([
                HeraldWorkCommand::class,
                Commands\HeraldListCommand::class,
                Commands\HeraldListenCommand::class,
                Commands\InstallCommand::class,
            ]);
        }
    }
}
