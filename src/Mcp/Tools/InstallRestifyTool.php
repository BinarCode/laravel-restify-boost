<?php

declare(strict_types=1);

namespace BinarCode\RestifyBoost\Mcp\Tools;

use Generator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

class InstallRestifyTool extends Tool
{
    private const LATEST_CONFIG_URL = 'https://raw.githubusercontent.com/BinarCode/laravel-restify/refs/heads/10.x/config/restify.php';

    public function description(): string
    {
        return 'Install and setup Laravel Restify package with all necessary configurations. This tool handles composer installation, downloads the latest config file from Laravel Restify 10.x, runs setup commands, creates the Restify service provider, scaffolds repositories, and optionally configures authentication middleware and generates mock data.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema
            ->boolean('run_migrations')
            ->description('Run migrations after setup (default: true)')
            ->optional()
            ->boolean('enable_sanctum_auth')
            ->description('Enable Sanctum authentication middleware in restify config')
            ->optional()
            ->string('api_prefix')
            ->description('Custom API prefix (default: /api/restify)')
            ->optional()
            ->boolean('install_doctrine_dbal')
            ->description('Install doctrine/dbal for mock data generation')
            ->optional()
            ->integer('generate_users_count')
            ->description('Number of mock users to generate (requires doctrine/dbal)')
            ->optional()
            ->boolean('generate_repositories')
            ->description('Auto-generate repositories for all existing models')
            ->optional()
            ->boolean('force')
            ->description('Force installation even if already installed')
            ->optional()
            ->boolean('update_config')
            ->description('Download and use the latest config file from Laravel Restify 10.x (default: true)')
            ->optional();
    }

    public function handle(array $arguments): ToolResult|Generator
    {
        try {
            $runMigrations = $arguments['run_migrations'] ?? true;
            $enableSanctumAuth = $arguments['enable_sanctum_auth'] ?? false;
            $apiPrefix = $arguments['api_prefix'] ?? null;
            $installDoctrineDbal = $arguments['install_doctrine_dbal'] ?? false;
            $generateUsersCount = $arguments['generate_users_count'] ?? 0;
            $generateRepositories = $arguments['generate_repositories'] ?? false;
            $force = $arguments['force'] ?? false;
            $updateConfig = $arguments['update_config'] ?? true;

            // Step 1: Validate environment
            $validationResult = $this->validateEnvironment();
            if (! $validationResult['success']) {
                return ToolResult::error($validationResult['message']);
            }

            // Step 2: Check if already installed
            if (! $force && $this->isRestifyAlreadyInstalled()) {
                return ToolResult::error(
                    'Laravel Restify is already installed. Use "force: true" to reinstall.'
                );
            }

            $results = [];

            // Step 3: Install composer package
            $installResult = $this->installComposerPackage();
            $results[] = $installResult;
            if (! $installResult['success']) {
                return ToolResult::error($installResult['message']);
            }

            // Step 4: Run restify setup
            $setupResult = $this->runRestifySetup();
            $results[] = $setupResult;
            if (! $setupResult['success']) {
                return ToolResult::error($setupResult['message']);
            }

            // Step 4.5: Update config file with latest version
            if ($updateConfig) {
                $configResult = $this->updateConfigFile();
                $results[] = $configResult;
            }

            // Step 5: Configure options
            if ($enableSanctumAuth) {
                $results[] = $this->enableSanctumAuthentication();
            }

            if ($apiPrefix) {
                $results[] = $this->configureApiPrefix($apiPrefix);
            }

            // Step 6: Run migrations
            if ($runMigrations) {
                $migrationResult = $this->runMigrations();
                $results[] = $migrationResult;
            }

            // Step 7: Install doctrine/dbal if requested
            if ($installDoctrineDbal) {
                $dbalResult = $this->installDoctrineDbal();
                $results[] = $dbalResult;

                // Step 8: Generate mock users if requested
                if ($generateUsersCount > 0) {
                    $results[] = $this->generateMockUsers($generateUsersCount);
                }
            }

            // Step 9: Generate repositories if requested
            if ($generateRepositories) {
                $results[] = $this->generateRepositoriesForModels();
            }

            return $this->generateSuccessResponse($results, $arguments);

        } catch (\Throwable $e) {
            return ToolResult::error('Restify installation failed: '.$e->getMessage());
        }
    }

