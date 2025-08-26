<?php

declare(strict_types=1);

namespace BinarCode\RestifyBoost\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Laravel\Prompts\Concerns\Colors;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;

class InstallCommand extends Command
{
    use Colors;

    protected $signature = 'restify-boost:install';

    protected $description = 'Install and configure the Restify Boost package';

    public function handle(): int
    {
        $this->displayRestifyHeader();
        $this->displayWelcome();

        // Publish configuration file
        $this->line('Publishing configuration file...');
        $this->call('vendor:publish', [
            '--tag' => 'restify-boost-config',
            '--force' => true,
        ]);

        // Check documentation availability
        $this->line('Checking documentation availability...');
        $this->checkDocumentationPaths();

        // Setup MCP integration
        $this->setupMcpIntegration();

        // Setup AI assistant integration
        $this->setupAiAssistantIntegration();

        $this->displayCompletion();

        return self::SUCCESS;
    }

    private function displayRestifyHeader(): void
    {
        note($this->restifyLogo());
        intro('✦ Laravel Restify MCP :: Install :: We Must REST ✦');
    }

    private function restifyLogo(): string
    {
        return
         <<<'HEADER'
        ██████╗  ███████╗ ███████╗ ████████╗ ██╗ ███████╗ ██╗   ██╗
        ██╔══██╗ ██╔════╝ ██╔════╝ ╚══██╔══╝ ██║ ██╔════╝ ╚██╗ ██╔╝
        ██████╔╝ █████╗   ███████╗    ██║    ██║ █████╗    ╚████╔╝ 
        ██╔══██╗ ██╔══╝   ╚════██║    ██║    ██║ ██╔══╝     ╚██╔╝  
        ██║  ██║ ███████╗ ███████║    ██║    ██║ ██║         ██║   
        ╚═╝  ╚═╝ ╚══════╝ ╚══════╝    ╚═╝    ╚═╝ ╚═╝         ╚═╝   
        HEADER;
    }

    protected function displayWelcome(): void
    {
        $appName = config('app.name', 'Your Laravel App');
        note("Let's give {$this->bgBlue($this->white($this->bold($appName)))} a RESTful boost with Restify MCP!");
        $this->newLine();
        $this->line('This will set up Restify Boost to provide Laravel Restify');
        $this->line('documentation access to AI assistants like Claude Code.');
        $this->newLine();
    }

    protected function setupMcpIntegration(): void
    {
        $this->info('🔌 MCP Integration Setup');
        $this->newLine();

        // Check for Laravel MCP packages
        $mcpPackages = [
            'laravel/mcp' => 'Official Laravel MCP package (recommended)',
            'php-mcp/laravel' => 'Community Laravel MCP SDK',
            'opgginc/laravel-mcp-server' => 'Enterprise MCP server with HTTP transport',
        ];

        $installedPackage = null;
        foreach ($mcpPackages as $package => $description) {
            if ($this->isPackageInstalled($package)) {
                $installedPackage = $package;
                $this->info("✅ Found {$package}: {$description}");
                break;
            }
        }

        if (! $installedPackage) {
            $this->warn('⚠️  No MCP package found. The documentation will still be accessible via command line tools.');
            $this->newLine();

            if ($this->confirm('Would you like recommendations for MCP packages to install?', true)) {
                $this->displayMcpPackageRecommendations();
            }
        } else {
            $this->info("✅ MCP integration ready with {$installedPackage}!");
        }
    }

    protected function setupAiAssistantIntegration(): void
    {
        $this->newLine();
        $this->info('🤖 AI Assistant Integration');
        $this->newLine();

        $this->line('This package works with AI assistants that support the Model Context Protocol:');
        $this->line('• Claude Code (VS Code extension)');
        $this->line('• Cursor AI');
        $this->line('• Codeium');
        $this->line('• Other MCP-compatible tools');
        $this->newLine();

        if ($this->confirm('Are you using Claude Code?', false)) {
            $this->displayClaudeSetupInstructions();
        }

        if ($this->confirm('Are you using Cursor AI?', false)) {
            $this->displayCursorSetupInstructions();
        }

        if ($this->confirm('Would you like to see general MCP setup instructions?', false)) {
            $this->displayGeneralMcpInstructions();
        }
    }

    protected function displayMcpPackageRecommendations(): void
    {
        $this->newLine();
        $this->info('📦 Recommended MCP Packages:');
        $this->newLine();

        $this->line('For most users:');
        $this->line('  composer require laravel/mcp');
        $this->newLine();

        $this->line('For advanced users who want more control:');
        $this->line('  composer require php-mcp/laravel');
        $this->newLine();

        $this->line('For enterprise/HTTP-based setups:');
        $this->line('  composer require opgginc/laravel-mcp-server');
        $this->newLine();
    }

