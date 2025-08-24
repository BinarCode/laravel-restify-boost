<?php

declare(strict_types=1);

namespace BinarCode\LaravelRestifyMcp\Contracts;

interface McpClient
{
    public function mcpClientName(): string;

    public function getPhpPath(): string;

    public function getArtisanPath(): string;

    /**
     * Install MCP server configuration.
     *
     * @param  array<int, string>  $args
     * @param  array<string, string>  $env
     */
    public function installMcp(string $key, string $command, array $args = [], array $env = []): bool;
}
