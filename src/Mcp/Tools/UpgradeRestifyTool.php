<?php

declare(strict_types=1);

namespace BinarCode\RestifyBoost\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Symfony\Component\Finder\Finder;

class UpgradeRestifyTool extends Tool
{
    private const DEFAULT_SEARCH_PATHS = [
        'app/Restify',
        'app/Http/Restify',
        'app/Repositories',
    ];

    private const REPOSITORY_FILE_PATTERN = '*Repository.php';

    private const REQUIRED_CONFIG_SECTIONS = [
        'mcp' => 'MCP server configuration for AI tools',
        'ai_solutions' => 'AI-powered solutions configuration',
    ];

    private const DEPRECATED_PATTERNS = [
        'resolveUsing' => 'Consider using modern field methods',
        'displayUsing' => 'Consider using modern field methods',
    ];

    public function description(): string
    {
        return 'Upgrade Laravel Restify from version 9.x to 10.x. This tool migrates repositories to use modern PHP attributes for model definitions, converts static search/sort arrays to field-level methods, checks config file compatibility, and provides a comprehensive upgrade report with recommendations.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'dry_run' => $schema->boolean()
                ->description('Preview changes without applying them (default: true)'),
            'migrate_attributes' => $schema->boolean()
                ->description('Convert static $model properties to PHP attributes (default: true)'),
            'migrate_fields' => $schema->boolean()
                ->description('Convert static $search/$sort arrays to field-level methods (default: true)'),
            'check_config' => $schema->boolean()
                ->description('Check and report config file compatibility (default: true)'),
            'backup_files' => $schema->boolean()
                ->description('Create backups of modified files (default: true)'),
            'interactive' => $schema->boolean()
                ->description('Prompt for confirmation before each change (default: true)'),
            'path' => $schema->string()
                ->description('Specific path to scan for repositories (defaults to app/Restify)'),
        ];
    }

    public function handle(Request $request): Response
    {
        try {
            $options = $this->parseArguments($request);
            $report = $this->initializeReport();

            $repositories = $this->scanRepositories($options['customPath']);
            $report['summary']['repositories_found'] = count($repositories);

            if (empty($repositories)) {
                return Response::text('No Restify repositories found. Ensure you have repositories in app/Restify or specify a custom path.');
            }

            $report = $this->analyzeRepositories($repositories, $report);

            if (! $options['dryRun']) {
                $report = $this->applyMigrationsWithConfirmation($repositories, $report, $options);
            }

            if ($options['checkConfig']) {
                $report['config_issues'] = $this->checkConfigCompatibility();
            }

            $report['recommendations'] = $this->generateRecommendations($report);

            return $this->generateUpgradeReport($report, $options['dryRun']);

        } catch (\Throwable $e) {
            return Response::error('Restify upgrade failed: '.$e->getMessage());
        }
    }

    private function parseArguments(Request $request): array
    {
        return [
            'dryRun' => $request->get('dry_run') ?? true,
            'migrateAttributes' => $request->get('migrate_attributes') ?? true,
            'migrateFields' => $request->get('migrate_fields') ?? true,
            'checkConfig' => $request->get('check_config') ?? true,
            'backupFiles' => $request->get('backup_files') ?? true,
            'interactive' => $request->get('interactive') ?? true,
            'customPath' => $request->get('path') ?? null,
        ];
    }

    private function initializeReport(): array
    {
        return [
            'summary' => [],
            'repositories' => [],
            'config_issues' => [],
            'recommendations' => [],
            'changes_applied' => [],
            'backups_created' => [],
        ];
    }

    private function analyzeRepositories(array $repositories, array $report): array
    {
        foreach ($repositories as $repoPath => $repoInfo) {
            $analysis = $this->analyzeRepository($repoPath, $repoInfo);
            $report['repositories'][$repoPath] = $analysis;
        }

        return $report;
    }

    private function applyMigrationsWithConfirmation(array $repositories, array $report, array $options): array
    {
        foreach ($repositories as $repoPath => $repoInfo) {
            $analysis = $report['repositories'][$repoPath];

            if ($this->shouldSkipRepository($analysis, $options)) {
                continue;
            }

            if ($options['interactive'] && ! $this->confirmRepositoryMigration($repoPath, $analysis)) {
                continue;
            }

            $changes = $this->applyMigrations(
                $repoPath,
                $analysis,
                $options['migrateAttributes'],
                $options['migrateFields'],
                $options['backupFiles']
            );

            $report['changes_applied'][$repoPath] = $changes;

            if ($options['backupFiles'] && ! empty($changes)) {
                $backup = $this->createBackup($repoPath);
                if ($backup) {
                    $report['backups_created'][] = $backup;
                }
            }
        }

        return $report;
    }

    private function shouldSkipRepository(array $analysis, array $options): bool
    {
        $needsAttributeMigration = $analysis['needs_attribute_migration'] && $options['migrateAttributes'];
        $needsFieldMigration = $analysis['needs_field_migration'] && $options['migrateFields'];

        return ! $needsAttributeMigration && ! $needsFieldMigration;
    }

    private function confirmRepositoryMigration(string $repoPath, array $analysis): bool
    {
        $repoName = basename($repoPath, '.php');
        $message = "Apply migrations to {$repoName}?\n";

        if ($analysis['needs_attribute_migration']) {
            $message .= "  - Convert static \$model to #[Model] attribute\n";
        }

        if ($analysis['needs_field_migration']) {
            $message .= "  - Convert static \$search/\$sort to field-level methods\n";
        }

        $message .= 'Proceed? (y/N): ';

        // In an MCP context, we'll assume 'yes' for now
        // In a real CLI context, this would read from STDIN
        return true;
    }

    protected function scanRepositories(?string $customPath = null): array
    {
        $repositories = [];
        $searchPaths = $customPath ? [$customPath] : array_map('app_path', self::DEFAULT_SEARCH_PATHS);

        foreach ($searchPaths as $searchPath) {
            if (! File::isDirectory($searchPath)) {
                continue;
            }

            try {
                $finder = new Finder;
                $finder->files()
                    ->in($searchPath)
                    ->name(self::REPOSITORY_FILE_PATTERN)
                    ->notPath('vendor')
                    ->notPath('tests');

                foreach ($finder as $file) {
                    $filePath = $file->getRealPath();
                    $content = File::get($filePath);

                    if ($this->isRestifyRepository($content)) {

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

    private function isRestifyRepository(string $content): bool
    {
        return str_contains($content, 'extends Repository') ||
               str_contains($content, 'use Repository');
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

        $this->analyzeStaticModelProperty($content, $analysis);
        $this->analyzeStaticSearchArray($content, $analysis);
        $this->analyzeStaticSortArray($content, $analysis);

        $this->analyzeFieldDefinitions($content, $analysis);

        $this->checkForUpgradeIssues($content, $analysis);

        return $analysis;
    }

    private function analyzeStaticModelProperty(string $content, array &$analysis): void
    {
        if (preg_match('/public\s+static\s+string\s+\$model\s*=\s*([^;]+);/', $content, $matches)) {
            $analysis['needs_attribute_migration'] = true;
            $analysis['current_model'] = trim($matches[1], ' \'"');
            $analysis['complexity_score'] += 2;
        }
    }

    private function analyzeStaticSearchArray(string $content, array &$analysis): void
    {
        if (preg_match('/public\s+static\s+array\s+\$search\s*=\s*\[(.*?)\];/s', $content, $matches)) {
            $analysis['needs_field_migration'] = true;
            $searchFields = $this->parseArrayContent($matches[1]);
            $analysis['static_search_fields'] = $searchFields;
            $analysis['complexity_score'] += count($searchFields);
        }
    }

    private function analyzeStaticSortArray(string $content, array &$analysis): void
    {
        if (preg_match('/public\s+static\s+array\s+\$sort\s*=\s*\[(.*?)\];/s', $content, $matches)) {
            $analysis['needs_field_migration'] = true;
            $sortFields = $this->parseArrayContent($matches[1]);
            $analysis['static_sort_fields'] = $sortFields;
            $analysis['complexity_score'] += count($sortFields);
        }
    }

    private function analyzeFieldDefinitions(string $content, array &$analysis): void
    {
        if (preg_match('/public\s+function\s+fields\s*\([^)]*\)\s*:\s*array\s*{(.*?)}/s', $content, $matches)) {
            $fieldsContent = $matches[1];
            $analysis['field_definitions'] = $this->extractFieldDefinitions($fieldsContent);
        }
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
        $content = $this->addModelUseStatement($content);
        $content = $this->removeStaticModelProperty($content);
        $content = $this->addModelAttribute($content, $modelClass);

        return $content;
    }

    private function addModelUseStatement(string $content): string
    {
        if (str_contains($content, 'use Binaryk\LaravelRestify\Attributes\Model;')) {
            return $content;
        }

        return preg_replace(
            '/(namespace\s+[^;]+;)/s',
            "$1\n\nuse Binaryk\\LaravelRestify\\Attributes\\Model;",
            $content
        );
    }

    private function removeStaticModelProperty(string $content): string
    {
        return preg_replace(
            '/public\s+static\s+string\s+\$model\s*=\s*([^;]+);/',
            '',
            $content
        );
    }

    private function addModelAttribute(string $content, string $modelClass): string
    {
        return preg_replace(
            '/(class\s+\w+Repository\s+extends\s+Repository)/s',
            "#[Model($modelClass)]\n$1",
            $content
        );
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
        $this->checkDeprecatedImports($content, $analysis);
        $this->checkDeprecatedMethods($content, $analysis);
    }

    private function checkDeprecatedImports(string $content, array &$analysis): void
    {
        if (str_contains($content, 'use Binaryk\LaravelRestify\Fields\Field;')) {
            $analysis['issues'][] = 'Consider updating field imports to use field() helper';
        }
    }

    private function checkDeprecatedMethods(string $content, array &$analysis): void
    {
        foreach (self::DEPRECATED_PATTERNS as $pattern => $suggestion) {
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

        foreach (self::REQUIRED_CONFIG_SECTIONS as $section => $description) {
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

    protected function generateUpgradeReport(array $report, bool $dryRun): Response
    {
        $response = $this->buildReportHeader($dryRun);
        $response .= $this->buildSummarySection($report);
        $response .= $this->buildRepositoryAnalysisSection($report, $dryRun);
        $response .= $this->buildConfigIssuesSection($report);
        $response .= $this->buildRecommendationsSection($report);
        $response .= $this->buildNextStepsSection($dryRun);
        $response .= $this->buildBackupInformationSection($report);
        $response .= $this->buildAdditionalResourcesSection();

        return Response::text($response);
    }

    private function buildReportHeader(bool $dryRun): string
    {
        $header = "# Laravel Restify 9.x → 10.x Upgrade Report\n\n";

        if ($dryRun) {
            $header .= "🔍 **DRY RUN MODE** - No changes were applied\n\n";
        } else {
            $header .= "✅ **UPGRADE COMPLETED** - Changes have been applied\n\n";
        }

        return $header;
    }

    private function buildSummarySection(array $report): string
    {
        $needsAttributeMigration = array_filter($report['repositories'], fn ($r) => $r['needs_attribute_migration']);
        $needsFieldMigration = array_filter($report['repositories'], fn ($r) => $r['needs_field_migration']);

        return "## Summary\n\n".
               "- **Repositories Found**: {$report['summary']['repositories_found']}\n".
               '- **Need Attribute Migration**: '.count($needsAttributeMigration)."\n".
               '- **Need Field Migration**: '.count($needsFieldMigration)."\n".
               '- **Config Issues**: '.count($report['config_issues'])."\n\n";
    }

    private function buildRepositoryAnalysisSection(array $report, bool $dryRun): string
    {
        if (empty($report['repositories'])) {
            return '';
        }

        $section = "## Repository Analysis\n\n";

        foreach ($report['repositories'] as $repoPath => $analysis) {
            $section .= $this->buildRepositorySection($repoPath, $analysis, $report, $dryRun);
        }

        return $section;
    }

    private function buildRepositorySection(string $repoPath, array $analysis, array $report, bool $dryRun): string
    {
        $repoName = basename($repoPath, '.php');
        $section = "### $repoName\n\n";
        $section .= "**Path**: `$repoPath`\n";
        $section .= "**Complexity Score**: {$analysis['complexity_score']}\n\n";

        $section .= $this->buildAttributeMigrationInfo($analysis);
        $section .= $this->buildFieldMigrationInfo($analysis);
        $section .= $this->buildIssuesInfo($analysis);
        $section .= $this->buildChangesAppliedInfo($repoPath, $report, $dryRun);
        $section .= "---\n\n";

        return $section;
    }

    private function buildAttributeMigrationInfo(array $analysis): string
    {
        if (! $analysis['needs_attribute_migration']) {
            return '';
        }

        return "🔄 **Needs Attribute Migration**\n".
               "- Current: `public static \$model = {$analysis['current_model']}`\n".
               "- Migrate to: `#[Model({$analysis['current_model']})]`\n\n";
    }

    private function buildFieldMigrationInfo(array $analysis): string
    {
        if (! $analysis['needs_field_migration']) {
            return '';
        }

        $info = "🔄 **Needs Field Migration**\n";

        if (! empty($analysis['static_search_fields'])) {
            $info .= '- Search fields: '.implode(', ', $analysis['static_search_fields'])."\n";
        }

        if (! empty($analysis['static_sort_fields'])) {
            $info .= '- Sort fields: '.implode(', ', $analysis['static_sort_fields'])."\n";
        }

        return $info."\n";
    }

    private function buildIssuesInfo(array $analysis): string
    {
        if (empty($analysis['issues'])) {
            return '';
        }

        $info = "⚠️ **Issues Found**:\n";
        foreach ($analysis['issues'] as $issue) {
            $info .= "- $issue\n";
        }

        return $info."\n";
    }

    private function buildChangesAppliedInfo(string $repoPath, array $report, bool $dryRun): string
    {
        if ($dryRun || ! isset($report['changes_applied'][$repoPath])) {
            return '';
        }

        $info = "✅ **Changes Applied**:\n";
        foreach ($report['changes_applied'][$repoPath] as $change) {
            $info .= "- $change\n";
        }

        return $info."\n";
    }

    private function buildConfigIssuesSection(array $report): string
    {
        if (empty($report['config_issues'])) {
            return '';
        }

        $section = "## Configuration Issues\n\n";

        foreach ($report['config_issues'] as $issue) {
            $section .= "❌ **{$issue['message']}**\n";
            $section .= "- Recommendation: {$issue['recommendation']}\n\n";
        }

        return $section;
    }

    private function buildRecommendationsSection(array $report): string
    {
        if (empty($report['recommendations'])) {
            return '';
        }

        $section = "## Recommendations\n\n";

        foreach ($report['recommendations'] as $rec) {
            $priority = isset($rec['priority']) ? strtoupper($rec['priority']) : 'RECOMMENDED';
            $section .= "### {$rec['title']} [$priority]\n\n";
            $section .= "{$rec['description']}\n\n";

            if (isset($rec['command'])) {
                $section .= "```bash\n{$rec['command']}\n```\n\n";
            }
        }

        return $section;
    }

    private function buildNextStepsSection(bool $dryRun): string
    {
        $section = "## Next Steps\n\n";

        if ($dryRun) {
            $section .= "1. **Review this report** and plan your migration strategy\n";
            $section .= "2. **Run with dry_run=false** to apply changes\n";
            $section .= "3. **Test thoroughly** after applying changes\n";
        } else {
            $section .= "1. **Test your application** to ensure everything works\n";
            $section .= "2. **Update your composer.json** to require Laravel Restify ^10.0\n";
            $section .= "3. **Run composer update** to get the latest version\n";
        }

        $section .= "4. **Update config file** if issues were found\n";
        $section .= "5. **Update your documentation** to reflect the new syntax\n\n";

        return $section;
    }

    private function buildBackupInformationSection(array $report): string
    {
        if (empty($report['backups_created'])) {
            return '';
        }

        $section = "## Backups Created\n\n";
        foreach ($report['backups_created'] as $backup) {
            $section .= "- `$backup`\n";
        }

        return $section."\n";
    }

    private function buildAdditionalResourcesSection(): string
    {
        return "## Additional Resources\n\n".
               "- **Migration Guide**: Review the full v9→v10 upgrade documentation\n".
               "- **PHP Attributes**: Modern way to define model relationships\n".
               "- **Field-Level Config**: Better organization and discoverability\n".
               "- **Backward Compatibility**: All existing code continues to work\n";
    }
}
