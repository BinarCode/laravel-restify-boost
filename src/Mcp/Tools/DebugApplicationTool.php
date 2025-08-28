<?php

declare(strict_types=1);

namespace BinarCode\RestifyBoost\Mcp\Tools;

use Generator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Symfony\Component\Finder\Finder;
use Throwable;

class DebugApplicationTool extends Tool
{
    public function description(): string
    {
        return 'Comprehensive debugging tool for Laravel Restify applications. Performs health checks on Laravel installation, database connectivity, Restify configuration, repository validation, performance analysis, and common issue detection. Provides detailed diagnostic reports with actionable suggestions and optional automatic fixes.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema
            ->string('check_type')
            ->description('Specific check to run: "all", "config", "database", "restify", "performance", "health" (default: all)')
            ->optional()
            ->boolean('detailed_output')
            ->description('Include detailed diagnostic information and stack traces (default: true)')
            ->optional()
            ->boolean('fix_issues')
            ->description('Attempt to automatically fix common issues found (default: false)')
            ->optional()
            ->boolean('export_report')
            ->description('Export diagnostic report to storage/logs/debug-report.md (default: false)')
            ->optional()
            ->boolean('include_suggestions')
            ->description('Include detailed fix suggestions in output (default: true)')
            ->optional();
    }

    public function handle(array $arguments): ToolResult|Generator
    {
        try {
            $checkType = $arguments['check_type'] ?? 'all';
            $detailedOutput = $arguments['detailed_output'] ?? true;
            $fixIssues = $arguments['fix_issues'] ?? false;
            $exportReport = $arguments['export_report'] ?? false;
            $includeSuggestions = $arguments['include_suggestions'] ?? true;

            $report = [
                'summary' => [],
                'health_check' => [],
                'config_analysis' => [],
                'database_check' => [],
                'restify_analysis' => [],
                'performance_check' => [],
                'issues' => [],
                'fixes_applied' => [],
                'suggestions' => [],
            ];

            // Perform checks based on type
            if ($checkType === 'all' || $checkType === 'health') {
                $report['health_check'] = $this->performHealthCheck();
            }

            if ($checkType === 'all' || $checkType === 'config') {
                $report['config_analysis'] = $this->analyzeConfiguration();
            }

            if ($checkType === 'all' || $checkType === 'database') {
                $report['database_check'] = $this->checkDatabaseHealth();
            }

            if ($checkType === 'all' || $checkType === 'restify') {
                $report['restify_analysis'] = $this->analyzeRestifySetup();
            }

            if ($checkType === 'all' || $checkType === 'performance') {
                $report['performance_check'] = $this->analyzePerformance();
            }

            // Collect all issues
            $this->collectIssues($report);

            // Apply fixes if requested
            if ($fixIssues) {
                $report['fixes_applied'] = $this->applyAutomaticFixes($report['issues']);
            }

            // Generate suggestions
            if ($includeSuggestions) {
                $report['suggestions'] = $this->generateSuggestions($report);
            }

            // Export report if requested
            if ($exportReport) {
                $this->exportReport($report);
            }

            return $this->generateDebugReport($report, $detailedOutput);

        } catch (Throwable $e) {
            return ToolResult::error('Debug analysis failed: ' . $e->getMessage());
        }
    }

    protected function performHealthCheck(): array
    {
        $checks = [];

        // Laravel version check
        $checks['laravel_version'] = [
            'status' => 'success',
            'value' => app()->version(),
            'message' => 'Laravel framework detected',
        ];

        // PHP version check
        $phpVersion = PHP_VERSION;
        $checks['php_version'] = [
            'status' => version_compare($phpVersion, '8.1.0', '>=') ? 'success' : 'warning',
            'value' => $phpVersion,
            'message' => version_compare($phpVersion, '8.1.0', '>=') ? 'PHP version compatible' : 'PHP version may be outdated',
       ];

        // Environment check
        $environment = app()->environment();
        $checks['environment'] = [
            'status' => 'info',
            'value' => $environment,
            'message' => "Running in {$environment} environment",
        ];

        // Debug mode check
        $debugMode = config('app.debug');
        $checks['debug_mode'] = [
            'status' => $debugMode && $environment === 'production' ? 'warning' : 'success',
            'value' => $debugMode ? 'enabled' : 'disabled',
            'message' => $debugMode && $environment === 'production' ? 'Debug mode enabled in production!' : 'Debug mode appropriately configured',
        ];

        // Cache check
        try {
            Cache::put('debug_test', 'test', 10);
            $cacheWorks = Cache::get('debug_test') === 'test';
            Cache::forget('debug_test');

            $checks['cache'] = [
                'status' => $cacheWorks ? 'success' : 'error',
                'value' => config('cache.default'),
                'message' => $cacheWorks ? 'Cache system working' : 'Cache system not working',
            ];
        } catch (Throwable $e) {
            $checks['cache'] = [
                'status' => 'error',
                'value' => 'error',
                'message' => 'Cache error: ' . $e->getMessage(),
            ];
        }

        // Storage permissions
        $storagePath = storage_path();
        $checks['storage_permissions'] = [
            'status' => is_writable($storagePath) ? 'success' : 'error',
            'value' => substr(sprintf('%o', fileperms($storagePath)), -4),
            'message' => is_writable($storagePath) ? 'Storage directory writable' : 'Storage directory not writable',
        ];

        return $checks;
    }

