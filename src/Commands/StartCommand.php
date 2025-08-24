<?php

declare(strict_types=1);

namespace BinarCode\RestifyBoost\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('restify-boost:start', 'Starts Restify Boost (usually from mcp.json)')]
class StartCommand extends Command
{
    public function handle(): int
    {
        return Artisan::call('mcp:start laravel-restify');
    }
}