    protected function validateEnvironment(): array
    {
        // Check if this is a Laravel project
        if (! File::exists(base_path('artisan'))) {
            return [
                'success' => false,
                'message' => 'This is not a Laravel project (artisan file not found)',
            ];
        }

        // Check PHP version
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            return [
                'success' => false,
                'message' => 'PHP 8.0 or higher is required. Current version: '.PHP_VERSION,
            ];
        }

        // Check if composer is available
        $composerCheck = Process::run('composer --version');
        if (! $composerCheck->successful()) {
            return [
                'success' => false,
                'message' => 'Composer is not available or not in PATH',
            ];
        }

        return ['success' => true];
    }

    protected function isRestifyAlreadyInstalled(): bool
    {
        // Check if package is in composer.json
        $composerFile = base_path('composer.json');
        if (File::exists($composerFile)) {
            $composer = json_decode(File::get($composerFile), true);
            $require = $composer['require'] ?? [];

            if (isset($require['binaryk/laravel-restify'])) {
                return true;
            }
        }

        // Check if config file exists
        if (File::exists(config_path('restify.php'))) {
            return true;
        }

        return false;
    }

    protected function installComposerPackage(): array
    {
        try {
            $result = Process::timeout(300)->run('composer require binaryk/laravel-restify');

            if ($result->successful()) {
                return [
                    'success' => true,
                    'step' => 'Package Installation',
                    'message' => 'Laravel Restify package installed successfully',
                ];
            }

            return [
                'success' => false,
                'step' => 'Package Installation',
                'message' => 'Failed to install Laravel Restify: '.$result->errorOutput(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'step' => 'Package Installation',
                'message' => 'Composer installation failed: '.$e->getMessage(),
            ];
        }
    }

    protected function runRestifySetup(): array
    {
        try {
            $result = Process::timeout(120)->run('php artisan restify:setup');

            if ($result->successful()) {
                return [
                    'success' => true,
                    'step' => 'Restify Setup',
                    'message' => 'Restify setup completed successfully',
                ];
            }

            return [
                'success' => false,
                'step' => 'Restify Setup',
                'message' => 'Restify setup failed: '.$result->errorOutput(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'step' => 'Restify Setup',
                'message' => 'Setup command failed: '.$e->getMessage(),
            ];
        }
    }

    protected function updateConfigFile(): array
    {
        try {
            $configPath = config_path('restify.php');
            $existingConfig = null;
            $backupCreated = false;

            // Backup existing config if it exists
            if (File::exists($configPath)) {
                $existingConfig = File::get($configPath);
                $backupPath = $configPath.'.backup-'.date('Y-m-d-H-i-s');
                File::copy($configPath, $backupPath);
                $backupCreated = true;
            }

            // Download latest config file
            $response = Http::timeout(30)->get(self::LATEST_CONFIG_URL);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'step' => 'Config Update',
                    'message' => 'Failed to download latest config file: HTTP '.$response->status(),
                ];
            }

            $latestConfig = $response->body();

            // If we have existing config, try to preserve custom settings
            if ($existingConfig && $this->configsAreDifferent($existingConfig, $latestConfig)) {
                $mergedConfig = $this->mergeConfigFiles($existingConfig, $latestConfig);
                File::put($configPath, $mergedConfig);

                $message = $backupCreated
                    ? 'Updated config file with latest version (backup created)'
                    : 'Updated config file with latest version';
            } else {
                // Use the latest config as-is
                File::put($configPath, $latestConfig);
                $message = 'Downloaded and installed latest config file';
            }

            return [
                'success' => true,
                'step' => 'Config Update',
                'message' => $message,
                'backup_created' => $backupCreated,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'step' => 'Config Update',
                'message' => 'Failed to update config file: '.$e->getMessage(),
            ];
        }
    }

    protected function configsAreDifferent(string $existing, string $latest): bool
    {
        // Simple comparison - if they're identical, no need to merge
        return trim($existing) !== trim($latest);
    }

    protected function mergeConfigFiles(string $existing, string $latest): string
    {
        try {
            // Extract custom values from existing config
            $customValues = $this->extractCustomValues($existing);

            // Start with the latest config
            $mergedConfig = $latest;

            // Apply custom values to the latest config
            foreach ($customValues as $key => $value) {
                $mergedConfig = $this->replaceConfigValue($mergedConfig, $key, $value);
            }

            // Add a comment indicating the merge
            $timestamp = date('Y-m-d H:i:s');
            $mergedConfig = str_replace(
                '<?php',
                "<?php\n\n// This config was merged with custom values on {$timestamp}\n// Original backed up to restify.php.backup-{$timestamp}",
                $mergedConfig
            );

            return $mergedConfig;

        } catch (\Exception $e) {
            // If merge fails, return the latest config with a warning comment
            return str_replace(
                '<?php',
                "<?php\n\n// Warning: Config merge failed, using latest version.\n// Check backup file for your custom settings.",
                $latest
            );
        }
    }

    protected function extractCustomValues(string $config): array
    {
        $customValues = [];

        // Extract commonly customized values
        $patterns = [
            'base' => "/'base'\s*=>\s*'([^']+)'/",
            'middleware' => "/'middleware'\s*=>\s*(\[[^\]]*\])/s",
            'auth' => "/'auth'\s*=>\s*(\[[^\]]*\])/s",
            'cache' => "/'cache'\s*=>\s*(\[[^\]]*\])/s",
        ];

        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $config, $matches)) {
                $customValues[$key] = $key === 'base' ? $matches[1] : $matches[0];
            }
        }

        return $customValues;
    }

    protected function replaceConfigValue(string $config, string $key, string $value): string
    {
        switch ($key) {
            case 'base':
                return preg_replace(
                    "/'base'\s*=>\s*'[^']+'/",
                    "'base' => '{$value}'",
                    $config
                );
            case 'middleware':
            case 'auth':
            case 'cache':
                $pattern = "/'$key'\s*=>\s*\[[^\]]*\]/s";

                return preg_replace($pattern, $value, $config);
            default:
                return $config;
        }
    }

    protected function enableSanctumAuthentication(): array
    {
        try {
            $configPath = config_path('restify.php');

            if (! File::exists($configPath)) {
                return [
                    'success' => false,
                    'step' => 'Sanctum Configuration',
                    'message' => 'Restify config file not found',
                ];
            }

            $config = File::get($configPath);
            $updatedConfig = str_replace(
                "// 'auth:sanctum',",
                "'auth:sanctum',",
                $config
            );

            File::put($configPath, $updatedConfig);

            return [
                'success' => true,
                'step' => 'Sanctum Configuration',
                'message' => 'Sanctum authentication enabled in restify.php',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'step' => 'Sanctum Configuration',
                'message' => 'Failed to enable Sanctum: '.$e->getMessage(),
            ];
        }
    }

    protected function configureApiPrefix(string $prefix): array
    {
        try {
            $configPath = config_path('restify.php');

            if (! File::exists($configPath)) {
                return [
                    'success' => false,
                    'step' => 'API Prefix Configuration',
                    'message' => 'Restify config file not found',
                ];
            }

            $config = File::get($configPath);
            $updatedConfig = preg_replace(
                "/'base' => '[^']*'/",
                "'base' => '{$prefix}'",
                $config
            );

            File::put($configPath, $updatedConfig);

            return [
                'success' => true,
                'step' => 'API Prefix Configuration',
                'message' => "API prefix updated to: {$prefix}",
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'step' => 'API Prefix Configuration',
                'message' => 'Failed to update API prefix: '.$e->getMessage(),
            ];
        }
    }

    protected function runMigrations(): array
    {
        try {
            $result = Process::timeout(180)->run('php artisan migrate');

            if ($result->successful()) {
                return [
                    'success' => true,
                    'step' => 'Migrations',
                    'message' => 'Migrations completed successfully',
                ];
            }

            return [
                'success' => false,
                'step' => 'Migrations',
                'message' => 'Migration failed: '.$result->errorOutput(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'step' => 'Migrations',
                'message' => 'Migration command failed: '.$e->getMessage(),
            ];
        }
    }

    protected function installDoctrineDbal(): array
    {
        try {
            $result = Process::timeout(180)->run('composer require doctrine/dbal --dev');

            if ($result->successful()) {
                return [
                    'success' => true,
                    'step' => 'Doctrine DBAL Installation',
                    'message' => 'doctrine/dbal installed successfully',
                ];
            }

            return [
                'success' => false,
                'step' => 'Doctrine DBAL Installation',
                'message' => 'Failed to install doctrine/dbal: '.$result->errorOutput(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'step' => 'Doctrine DBAL Installation',
                'message' => 'Doctrine DBAL installation failed: '.$e->getMessage(),
            ];
        }
    }

    protected function generateMockUsers(int $count): array
    {
        try {
            $result = Process::timeout(120)->run("php artisan restify:stub users --count={$count}");

            if ($result->successful()) {
                return [
                    'success' => true,
                    'step' => 'Mock Data Generation',
                    'message' => "Generated {$count} mock users successfully",
                ];
            }

            return [
                'success' => false,
                'step' => 'Mock Data Generation',
                'message' => 'Failed to generate mock users: '.$result->errorOutput(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'step' => 'Mock Data Generation',
                'message' => 'Mock data generation failed: '.$e->getMessage(),
            ];
        }
    }

    protected function generateRepositoriesForModels(): array
    {
        try {
            $result = Process::timeout(180)->run('php artisan restify:generate:repositories --skip-preview');

            if ($result->successful()) {
                return [
                    'success' => true,
                    'step' => 'Repository Generation',
                    'message' => 'Repositories generated for existing models',
                ];
            }

            return [
                'success' => false,
                'step' => 'Repository Generation',
                'message' => 'Repository generation failed: '.$result->errorOutput(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'step' => 'Repository Generation',
                'message' => 'Repository generation failed: '.$e->getMessage(),
            ];
        }
    }

    protected function generateSuccessResponse(array $results, array $arguments): ToolResult
    {
        $response = "# Laravel Restify Installation Complete! 🎉\n\n";

        // Installation summary
        $response .= "## Installation Summary\n\n";

        foreach ($results as $result) {
            if (isset($result['step'])) {
                $icon = $result['success'] ? '✅' : '❌';
                $response .= "**{$icon} {$result['step']}:** {$result['message']}\n";
            }
        }

        // Configuration summary
        $response .= "\n## Configuration Applied\n\n";

        if ($arguments['enable_sanctum_auth'] ?? false) {
            $response .= "✅ **Sanctum Authentication:** Enabled\n";
        }

        if (! empty($arguments['api_prefix'])) {
            $response .= "✅ **API Prefix:** `{$arguments['api_prefix']}`\n";
        } else {
            $response .= "ℹ️ **API Prefix:** `/api/restify` (default)\n";
        }

        if ($arguments['install_doctrine_dbal'] ?? false) {
            $response .= "✅ **Doctrine DBAL:** Installed for mock data generation\n";
        }

        if (($arguments['generate_users_count'] ?? 0) > 0) {
            $response .= "✅ **Mock Users:** Generated {$arguments['generate_users_count']} users\n";
        }

        if ($arguments['generate_repositories'] ?? false) {
            $response .= "✅ **Repositories:** Auto-generated for existing models\n";
        }

        if ($arguments['update_config'] ?? true) {
            $response .= "✅ **Config File:** Updated with latest Laravel Restify 10.x configuration\n";
        }

        // What was created
        $response .= "\n## Files Created/Updated\n\n";
        $response .= "- `config/restify.php` - Latest configuration file (v10.x)\n";
        $response .= "- `app/Providers/RestifyServiceProvider.php` - Service provider\n";
        $response .= "- `app/Restify/` - Repository directory\n";
        $response .= "- `app/Restify/Repository.php` - Base repository class\n";
        $response .= "- `app/Restify/UserRepository.php` - User repository example\n";
        $response .= "- Database migration for action logs\n";

        if ($arguments['update_config'] ?? true) {
            $response .= "- `config/restify.php.backup-*` - Backup of previous config (if existed)\n";
        }

        // API endpoints
        $apiPrefix = $arguments['api_prefix'] ?? '/api/restify';
        $response .= "\n## Available API Endpoints\n\n";
        $response .= "Your Laravel Restify API is now available at:\n\n";
        $response .= "```\n";
        $response .= "GET    {$apiPrefix}/users          # List users\n";
        $response .= "POST   {$apiPrefix}/users          # Create user\n";
        $response .= "GET    {$apiPrefix}/users/{id}     # Show user\n";
        $response .= "PATCH  {$apiPrefix}/users/{id}     # Update user\n";
        $response .= "DELETE {$apiPrefix}/users/{id}     # Delete user\n";
        $response .= "```\n";

        // Next steps
        $response .= "\n## Next Steps\n\n";
        $response .= "1. **Test the API:** Make a GET request to `{$apiPrefix}/users`\n";
        $response .= "2. **Review config file:** Check `config/restify.php` for the latest features\n";
        $response .= "3. **Create more repositories:** `php artisan restify:repository PostRepository`\n";
        $response .= "4. **Generate policies:** `php artisan restify:policy UserPolicy`\n";
        $response .= "5. **Review documentation:** https://restify.binarcode.com\n";

        if ($arguments['enable_sanctum_auth'] ?? false) {
            $response .= "6. **Configure Sanctum:** Ensure Laravel Sanctum is properly set up\n";
        }

        // Authentication note
        if ($arguments['enable_sanctum_auth'] ?? false) {
            $response .= "\n## Authentication Note\n\n";
            $response .= "⚠️ **Sanctum authentication is enabled.** Make sure:\n";
            $response .= "- Laravel Sanctum is installed: `composer require laravel/sanctum`\n";
            $response .= "- Sanctum is published: `php artisan vendor:publish --provider=\"Laravel\\Sanctum\\SanctumServiceProvider\"`\n";
            $response .= "- API tokens are configured for your users\n";
        }

        // Additional commands
        $response .= "\n## Useful Commands\n\n";
        $response .= "```bash\n";
        $response .= "# Generate new repository\n";
        $response .= "php artisan restify:repository PostRepository --all\n\n";
        $response .= "# Generate repositories for all models\n";
        $response .= "php artisan restify:generate:repositories\n\n";
        $response .= "# Generate mock data\n";
        $response .= "php artisan restify:stub users --count=50\n\n";
        $response .= "# Generate policy\n";
        $response .= "php artisan restify:policy PostPolicy\n\n";
        $response .= "# Republish config file (to get latest updates)\n";
        $response .= "php artisan vendor:publish --provider=\"Binaryk\\LaravelRestify\\LaravelRestifyServiceProvider\" --tag=config --force\n";
        $response .= "```\n";

        return ToolResult::text($response);
    }
}