    protected function analyzeConfiguration(): array
    {
        $analysis = [];

        // App configuration
        $analysis['app_config'] = [
            'app_key' => [
                'status' => config('app.key') ? 'success' : 'error',
                'message' => config('app.key') ? 'Application key set' : 'Application key missing',
            ],
            'app_url' => [
                'status' => config('app.url') ? 'success' : 'warning',
                'value' => config('app.url'),
                'message' => config('app.url') ? 'Application URL configured' : 'Application URL not set',
            ],
        ];

        // Database configuration
        $analysis['database_config'] = [
            'connection' => [
                'status' => 'info',
                'value' => config('database.default'),
                'message' => 'Default database connection: ' . config('database.default'),
            ],
        ];

        // Restify configuration
        $restifyConfigPath = config_path('restify.php');
        $analysis['restify_config'] = [
            'config_file' => [
                'status' => File::exists($restifyConfigPath) ? 'success' : 'warning',
                'message' => File::exists($restifyConfigPath) ? 'Restify config file exists' : 'Restify config file missing',
            ],
        ];

        if (File::exists($restifyConfigPath)) {
            $restifyConfig = include $restifyConfigPath;

            $analysis['restify_config']['middleware'] = [
                'status' => isset($restifyConfig['middleware']) ? 'success' : 'warning',
                'value' => isset($restifyConfig['middleware']) ? count($restifyConfig['middleware']) : 0,
                'message' => isset($restifyConfig['middleware']) ? 'Middleware configured' : 'No middleware configured',
            ];
        }

        // Mail configuration
        $analysis['mail_config'] = [
            'driver' => [
                'status' => config('mail.default') ? 'success' : 'warning',
                'value' => config('mail.default'),
                'message' => config('mail.default') ? 'Mail driver configured' : 'Mail driver not configured',
            ],
        ];

        return $analysis;
    }