    protected function displayClaudeSetupInstructions(): void
    {
        $this->newLine();
        $this->info('🔧 Claude Code MCP Configuration');
        $this->newLine();

        if ($this->createMcpConfigFile()) {
            $this->info('✅ Successfully created .mcp.json file!');
            $this->line('The Laravel Restify Documentation server is now available.');
            $this->newLine();
            $this->line('🔄 Please restart Claude Code to load the new MCP server.');
        } else {
            $this->warn('⚠️  Could not create .mcp.json file. Manual setup required:');
            $this->newLine();
            $this->line('1. Create a .mcp.json file in your project root with:');
            $this->newLine();
            $this->line(json_encode([
                'mcpServers' => [
                    'laravel-restify' => [
                        'command' => 'php',
                        'args' => ['artisan', 'restify-boost:start'],
                    ],
                ],
            ], JSON_PRETTY_PRINT));
            $this->newLine();
            $this->line('2. Restart Claude Code to load the MCP server');
        }

        $this->newLine();
        $this->line('💡 Once configured, you can ask Claude about Laravel Restify:');
        $this->line('   "How do I create a custom field in Laravel Restify?"');
        $this->line('   "Show me examples of repository validation"');
        $this->newLine();
    }

    protected function displayCursorSetupInstructions(): void
    {
        $this->newLine();
        $this->info('🔧 Cursor AI Setup Instructions:');
        $this->newLine();

        $this->line('1. Open Cursor AI settings');
        $this->line('2. Navigate to Extensions → MCP');
        $this->line('3. Add a new MCP server:');
        $this->line('   - Name: Laravel Restify Docs');
        $this->line('   - Command: php artisan restify-boost:start');
        $this->line('   - Working Directory: '.base_path());
        $this->newLine();
    }

    protected function displayGeneralMcpInstructions(): void
    {
        $this->newLine();
        $this->info('🔧 General MCP Setup:');
        $this->newLine();

        $this->line('For any MCP-compatible AI assistant:');
        $this->line('1. Start the MCP server: php artisan restify-boost:start');
        $this->line('2. The server will be available on localhost:8080');
        $this->line('3. Configure your AI assistant to connect to this endpoint');
        $this->newLine();

        $this->line('Available MCP endpoints:');
        $this->line('• Tools: search-restify-docs, get-code-examples, navigate-docs, generate-repository');
        $this->line('• Resources: restify-documentation, restify-api-reference');
        $this->line('• Prompts: restify-how-to, restify-troubleshooting');
        $this->newLine();
    }

    protected function displayCompletion(): void
    {
        $this->newLine();
        $this->info('✅ Restify Boost package installed successfully!');
        $this->newLine();

        $this->info('🚀 Quick Start:');
        $this->line('1. Test the installation: php artisan restify-boost:execute search-restify-docs --queries="repository"');
        $this->line('2. Generate a repository: php artisan restify-boost:execute generate-repository --model-name="User"');
        $this->line('3. Browse documentation: php artisan restify-boost:execute navigate-docs --action="overview"');
        $this->line('4. Start MCP server: php artisan restify-boost:start');
        $this->line('5. Configure your AI assistant to use the MCP server');
        $this->newLine();

        $this->line('Configuration file: config/restify-boost.php');
        $this->line('Documentation: https://github.com/binarcode/restify-boost');
        $this->newLine();
    }

    protected function isPackageInstalled(string $package): bool
    {
        $composerLock = base_path('composer.lock');
        if (! File::exists($composerLock)) {
            return false;
        }

        $lockData = json_decode(File::get($composerLock), true);
        foreach (($lockData['packages'] ?? []) as $installedPackage) {
            if ($installedPackage['name'] === $package) {
                return true;
            }
        }

        return false;
    }

    protected function createMcpConfigFile(): bool
    {
        try {
            $mcpConfigPath = base_path('.mcp.json');

            // Load existing config or create new
            $config = [];
            if (File::exists($mcpConfigPath)) {
                $existingConfig = File::get($mcpConfigPath);
                $config = json_decode($existingConfig, true) ?: [];
            }

            // Initialize mcpServers section if it doesn't exist
            if (! isset($config['mcpServers'])) {
                $config['mcpServers'] = [];
            }

            // Add our MCP server configuration
            $config['mcpServers']['laravel-restify'] = [
                'command' => 'php',
                'args' => ['artisan', 'restify-boost:start'],
            ];

            // Write the updated config
            File::put($mcpConfigPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return true;
        } catch (\Exception $e) {
            $this->error('Failed to create .mcp.json file: '.$e->getMessage());

            return false;
        }
    }

    protected function checkDocumentationPaths(): void
    {
        $primaryPath = config('restify-boost.docs.paths.primary');
        $legacyPath = config('restify-boost.docs.paths.legacy');

        $foundDocs = false;

        if ($primaryPath && File::isDirectory($primaryPath)) {
            $this->info('✅ Primary documentation found: '.$primaryPath);
            $foundDocs = true;
        } else {
            $this->warn('⚠️  Primary documentation not found: '.$primaryPath);
        }

        if ($legacyPath && File::isDirectory($legacyPath)) {
            $this->info('✅ Legacy documentation found: '.$legacyPath);
            $foundDocs = true;
        } else {
            $this->warn('⚠️  Legacy documentation not found: '.$legacyPath);
        }

        if (! $foundDocs) {
            $this->warn('No documentation found. Please ensure Laravel Restify is properly installed.');
        }

        // Count available markdown files
        $totalFiles = 0;
        foreach ([$primaryPath, $legacyPath] as $path) {
            if (File::isDirectory($path)) {
                $files = File::allFiles($path);
                $markdownFiles = collect($files)->filter(fn ($file) => $file->getExtension() === 'md')->count();
                $totalFiles += $markdownFiles;
            }
        }

        if ($totalFiles > 0) {
            $this->info("Found {$totalFiles} documentation files");
        }
    }
}
