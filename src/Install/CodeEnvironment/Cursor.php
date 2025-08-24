<?php

declare(strict_types=1);

namespace BinarCode\RestifyBoost\Install\CodeEnvironment;

use BinarCode\RestifyBoost\Contracts\McpClient;

class Cursor extends CodeEnvironment implements McpClient
{
    public bool $useAbsolutePathForMcp = true;

    public function name(): string
    {
        return 'cursor';
    }

    public function displayName(): string
    {
        return 'Cursor';
    }

    public function mcpClientName(): string
    {
        return $this->displayName();
    }

    protected function getSystemPaths(string $platform): array
    {
        return match ($platform) {
            'mac' => [
                '/Applications/Cursor.app',
                '/System/Applications/Cursor.app',
                '/Users/'.get_current_user().'/Applications/Cursor.app',
            ],
            'windows' => [
                'C:\\Users\\'.get_current_user().'\\AppData\\Local\\Programs\\cursor\\Cursor.exe',
                'C:\\Program Files\\Cursor\\Cursor.exe',
            ],
            'linux' => [
                '/usr/bin/cursor',
                '/usr/local/bin/cursor',
                '/opt/cursor/cursor',
            ],
            default => [],
        };
    }

    protected function getProjectFiles(): array
    {
        return [
            '.cursor/settings.json',
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
            'mac' => $_SERVER['HOME'].'/Library/Application Support/Cursor/User/globalStorage/rooveterinaryinc.roo-cline/settings/cline_mcp_settings.json',
            'windows' => $_SERVER['APPDATA'].'\\Cursor\\User\\globalStorage\\rooveterinaryinc.roo-cline\\settings\\cline_mcp_settings.json',
            'linux' => $_SERVER['HOME'].'/.config/Cursor/User/globalStorage/rooveterinaryinc.roo-cline/settings/cline_mcp_settings.json',
            default => null,
        };
    }
}
