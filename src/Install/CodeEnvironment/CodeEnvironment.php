<?php

declare(strict_types=1);

namespace BinarCode\LaravelRestifyMcp\Install\CodeEnvironment;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;

abstract class CodeEnvironment
{
    public bool $useAbsolutePathForMcp = false;

    abstract public function name(): string;

    abstract public function displayName(): string;

    public function useAbsolutePathForMcp(): bool
    {
        return $this->useAbsolutePathForMcp;
    }

    public function getPhpPath(): string
    {
        return $this->useAbsolutePathForMcp() ? PHP_BINARY : 'php';
    }

    public function getArtisanPath(): string
    {
        return $this->useAbsolutePathForMcp() ? base_path('artisan') : './artisan';
    }

    public function detectOnSystem(string $platform): bool
    {
        $paths = $this->getSystemPaths($platform);
        
        foreach ($paths as $path) {
            if (file_exists($path) || is_dir($path)) {
                return true;
            }
        }

        return false;
    }

    public function detectInProject(string $basePath): bool
    {
        $files = $this->getProjectFiles();
        
        foreach ($files as $file) {
            if (file_exists($basePath.'/'.$file)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get system paths to check for the application.
     *
     * @return array<string>
     */
    abstract protected function getSystemPaths(string $platform): array;

    /**
     * Get project files to check for the application.
     *
     * @return array<string>
     */
    abstract protected function getProjectFiles(): array;

    public function mcpConfigPath(): ?string
    {
        return null;
    }

    /**
     * Install MCP server using file-based configuration strategy.
     *
     * @param array<int, string> $args
     * @param array<string, string> $env
     *
     * @throws FileNotFoundException
     */
    public function installMcp(string $key, string $command, array $args = [], array $env = []): bool
    {
        $path = $this->mcpConfigPath();
        if (! $path) {
            return false;
        }

        File::ensureDirectoryExists(dirname($path));

        $config = File::exists($path)
            ? json_decode(File::get($path), true) ?: []
            : [];

        $mcpKey = $this->mcpConfigKey();
        data_set($config, "{$mcpKey}.{$key}", collect([
            'command' => $command,
            'args' => $args,
            'env' => $env,
        ])->filter()->toArray());

        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return $json && File::put($path, $json);
    }

    protected function mcpConfigKey(): string
    {
        return 'mcpServers';
    }
}