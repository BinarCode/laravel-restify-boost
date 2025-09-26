<?php

declare(strict_types=1);

namespace BinarCode\RestifyBoost;

use BinarCode\RestifyBoost\Commands\ExecuteToolCommand;
use BinarCode\RestifyBoost\Commands\InstallCommand;
use BinarCode\RestifyBoost\Commands\StartCommand;
use BinarCode\RestifyBoost\Mcp\RestifyDocs;
use BinarCode\RestifyBoost\Services\DocCache;
use BinarCode\RestifyBoost\Services\DocIndexer;
use BinarCode\RestifyBoost\Services\DocParser;
use Illuminate\Support\ServiceProvider;

class RestifyBoostServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/restify-boost.php',
            'restify-boost'
        );

        if (! $this->shouldRun()) {
            return;
        }

        // Register core services
        $this->app->singleton(DocCache::class);
        $this->app->singleton(DocParser::class);
        $this->app->singleton(DocIndexer::class);
        $this->app->singleton(RestifyDocs::class);
    }

    public function boot(): void
    {
        if (! $this->shouldRun()) {
            return;
        }

        // TODO: Update MCP server registration for Laravel MCP 0.2
        // Mcp::local('laravel-restify', RestifyDocs::class);

        $this->registerPublishing();
        $this->registerCommands();
    }

    private function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/restify-boost.php' => config_path('restify-boost.php'),
            ], 'restify-boost-config');
        }
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                StartCommand::class,
                ExecuteToolCommand::class,
            ]);
        }
    }

    private function shouldRun(): bool
    {
        if (! config('restify-boost.enabled', true)) {
            return false;
        }

        if (app()->runningUnitTests()) {
            return false;
        }

        return true;
    }
}
