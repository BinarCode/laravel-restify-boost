<?php

declare(strict_types=1);

namespace BinarCode\RestifyBoost\Mcp\Tools;

use Generator;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Symfony\Component\Finder\Finder;

class UpgradeRestifyTool extends Tool
{
    public function description(): string
    {
        return 'Upgrade Laravel Restify from version 9.x to 10.x. This tool migrates repositories to use modern PHP attributes for model definitions, converts static search/sort arrays to field-level methods, checks config file compatibility, and provides a comprehensive upgrade report with recommendations.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema
            ->boolean('dry_run')
            ->description('Preview changes without applying them (default: true)')
            ->optional()
            ->boolean('migrate_attributes')
            ->description('Convert static $model properties to PHP attributes (default: true)')
            ->optional()
            ->boolean('migrate_fields')
            ->description('Convert static $search/$sort arrays to field-level methods (default: true)')
            ->optional()
            ->boolean('check_config')
            ->description('Check and report config file compatibility (default: true)')
            ->optional()
            ->boolean('backup_files')
            ->description('Create backups of modified files (default: true)')
            ->optional()
            ->string('path')
            ->description('Specific path to scan for repositories (defaults to app/Restify)')
            ->optional();
    }

    public function handle(array $arguments): ToolResult|Generator
    {
        try {
            $dryRun = $arguments['dry_run'] ?? true;
            $migrateAttributes = $arguments['migrate_attributes'] ?? true;
            $migrateFields = $arguments['migrate_fields'] ?? true;
            $checkConfig = $arguments['check_config'] ?? true;
            $backupFiles = $arguments['backup_files'] ?? true;
            $customPath = $arguments['path'] ?? null;

            $report = [
                'summary' => [],
                'repositories' => [],
                'config_issues' => [],
                'recommendations' => [],
                'changes_applied' => [],
                'backups_created' => [],
            ];

            // Step 1: Scan for repositories
            $repositories = $this->scanRepositories($customPath);
            $report['summary']['repositories_found'] = count($repositories);

            if (empty($repositories)) {
                return ToolResult::text('No Restify repositories found. Ensure you have repositories in app/Restify or specify a custom path.');
            }

            // Step 2: Analyze each repository
            foreach ($repositories as $repoPath => $repoInfo) {
                $analysis = $this->analyzeRepository($repoPath, $repoInfo);
                $report['repositories'][$repoPath] = $analysis;

                // Step 3: Apply migrations if not dry run
                if (! $dryRun) {
                    $changes = $this->applyMigrations(
                        $repoPath,
                        $analysis,
                        $migrateAttributes,
                        $migrateFields,
                        $backupFiles
                    );
                    $report['changes_applied'][$repoPath] = $changes;

                    if ($backupFiles && ! empty($changes)) {
                        $backup = $this->createBackup($repoPath);
                        if ($backup) {
                            $report['backups_created'][] = $backup;
                        }
                    }
                }
            }

            // Step 4: Check config compatibility
            if ($checkConfig) {
                $configIssues = $this->checkConfigCompatibility();
                $report['config_issues'] = $configIssues;
            }

            // Step 5: Generate recommendations
            $report['recommendations'] = $this->generateRecommendations($report);

            // Step 6: Generate response
            return $this->generateUpgradeReport($report, $dryRun);

        } catch (\Throwable $e) {
            return ToolResult::error('Restify upgrade failed: '.$e->getMessage());
        }
    }

