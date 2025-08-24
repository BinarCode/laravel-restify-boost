<?php

declare(strict_types=1);

namespace BinarCode\LaravelRestifyMcp;

use BinarCode\LaravelRestifyMcp\Commands\ExecuteToolCommand;
use BinarCode\LaravelRestifyMcp\Commands\InstallCommand;
use BinarCode\LaravelRestifyMcp\Commands\StartCommand;
use BinarCode\LaravelRestifyMcp\Mcp\RestifyDocs;
use BinarCode\LaravelRestifyMcp\Services\DocCache;
use BinarCode\LaravelRestifyMcp\Services\DocIndexer;
use BinarCode\LaravelRestifyMcp\Services\DocParser;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Server\Facades\Mcp;

class LaravelRestifyMcpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/restify-mcp.php',
            'restify-mcp'
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

        Mcp::local('laravel-restify', RestifyDocs::class);

        $this->registerPublishing();
        $this->registerCommands();
    }

    private function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/restify-mcp.php' => config_path('restify-mcp.php'),
            ], 'restify-mcp-config');
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
        if (! config('restify-mcp.enabled', true)) {
            return false;
        }

        if (app()->runningUnitTests()) {
            return false;
        }

        return true;
    }
}
