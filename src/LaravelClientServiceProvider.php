<?php

namespace Vaharadev\LaravelClient;

use Illuminate\Support\ServiceProvider;

class LaravelClientServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laravel-client.php' => config_path('laravel-client.php'),
            ], 'laravel-client-config');

            // Export the migrations
            if (!class_exists('CreateVaharaItemsTable')) {
                $this->publishes([
                    __DIR__ . '/../database/migrations/create_vahara_items_table.php.stub' => database_path('migrations/' . date('Y_m_d_His', time()) . '_create_vahara_items_table.php'),
                    __DIR__ . '/../database/migrations/create_vahara_item_pivot_table.php.stub' => database_path('migrations/' . date('Y_m_d_His', time()) . '_create_vahara_item_pivot_table.php'),
                ], 'laravel-client-migrations');
            }
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laravel-client');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-client.php', 'laravel-client');
    }
}
