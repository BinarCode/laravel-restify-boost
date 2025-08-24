<?php

declare(strict_types=1);

namespace BinarCode\RestifyBoost\Install\CodeEnvironment;

use BinarCode\RestifyBoost\Contracts\McpClient;

class VSCode extends CodeEnvironment implements McpClient
{
    public bool $useAbsolutePathForMcp = true;

    public function name(): string
    {
        return 'vscode';
    }

    public function displayName(): string
    {
        return 'Visual Studio Code';
    }

    public function mcpClientName(): string
    {
        return $this->displayName();
    }

    protected function getSystemPaths(string $platform): array
    {
        return match ($platform) {
            'mac' => [
                '/Applications/Visual Studio Code.app',
                '/System/Applications/Visual Studio Code.app',
                '/Users/'.get_current_user().'/Applications/Visual Studio Code.app',
            ],
            'windows' => [
                'C:\\Users\\'.get_current_user().'\\AppData\\Local\\Programs\\Microsoft VS Code\\Code.exe',
                'C:\\Program Files\\Microsoft VS Code\\Code.exe',
            ],
            'linux' => [
                '/usr/bin/code',
                '/usr/local/bin/code',
                '/opt/visual-studio-code/code',
            ],
            default => [],
        };
    }

    protected function getProjectFiles(): array
    {
        return [
            '.vscode/settings.json',
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
            'mac' => $_SERVER['HOME'].'/Library/Application Support/Code/User/globalStorage/rooveterinaryinc.roo-cline/settings/cline_mcp_settings.json',
            'windows' => $_SERVER['APPDATA'].'\\Code\\User\\globalStorage\\rooveterinaryinc.roo-cline\\settings\\cline_mcp_settings.json',
            'linux' => $_SERVER['HOME'].'/.config/Code/User/globalStorage/rooveterinaryinc.roo-cline/settings/cline_mcp_settings.json',
            default => null,
        };
    }
}
