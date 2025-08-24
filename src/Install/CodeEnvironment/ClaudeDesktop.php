<?php

declare(strict_types=1);

namespace BinarCode\LaravelRestifyMcp\Install\CodeEnvironment;

use BinarCode\LaravelRestifyMcp\Contracts\McpClient;

class ClaudeDesktop extends CodeEnvironment implements McpClient
{
    public bool $useAbsolutePathForMcp = true;

    public function name(): string
    {
        return 'claudedesktop';
    }

    public function displayName(): string
    {
        return 'Claude Desktop';
    }

    public function mcpClientName(): string
    {
        return $this->displayName();
    }

    protected function getSystemPaths(string $platform): array
    {
        return match ($platform) {
            'mac' => [
                '/Applications/Claude.app',
                '/System/Applications/Claude.app',
                '/Users/'.get_current_user().'/Applications/Claude.app',
            ],
            'windows' => [
                'C:\\Users\\'.get_current_user().'\\AppData\\Local\\Programs\\claude\\Claude.exe',
                'C:\\Program Files\\Claude\\Claude.exe',
            ],
            'linux' => [
                '/usr/bin/claude',
                '/usr/local/bin/claude',
                '/opt/claude/claude',
            ],
            default => [],
        };
    }

    protected function getProjectFiles(): array
    {
        return [
            '.claude_desktop_config.json',
        ];
    }

    public function mcpConfigPath(): ?string
    {
        $platform = match (PHP_OS_FAMILY) {
            'Darwin' => 'mac',
            'Linux' => 'linux',  
            'Windows' => 'windows',
            default => null,
        };

        return match ($platform) {
            'mac' => $_SERVER['HOME'].'/.config/claude/claude_desktop_config.json',
            'windows' => $_SERVER['APPDATA'].'\\Claude\\claude_desktop_config.json',
            'linux' => $_SERVER['HOME'].'/.config/claude/claude_desktop_config.json',
            default => null,
        };
    }
}