    protected function checkDatabaseHealth(): array
    {
        $checks = [];

        try {
            // Test database connection
            DB::connection()->getPdo();
            $checks['connection'] = [
                'status' => 'success',
                'message' => 'Database connection successful',
            ];

            // Check migrations
            try {
                $pendingMigrations = Artisan::call('migrate:status', ['--pending' => true]);
                $checks['migrations'] = [
                    'status' => 'success',
                    'message' => 'Migration status checked',
                ];
            } catch (Throwable $e) {
                $checks['migrations'] = [
                    'status' => 'warning',
                    'message' => 'Could not check migration status: ' . $e->getMessage(),
                ];
            }

            // Check common tables
            $commonTables = ['users', 'migrations', 'password_resets'];
            $existingTables = [];
            foreach ($commonTables as $table) {
                if (Schema::hasTable($table)) {
                    $existingTables[] = $table;
                }
            }

            $checks['common_tables'] = [
                'status' => count($existingTables) > 0 ? 'success' : 'warning',
                'value' => $existingTables,
                'message' => count($existingTables) > 0 ? 'Common tables found' : 'No common tables found',
            ];

            // Test query performance
            $start = microtime(true);
            DB::select('SELECT 1');
            $queryTime = microtime(true) - $start;

            $checks['query_performance'] = [
                'status' => $queryTime < 0.1 ? 'success' : 'warning',
                'value' => round($queryTime * 1000, 2) . 'ms',
                'message' => $queryTime < 0.1 ? 'Good query performance' : 'Slow query performance',
            ];

        } catch (Throwable $e) {
            $checks['connection'] = [
                'status' => 'error',
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
        }

        return $checks;
    }

    protected function analyzeRestifySetup(): array
    {
        $analysis = [];

        // Find repositories
        $repositories = $this->findRepositories();
        $analysis['repositories'] = [
            'count' => count($repositories),
            'status' => count($repositories) > 0 ? 'success' : 'info',
            'message' => count($repositories) > 0 ? 'Restify repositories found' : 'No Restify repositories found',
            'list' => array_keys($repositories),
        ];

        // Check routes
        $restifyRoutes = collect(Route::getRoutes())->filter(function ($route) {
            return str_contains($route->uri(), 'restify-api') || str_contains($route->getActionName(), 'Restify');
        });

        $analysis['routes'] = [
            'count' => $restifyRoutes->count(),
            'status' => $restifyRoutes->count() > 0 ? 'success' : 'warning',
            'message' => $restifyRoutes->count() > 0 ? 'Restify routes registered' : 'No Restify routes found',
        ];

        // Analyze each repository
        $repositoryAnalysis = [];
        foreach ($repositories as $path => $info) {
            $repositoryAnalysis[basename($path, '.php')] = $this->analyzeRepository($path);
        }
        $analysis['repository_analysis'] = $repositoryAnalysis;

        // Check service provider
        $providers = config('app.providers', []);
        $restifyProvider = collect($providers)->first(function ($provider) {
            return str_contains($provider, 'RestifyServiceProvider') || str_contains($provider, 'Restify');
        });

        $analysis['service_provider'] = [
            'status' => $restifyProvider ? 'success' : 'warning',
            'value' => $restifyProvider,
            'message' => $restifyProvider ? 'Restify service provider registered' : 'Restify service provider not found',
        ];

        return $analysis;
    }

    protected function findRepositories(): array
    {
        $repositories = [];
        $searchPaths = [
            app_path('Restify'),
            app_path('Http/Restify'),
            app_path('Repositories'),
        ];

        foreach ($searchPaths as $searchPath) {
            if (!File::isDirectory($searchPath)) {
                continue;
            }

            try {
                $finder = new Finder();
                $finder->files()
                    ->in($searchPath)
                    ->name('*Repository.php')
                    ->notPath('vendor');

                foreach ($finder as $file) {
                    $filePath = $file->getRealPath();
                    $content = File::get($filePath);

                    if (str_contains($content, 'extends Repository') || str_contains($content, 'use Repository')) {
                        $repositories[$filePath] = [
                            'name' => $file->getFilenameWithoutExtension(),
                            'size' => $file->getSize(),
                            'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                        ];
                    }
                }
            } catch (Throwable $e) {
                // Skip path on error
            }
        }

        return $repositories;
    }

    protected function analyzeRepository(string $filePath): array
    {
        $content = File::get($filePath);
        $analysis = [
            'issues' => [],
            'suggestions' => [],
            'fields_count' => 0,
            'relationships_count' => 0,
        ];

        // Check for fields method
        if (preg_match('/public\s+function\s+fields\s*\([^)]*\)\s*:\s*array/', $content)) {
            $analysis['has_fields_method'] = true;

            // Count fields
            if (preg_match('/public\s+function\s+fields\s*\([^)]*\)\s*:\s*array\s*{(.*?)}/s', $content, $matches)) {
                $fieldsContent = $matches[1];
                preg_match_all('/field\s*\(/', $fieldsContent, $fieldMatches);
                $analysis['fields_count'] = count($fieldMatches[0]);
            }
        } else {
            $analysis['has_fields_method'] = false;
            $analysis['issues'][] = 'Missing fields() method';
        }

        // Check for model property or attribute
        if (preg_match('/public\s+static\s+string\s+\$model\s*=/', $content)) {
            $analysis['has_model'] = true;
            $analysis['model_type'] = 'property';
        } elseif (preg_match('/#\[Model\(/', $content)) {
            $analysis['has_model'] = true;
            $analysis['model_type'] = 'attribute';
        } else {
            $analysis['has_model'] = false;
            $analysis['issues'][] = 'Missing model definition';
        }

        // Check for relationships
        if (preg_match('/public\s+static\s+function\s+include\s*\(\)\s*:\s*array/', $content)) {
            $analysis['has_relationships'] = true;

            // Count relationships
            if (preg_match('/public\s+static\s+function\s+include\s*\(\)\s*:\s*array\s*{(.*?)}/s', $content, $matches)) {
                $includeContent = $matches[1];
                preg_match_all('/(BelongsTo|HasMany|HasOne|BelongsToMany)::', $includeContent, $relMatches);
                $analysis['relationships_count'] = count($relMatches[0]);
            }
        } else {
            $analysis['has_relationships'] = false;
        }

        // Check for authorization
        if (str_contains($content, 'public function allowedToShow') ||
            str_contains($content, 'public function allowedToStore') ||
            str_contains($content, 'public function allowedToUpdate')) {
            $analysis['has_authorization'] = true;
        } else {
            $analysis['has_authorization'] = false;
            $analysis['suggestions'][] = 'Consider adding authorization methods';
        }

        return $analysis;
    }

    protected function analyzePerformance(): array
    {
        $analysis = [];

        // Memory usage
        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        $analysis['memory'] = [
            'current' => $this->formatBytes($memoryUsage),
            'peak' => $this->formatBytes($peakMemory),
            'status' => $peakMemory < 128 * 1024 * 1024 ? 'success' : 'warning',
            'message' => $peakMemory < 128 * 1024 * 1024 ? 'Good memory usage' : 'High memory usage detected',
        ];

        // Check for query logging
        $analysis['query_logging'] = [
            'status' => config('app.debug') ? 'info' : 'success',
            'message' => config('app.debug') ? 'Query logging enabled (debug mode)' : 'Query logging disabled in production',
        ];

        // Check cache configuration
        $cacheDriver = config('cache.default');
        $analysis['cache_driver'] = [
            'value' => $cacheDriver,
            'status' => in_array($cacheDriver, ['redis', 'memcached']) ? 'success' : 'warning',
            'message' => in_array($cacheDriver, ['redis', 'memcached']) ? 'Efficient cache driver' : 'Consider using Redis or Memcached',
        ];

        // Check session driver
        $sessionDriver = config('session.driver');
        $analysis['session_driver'] = [
            'value' => $sessionDriver,
            'status' => in_array($sessionDriver, ['redis', 'database']) ? 'success' : 'warning',
            'message' => in_array($sessionDriver, ['redis', 'database']) ? 'Scalable session driver' : 'Consider using Redis or database sessions',
        ];

        return $analysis;
    }

    protected function collectIssues(array &$report): void
    {
        $issues = [];

        // Collect issues from all checks
        foreach ($report as $section => $data) {
            if (is_array($data)) {
                $this->extractIssuesFromSection($data, $section, $issues);
            }
        }

        $report['issues'] = $issues;
        $report['summary']['total_issues'] = count($issues);
        $report['summary']['critical_issues'] = count(array_filter($issues, fn($issue) => $issue['severity'] === 'error'));
        $report['summary']['warnings'] = count(array_filter($issues, fn($issue) => $issue['severity'] === 'warning'));
    }

    protected function extractIssuesFromSection(array $data, string $section, array &$issues): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (isset($value['status']) && in_array($value['status'], ['error', 'warning'])) {
                    $issues[] = [
                        'section' => $section,
                        'key' => $key,
                        'severity' => $value['status'],
                        'message' => $value['message'] ?? 'Issue detected',
                        'value' => $value['value'] ?? null,
                    ];
                } elseif (isset($value['issues']) && is_array($value['issues'])) {
                    foreach ($value['issues'] as $issue) {
                        $issues[] = [
                            'section' => $section,
                            'key' => $key,
                            'severity' => 'warning',
                            'message' => $issue,
                        ];
                    }
                } else {
                    $this->extractIssuesFromSection($value, $section, $issues);
                }
            }
        }
    }

    protected function applyAutomaticFixes(array $issues): array
    {
        $fixes = [];

        foreach ($issues as $issue) {
            $fix = $this->attemptAutoFix($issue);
            if ($fix) {
                $fixes[] = $fix;
            }
        }

        return $fixes;
    }

    protected function attemptAutoFix(array $issue): ?array
    {
        // Only attempt safe fixes
        switch ($issue['section']) {
            case 'config_analysis':
                if ($issue['key'] === 'restify_config' && str_contains($issue['message'], 'missing')) {
                    try {
                        Artisan::call('vendor:publish', [
                            '--provider' => 'Binaryk\\LaravelRestify\\LaravelRestifyServiceProvider',
                            '--tag' => 'config'
                        ]);

                        return [
                            'issue' => $issue['message'],
                            'fix' => 'Published Restify config file',
                            'status' => 'success',
                        ];
                    } catch (Throwable $e) {
                        return [
                            'issue' => $issue['message'],
                            'fix' => 'Failed to publish config: ' . $e->getMessage(),
                            'status' => 'error',
                        ];
                    }
                }
                break;
        }

        return null;
    }

    protected function generateSuggestions(array $report): array
    {
        $suggestions = [];

        // Performance suggestions
        if (isset($report['performance_check']['cache_driver']['status']) &&
            $report['performance_check']['cache_driver']['status'] === 'warning') {
            $suggestions[] = [
                'category' => 'performance',
                'title' => 'Upgrade Cache Driver',
                'description' => 'Consider switching to Redis or Memcached for better performance',
                'priority' => 'medium',
            ];
        }

        // Configuration suggestions
        if (isset($report['config_analysis']['restify_config']['config_file']['status']) &&
            $report['config_analysis']['restify_config']['config_file']['status'] === 'warning') {
            $suggestions[] = [
                'category' => 'configuration',
                'title' => 'Publish Restify Configuration',
                'description' => 'Run: php artisan vendor:publish --provider="Binaryk\\LaravelRestify\\LaravelRestifyServiceProvider" --tag="config"',
                'priority' => 'high',
            ];
        }

        // Repository suggestions
        if (isset($report['restify_analysis']['repositories']['count']) &&
            $report['restify_analysis']['repositories']['count'] === 0) {
            $suggestions[] = [
                'category' => 'development',
                'title' => 'Create Restify Repositories',
                'description' => 'Start by creating repositories for your models using: php artisan restify:repository ModelName',
                'priority' => 'high',
            ];
        }

        return $suggestions;
    }

    protected function exportReport(array $report): void
    {
        $reportPath = storage_path('logs/debug-report-' . date('Y-m-d-H-i-s') . '.md');
        $reportContent = $this->generateMarkdownReport($report);

        File::put($reportPath, $reportContent);
    }

    protected function generateMarkdownReport(array $report): string
    {
        $content = "# Laravel Restify Debug Report\n\n";
        $content .= "**Generated:** " . date('Y-m-d H:i:s') . "\n\n";

        // Summary
        if (isset($report['summary'])) {
            $content .= "## Summary\n\n";
            foreach ($report['summary'] as $key => $value) {
                $content .= "- **" . ucwords(str_replace('_', ' ', $key)) . ":** $value\n";
            }
            $content .= "\n";
        }

        // Issues
        if (!empty($report['issues'])) {
            $content .= "## Issues Found\n\n";
            foreach ($report['issues'] as $issue) {
                $emoji = $issue['severity'] === 'error' ? '❌' : '⚠️';
                $content .= "$emoji **{$issue['message']}**\n";
                $content .= "- Section: {$issue['section']}\n";
                if (isset($issue['value'])) {
                    $content .= "- Value: {$issue['value']}\n";
                }
                $content .= "\n";
            }
        }

        // Suggestions
        if (!empty($report['suggestions'])) {
            $content .= "## Suggestions\n\n";
            foreach ($report['suggestions'] as $suggestion) {
                $priority = strtoupper($suggestion['priority']);
                $content .= "### {$suggestion['title']} [$priority]\n\n";
                $content .= "{$suggestion['description']}\n\n";
            }
        }

        return $content;
    }

    protected function generateDebugReport(array $report, bool $detailed): ToolResult
    {
        $response = "# Laravel Restify Debug Report\n\n";

        // Overall status
        $criticalIssues = $report['summary']['critical_issues'] ?? 0;
        $warnings = $report['summary']['warnings'] ?? 0;

        if ($criticalIssues > 0) {
            $response .= "🔴 **Status: CRITICAL** - $criticalIssues critical issues found\n\n";
        } elseif ($warnings > 0) {
            $response .= "🟡 **Status: WARNING** - $warnings warnings found\n\n";
        } else {
            $response .= "🟢 **Status: HEALTHY** - No critical issues found\n\n";
        }

        // Summary
        if (isset($report['summary'])) {
            $response .= "## Summary\n\n";
            foreach ($report['summary'] as $key => $value) {
                $response .= "- **" . ucwords(str_replace('_', ' ', $key)) . ":** $value\n";
            }
            $response .= "\n";
        }

        // Health Check
        if (!empty($report['health_check'])) {
            $response .= "## System Health\n\n";
            foreach ($report['health_check'] as $check => $result) {
                $emoji = $this->getStatusEmoji($result['status']);
                $response .= "$emoji **" . ucwords(str_replace('_', ' ', $check)) . ":** {$result['message']}";
                if (isset($result['value'])) {
                    $response .= " ({$result['value']})";
                }
                $response .= "\n";
            }
            $response .= "\n";
        }

        // Database Check
        if (!empty($report['database_check'])) {
            $response .= "## Database Health\n\n";
            foreach ($report['database_check'] as $check => $result) {
                $emoji = $this->getStatusEmoji($result['status']);
                $response .= "$emoji **" . ucwords(str_replace('_', ' ', $check)) . ":** {$result['message']}";
                if (isset($result['value'])) {
                    $value = is_array($result['value']) ? implode(', ', $result['value']) : $result['value'];
                    $response .= " ($value)";
                }
                $response .= "\n";
            }
            $response .= "\n";
        }

        // Restify Analysis
        if (!empty($report['restify_analysis'])) {
            $response .= "## Restify Analysis\n\n";

            if (isset($report['restify_analysis']['repositories'])) {
                $repos = $report['restify_analysis']['repositories'];
                $emoji = $this->getStatusEmoji($repos['status']);
                $response .= "$emoji **Repositories:** {$repos['message']} ({$repos['count']} found)\n";

                if (!empty($repos['list']) && $detailed) {
                    foreach ($repos['list'] as $repo) {
                        $response .= "  - $repo\n";
                    }
                }
            }

            if (isset($report['restify_analysis']['routes'])) {
                $routes = $report['restify_analysis']['routes'];
                $emoji = $this->getStatusEmoji($routes['status']);
                $response .= "$emoji **Routes:** {$routes['message']} ({$routes['count']} found)\n";
            }

            $response .= "\n";
        }

        // Issues
        if (!empty($report['issues'])) {
            $response .= "## Issues Found\n\n";
            foreach ($report['issues'] as $issue) {
                $emoji = $issue['severity'] === 'error' ? '❌' : '⚠️';
                $response .= "$emoji **{$issue['message']}** ({$issue['section']})\n";
            }
            $response .= "\n";
        }

        // Fixes Applied
        if (!empty($report['fixes_applied'])) {
            $response .= "## Automatic Fixes Applied\n\n";
            foreach ($report['fixes_applied'] as $fix) {
                $emoji = $fix['status'] === 'success' ? '✅' : '❌';
                $response .= "$emoji {$fix['fix']}\n";
            }
            $response .= "\n";
        }

        // Suggestions
        if (!empty($report['suggestions'])) {
            $response .= "## Recommendations\n\n";
            foreach ($report['suggestions'] as $suggestion) {
                $priority = strtoupper($suggestion['priority']);
                $response .= "### {$suggestion['title']} [$priority]\n\n";
                $response .= "{$suggestion['description']}\n\n";
            }
        }

        // Next Steps
        $response .= "## Next Steps\n\n";
        if ($criticalIssues > 0) {
            $response .= "1. **Address critical issues immediately** - these may prevent proper functionality\n";
            $response .= "2. **Review warnings** - these indicate potential problems\n";
        } elseif ($warnings > 0) {
            $response .= "1. **Review warnings** - address these to improve application reliability\n";
        } else {
            $response .= "1. **System looks healthy!** Consider the recommendations above for optimization\n";
        }

        $response .= "2. **Run with fix_issues=true** to automatically fix common problems\n";
        $response .= "3. **Export detailed report** with export_report=true for documentation\n";

        return ToolResult::text($response);
    }

    protected function getStatusEmoji(string $status): string
    {
        return match ($status) {
            'success' => '✅',
            'error' => '❌',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            default => '🔘',
        };
    }

    protected function formatBytes(int $size, int $precision = 2): string
    {
        if ($size === 0) return '0B';

        $base = log($size, 1024);
        $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];

        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
    }
}