    protected function scanRepositories(?string $customPath = null): array
    {
        $repositories = [];
        $searchPaths = $customPath ? [$customPath] : [
            app_path('Restify'),
            app_path('Http/Restify'),
            app_path('Repositories'),
        ];

        foreach ($searchPaths as $searchPath) {
            if (! File::isDirectory($searchPath)) {
                continue;
            }

            try {
                $finder = new Finder;
                $finder->files()
                    ->in($searchPath)
                    ->name('*Repository.php')
                    ->notPath('vendor')
                    ->notPath('tests');

                foreach ($finder as $file) {
                    $filePath = $file->getRealPath();
                    $content = File::get($filePath);

                    // Basic check if it's a Restify repository
                    if (str_contains($content, 'extends Repository') ||
                        str_contains($content, 'use Repository')) {

                        $repositories[$filePath] = [
                            'name' => $file->getFilenameWithoutExtension(),
                            'size' => $file->getSize(),
                            'modified' => $file->getMTime(),
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Skip path on error
            }
        }

        return $repositories;
    }

    protected function analyzeRepository(string $filePath, array $repoInfo): array
    {
        $content = File::get($filePath);
        $analysis = [
            'needs_attribute_migration' => false,
            'needs_field_migration' => false,
            'current_model' => null,
            'static_search_fields' => [],
            'static_sort_fields' => [],
            'field_definitions' => [],
            'issues' => [],
            'complexity_score' => 0,
        ];

        // Check for static $model property
        if (preg_match('/public\s+static\s+string\s+\$model\s*=\s*([^;]+);/', $content, $matches)) {
            $analysis['needs_attribute_migration'] = true;
            $analysis['current_model'] = trim($matches[1], ' \'"');
            $analysis['complexity_score'] += 2;
        }

        // Check for static search array
        if (preg_match('/public\s+static\s+array\s+\$search\s*=\s*\[(.*?)\];/s', $content, $matches)) {
            $analysis['needs_field_migration'] = true;
            $searchFields = $this->parseArrayContent($matches[1]);
            $analysis['static_search_fields'] = $searchFields;
            $analysis['complexity_score'] += count($searchFields);
        }

        // Check for static sort array
        if (preg_match('/public\s+static\s+array\s+\$sort\s*=\s*\[(.*?)\];/s', $content, $matches)) {
            $analysis['needs_field_migration'] = true;
            $sortFields = $this->parseArrayContent($matches[1]);
            $analysis['static_sort_fields'] = $sortFields;
            $analysis['complexity_score'] += count($sortFields);
        }

        // Analyze field definitions
        if (preg_match('/public\s+function\s+fields\s*\([^)]*\)\s*:\s*array\s*{(.*?)}/s', $content, $matches)) {
            $fieldsContent = $matches[1];
            $analysis['field_definitions'] = $this->extractFieldDefinitions($fieldsContent);
        }

        // Check for potential issues
        $this->checkForUpgradeIssues($content, $analysis);

        return $analysis;
    }

    protected function applyMigrations(
        string $filePath,
        array $analysis,
        bool $migrateAttributes,
        bool $migrateFields,
        bool $backupFiles
    ): array {
        $changes = [];
        $content = File::get($filePath);
        $originalContent = $content;

        // Migrate to PHP attributes
        if ($migrateAttributes && $analysis['needs_attribute_migration'] && $analysis['current_model']) {
            $content = $this->migrateToAttributes($content, $analysis['current_model']);
            $changes[] = 'Migrated static $model to #[Model] attribute';
        }

        // Migrate field-level search/sort
        if ($migrateFields && $analysis['needs_field_migration']) {
            $content = $this->migrateToFieldLevel($content, $analysis);
            $changes[] = 'Migrated static $search/$sort arrays to field-level methods';
        }

        // Write changes if content was modified
        if ($content !== $originalContent) {
            File::put($filePath, $content);
        }

        return $changes;
    }

    protected function migrateToAttributes(string $content, string $modelClass): string
    {
        // Add use statement if not present
        if (! str_contains($content, 'use Binaryk\LaravelRestify\Attributes\Model;')) {
            $content = preg_replace(
                '/(namespace\s+[^;]+;)/s',
                "$1\n\nuse Binaryk\\LaravelRestify\\Attributes\\Model;",
                $content
            );
        }

        // Replace static model property with attribute
        $content = preg_replace(
            '/public\s+static\s+string\s+\$model\s*=\s*([^;]+);/',
            '',
            $content
        );

        // Add attribute to class
        $content = preg_replace(
            '/(class\s+\w+Repository\s+extends\s+Repository)/s',
            "#[Model($modelClass)]\n$1",
            $content
        );

        return $content;
    }

    protected function migrateToFieldLevel(string $content, array $analysis): string
    {
        // Remove static arrays
        $content = preg_replace('/public\s+static\s+array\s+\$search\s*=\s*\[.*?\];\s*/s', '', $content);
        $content = preg_replace('/public\s+static\s+array\s+\$sort\s*=\s*\[.*?\];\s*/s', '', $content);

        // Update field definitions
        if (! empty($analysis['field_definitions'])) {
            $content = $this->updateFieldDefinitions(
                $content,
                $analysis['static_search_fields'],
                $analysis['static_sort_fields']
            );
        }

        return $content;
    }

    protected function updateFieldDefinitions(string $content, array $searchFields, array $sortFields): string
    {
        // Find and update fields method
        return preg_replace_callback(
            '/(public\s+function\s+fields\s*\([^)]*\)\s*:\s*array\s*{)(.*?)(})/s',
            function ($matches) use ($searchFields, $sortFields) {
                $methodStart = $matches[1];
                $methodBody = $matches[2];
                $methodEnd = $matches[3];

                // Update field() calls to include searchable/sortable
                $methodBody = preg_replace_callback(
                    '/field\([\'"]([^\'"]+)[\'"]\)/',
                    function ($fieldMatches) use ($searchFields, $sortFields) {
                        $fieldName = $fieldMatches[1];
                        $chain = $fieldMatches[0];

                        if (in_array($fieldName, $searchFields)) {
                            $chain .= '->searchable()';
                        }

                        if (in_array($fieldName, $sortFields)) {
                            $chain .= '->sortable()';
                        }

                        return $chain;
                    },
                    $methodBody
                );

                return $methodStart.$methodBody.$methodEnd;
            },
            $content
        );
    }

    protected function parseArrayContent(string $content): array
    {
        $fields = [];
        $content = trim($content);

        if (preg_match_all('/[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
            $fields = $matches[1];
        }

        return $fields;
    }

    protected function extractFieldDefinitions(string $content): array
    {
        $fields = [];

        if (preg_match_all('/field\([\'"]([^\'"]+)[\'"]\)/', $content, $matches)) {
            $fields = $matches[1];
        }

        return $fields;
    }

    protected function checkForUpgradeIssues(string $content, array &$analysis): void
    {
        // Check for potential compatibility issues

        // Old imports that might need updating
        if (str_contains($content, 'use Binaryk\LaravelRestify\Fields\Field;')) {
            $analysis['issues'][] = 'Consider updating field imports to use field() helper';
        }

        // Check for deprecated methods
        $deprecatedPatterns = [
            'resolveUsing' => 'Consider using modern field methods',
            'displayUsing' => 'Consider using modern field methods',
        ];

        foreach ($deprecatedPatterns as $pattern => $suggestion) {
            if (str_contains($content, $pattern)) {
                $analysis['issues'][] = $suggestion;
            }
        }
    }

    protected function checkConfigCompatibility(): array
    {
        $issues = [];
        $configPath = config_path('restify.php');

        if (! File::exists($configPath)) {
            $issues[] = [
                'type' => 'missing_config',
                'message' => 'Config file config/restify.php not found',
                'recommendation' => 'Run: php artisan vendor:publish --provider="Binaryk\\LaravelRestify\\LaravelRestifyServiceProvider" --tag="config"',
            ];

            return $issues;
        }

        $config = File::get($configPath);

        // Check for new v10 config sections
        $requiredSections = [
            'mcp' => 'MCP server configuration for AI tools',
            'ai_solutions' => 'AI-powered solutions configuration',
        ];

        foreach ($requiredSections as $section => $description) {
            if (! str_contains($config, "'$section'")) {
                $issues[] = [
                    'type' => 'missing_section',
                    'section' => $section,
                    'message' => "Missing '$section' configuration section",
                    'recommendation' => "Add $description to config file",
                ];
            }
        }

        return $issues;
    }

    protected function generateRecommendations(array $report): array
    {
        $recommendations = [];

        // General upgrade recommendations
        $recommendations[] = [
            'type' => 'general',
            'title' => 'Upgrade Laravel Restify Package',
            'description' => 'Update your composer.json to require Laravel Restify ^10.0',
            'command' => 'composer require binaryk/laravel-restify:^10.0',
        ];

        // Repository-specific recommendations
        $totalRepos = count($report['repositories']);
        $needsAttributeMigration = array_filter($report['repositories'], fn ($r) => $r['needs_attribute_migration']);
        $needsFieldMigration = array_filter($report['repositories'], fn ($r) => $r['needs_field_migration']);

        if (count($needsAttributeMigration) > 0) {
            $recommendations[] = [
                'type' => 'migration',
                'title' => 'PHP Attributes Migration',
                'description' => sprintf(
                    'Migrate %d/%d repositories to use PHP attributes for better IDE support',
                    count($needsAttributeMigration),
                    $totalRepos
                ),
                'priority' => 'recommended',
            ];
        }

        if (count($needsFieldMigration) > 0) {
            $recommendations[] = [
                'type' => 'migration',
                'title' => 'Field-Level Configuration',
                'description' => sprintf(
                    'Migrate %d/%d repositories to use field-level search/sort methods',
                    count($needsFieldMigration),
                    $totalRepos
                ),
                'priority' => 'recommended',
            ];
        }

        // Config recommendations
        if (! empty($report['config_issues'])) {
            $recommendations[] = [
                'type' => 'config',
                'title' => 'Configuration Updates',
                'description' => 'Update config file to include new v10 features',
                'priority' => 'important',
            ];
        }

        return $recommendations;
    }

    protected function createBackup(string $filePath): ?string
    {
        $backupPath = $filePath.'.bak-'.date('Y-m-d-H-i-s');

        try {
            File::copy($filePath, $backupPath);

            return $backupPath;
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function generateUpgradeReport(array $report, bool $dryRun): ToolResult
    {
        $response = "# Laravel Restify 9.x → 10.x Upgrade Report\n\n";

        // Status indicator
        if ($dryRun) {
            $response .= "🔍 **DRY RUN MODE** - No changes were applied\n\n";
        } else {
            $response .= "✅ **UPGRADE COMPLETED** - Changes have been applied\n\n";
        }

        // Summary
        $response .= "## Summary\n\n";
        $response .= "- **Repositories Found**: {$report['summary']['repositories_found']}\n";

        $needsAttributeMigration = array_filter($report['repositories'], fn ($r) => $r['needs_attribute_migration']);
        $needsFieldMigration = array_filter($report['repositories'], fn ($r) => $r['needs_field_migration']);

        $response .= '- **Need Attribute Migration**: '.count($needsAttributeMigration)."\n";
        $response .= '- **Need Field Migration**: '.count($needsFieldMigration)."\n";
        $response .= '- **Config Issues**: '.count($report['config_issues'])."\n\n";

        // Repository Analysis
        if (! empty($report['repositories'])) {
            $response .= "## Repository Analysis\n\n";

            foreach ($report['repositories'] as $repoPath => $analysis) {
                $repoName = basename($repoPath, '.php');
                $response .= "### $repoName\n\n";
                $response .= "**Path**: `$repoPath`\n";
                $response .= "**Complexity Score**: {$analysis['complexity_score']}\n\n";

                if ($analysis['needs_attribute_migration']) {
                    $response .= "🔄 **Needs Attribute Migration**\n";
                    $response .= "- Current: `public static \$model = {$analysis['current_model']}`\n";
                    $response .= "- Migrate to: `#[Model({$analysis['current_model']})]`\n\n";
                }

                if ($analysis['needs_field_migration']) {
                    $response .= "🔄 **Needs Field Migration**\n";
                    if (! empty($analysis['static_search_fields'])) {
                        $response .= '- Search fields: '.implode(', ', $analysis['static_search_fields'])."\n";
                    }
                    if (! empty($analysis['static_sort_fields'])) {
                        $response .= '- Sort fields: '.implode(', ', $analysis['static_sort_fields'])."\n";
                    }
                    $response .= "\n";
                }

                if (! empty($analysis['issues'])) {
                    $response .= "⚠️ **Issues Found**:\n";
                    foreach ($analysis['issues'] as $issue) {
                        $response .= "- $issue\n";
                    }
                    $response .= "\n";
                }

                if (! $dryRun && isset($report['changes_applied'][$repoPath])) {
                    $response .= "✅ **Changes Applied**:\n";
                    foreach ($report['changes_applied'][$repoPath] as $change) {
                        $response .= "- $change\n";
                    }
                    $response .= "\n";
                }

                $response .= "---\n\n";
            }
        }

        // Config Issues
        if (! empty($report['config_issues'])) {
            $response .= "## Configuration Issues\n\n";

            foreach ($report['config_issues'] as $issue) {
                $response .= "❌ **{$issue['message']}**\n";
                $response .= "- Recommendation: {$issue['recommendation']}\n\n";
            }
        }

        // Recommendations
        if (! empty($report['recommendations'])) {
            $response .= "## Recommendations\n\n";

            foreach ($report['recommendations'] as $rec) {
                $priority = isset($rec['priority']) ? strtoupper($rec['priority']) : 'RECOMMENDED';
                $response .= "### {$rec['title']} [$priority]\n\n";
                $response .= "{$rec['description']}\n\n";

                if (isset($rec['command'])) {
                    $response .= "```bash\n{$rec['command']}\n```\n\n";
                }
            }
        }

        // Next Steps
        $response .= "## Next Steps\n\n";

        if ($dryRun) {
            $response .= "1. **Review this report** and plan your migration strategy\n";
            $response .= "2. **Run with dry_run=false** to apply changes\n";
            $response .= "3. **Test thoroughly** after applying changes\n";
        } else {
            $response .= "1. **Test your application** to ensure everything works\n";
            $response .= "2. **Update your composer.json** to require Laravel Restify ^10.0\n";
            $response .= "3. **Run composer update** to get the latest version\n";
        }

        $response .= "4. **Update config file** if issues were found\n";
        $response .= "5. **Update your documentation** to reflect the new syntax\n\n";

        // Backup Information
        if (! empty($report['backups_created'])) {
            $response .= "## Backups Created\n\n";
            foreach ($report['backups_created'] as $backup) {
                $response .= "- `$backup`\n";
            }
            $response .= "\n";
        }

        $response .= "## Additional Resources\n\n";
        $response .= "- **Migration Guide**: Review the full v9→v10 upgrade documentation\n";
        $response .= "- **PHP Attributes**: Modern way to define model relationships\n";
        $response .= "- **Field-Level Config**: Better organization and discoverability\n";
        $response .= "- **Backward Compatibility**: All existing code continues to work\n";

        return ToolResult::text($response);
    }
}
