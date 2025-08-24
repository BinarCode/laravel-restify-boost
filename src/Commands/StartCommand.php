<?php

declare(strict_types=1);

namespace BinarCode\LaravelRestifyMcp\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('restify-mcp:start', 'Starts Laravel Restify (usually from mcp.json)')]
class StartCommand extends Command
{
    public function handle(): int
    {
        return Artisan::call('mcp:start laravel-restify');
    }
}
