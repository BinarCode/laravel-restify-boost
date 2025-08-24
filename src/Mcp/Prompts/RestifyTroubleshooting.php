<?php

declare(strict_types=1);

namespace BinarCode\LaravelRestifyMcp\Mcp\Prompts;

use BinarCode\LaravelRestifyMcp\Services\DocIndexer;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\PromptInputSchema;
use Laravel\Mcp\Server\Prompts\PromptResult;

class RestifyTroubleshooting extends Prompt
{
    public function __construct(protected DocIndexer $indexer) {}

    public function name(): string
    {
        return 'restify-troubleshooting';
    }

    public function description(): string
    {
        return 'Get help troubleshooting Laravel Restify issues. Provide error messages, describe problems, or ask about common issues to receive targeted solutions and debugging guidance. This prompt helps diagnose and resolve configuration, runtime, and implementation problems.';
    }

    public function schema(PromptInputSchema $schema): PromptInputSchema
    {
        return $schema
            ->string('issue')
            ->description('Describe the problem you\'re experiencing or paste the error message')
            ->required()
            ->string('context')
            ->description('Additional context: what you were trying to do, recent changes, environment details, etc.')
            ->optional()
            ->string('error_type')
            ->description('Type of issue: "error", "performance", "configuration", "unexpected_behavior", or "other"')
            ->optional()
            ->string('code_snippet')
            ->description('Relevant code snippet where the issue occurs')
            ->optional();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function handle(array $arguments): PromptResult
    {
        try {
            $issue = trim($arguments['issue']);
            $context = $arguments['context'] ?? '';
            $errorType = strtolower($arguments['error_type'] ?? 'other');
            $codeSnippet = $arguments['code_snippet'] ?? '';

            if (empty($issue)) {
                return PromptResult::text('Please describe the issue you\'re experiencing with Laravel Restify.');
            }

            // Initialize indexer
            $this->initializeIndexer();

            // Analyze the issue and generate troubleshooting guidance
            $troubleshootingGuide = $this->generateTroubleshootingGuide($issue, $context, $errorType, $codeSnippet);

            return PromptResult::text($troubleshootingGuide);
        } catch (\Throwable $e) {
            return PromptResult::text("I encountered an error while generating troubleshooting guidance: {$e->getMessage()}");
        }
    }

    protected function initializeIndexer(): void
    {
        $paths = $this->getDocumentationPaths();
        $this->indexer->indexDocuments($paths);
    }

    protected function getDocumentationPaths(): array
    {
        $paths = [];
        $docsPath = config('restify-mcp.docs.paths.primary');
        $legacyPath = config('restify-mcp.docs.paths.legacy');

        foreach ([$docsPath, $legacyPath] as $basePath) {
            if (is_dir($basePath)) {
                $paths = array_merge($paths, $this->scanDirectoryForMarkdown($basePath));
            }
        }

        return $paths;
    }

    protected function scanDirectoryForMarkdown(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    protected function generateTroubleshootingGuide(string $issue, string $context, string $errorType, string $codeSnippet): string
    {
        $guide = "# Laravel Restify Troubleshooting Guide\n\n";
        $guide .= "**Issue:** {$issue}\n\n";

        if (! empty($context)) {
            $guide .= "**Context:** {$context}\n\n";
        }

        if (! empty($codeSnippet)) {
            $guide .= "**Code Context:**\n```php\n{$codeSnippet}\n```\n\n";
        }

        // Analyze the issue type
        $analysisResult = $this->analyzeIssue($issue, $errorType, $codeSnippet);

        $guide .= "## Diagnosis\n\n";
        $guide .= $analysisResult['diagnosis']."\n\n";

        $guide .= "## Possible Solutions\n\n";
        foreach ($analysisResult['solutions'] as $index => $solution) {
            $guide .= '### Solution '.($index + 1).": {$solution['title']}\n\n";
            $guide .= "{$solution['description']}\n\n";

            if (! empty($solution['code'])) {
                $guide .= "```php\n{$solution['code']}\n```\n\n";
            }

            if (! empty($solution['commands'])) {
                $guide .= "**Commands to run:**\n";
                foreach ($solution['commands'] as $command) {
                    $guide .= "```bash\n{$command}\n```\n";
                }
                $guide .= "\n";
            }
        }

        // Add debugging steps
        $guide .= $this->generateDebuggingSteps($analysisResult['category']);

        // Add prevention tips
        $guide .= $this->generatePreventionTips($analysisResult['category']);

        // Search for related documentation
        $searchResults = $this->indexer->search($issue, null, 3);
        if (! empty($searchResults)) {
            $guide .= "## Related Documentation\n\n";
            foreach ($searchResults as $result) {
                $doc = $result['document'];
                $guide .= "- **{$doc['title']}** ({$doc['category']}): {$doc['summary']}\n";
            }
            $guide .= "\n";
        }

        $guide .= "## Still Having Issues?\n\n";
        $guide .= "If these solutions don't resolve your problem:\n\n";
        $guide .= "1. Check the Laravel Restify GitHub issues for similar problems\n";
        $guide .= "2. Enable debug mode and check Laravel logs\n";
        $guide .= "3. Use `search-restify-docs` for more specific documentation\n";
        $guide .= "4. Consider asking on the Laravel community forums\n";

        return $guide;
    }

    protected function analyzeIssue(string $issue, string $errorType, string $codeSnippet): array
    {
        $issueLower = strtolower($issue);
        $category = 'general';
        $diagnosis = 'General Laravel Restify issue that needs investigation.';
        $solutions = [];

        // Route/Endpoint Issues
        if (str_contains($issueLower, 'route') || str_contains($issueLower, '404') || str_contains($issueLower, 'not found')) {
            $category = 'routing';
            $diagnosis = 'This appears to be a routing issue. The API endpoint may not be properly registered or accessible.';
            $solutions = [
                [
                    'title' => 'Verify Repository Registration',
                    'description' => 'Ensure your repository is properly registered in your service provider or routes file.',
                    'code' => '// In a service provider or routes/api.php
Restify::repositories([
    App\\Restify\\YourRepository::class,
]);',
                ],
                [
                    'title' => 'Check Route Caching',
                    'description' => 'Clear route cache if you\'re not seeing new routes.',
                    'commands' => ['php artisan route:clear'],
                ],
                [
                    'title' => 'Verify API Middleware',
                    'description' => 'Check if the correct middleware is applied to your API routes.',
                ],
            ];
        }

        // Authentication Issues
        elseif (str_contains($issueLower, 'auth') || str_contains($issueLower, 'unauthorized') || str_contains($issueLower, '401')) {
            $category = 'authentication';
            $diagnosis = 'This is an authentication or authorization issue. The request is being rejected due to insufficient permissions.';
            $solutions = [
                [
                    'title' => 'Check Repository Authorization',
                    'description' => 'Verify your repository\'s authorize method is correctly implemented.',
                    'code' => 'public function authorize(RestifyRequest $request): bool
{
    return $request->user() !== null;
    // Or implement more specific logic
}',
                ],
                [
                    'title' => 'Verify Authentication Middleware',
                    'description' => 'Ensure the correct authentication middleware is applied.',
                ],
                [
                    'title' => 'Check API Token/Session',
                    'description' => 'Verify that authentication credentials are being passed correctly in requests.',
                ],
            ];
        }

        // Validation Issues
        elseif (str_contains($issueLower, 'validation') || str_contains($issueLower, '422') || str_contains($issueLower, 'required')) {
            $category = 'validation';
            $diagnosis = 'This is a validation error. Input data doesn\'t meet the defined validation rules.';
            $solutions = [
                [
                    'title' => 'Review Field Validation Rules',
                    'description' => 'Check the validation rules defined in your repository fields.',
                    'code' => 'field(\'email\')
    ->rules(\'required|email|unique:users,email,{{resourceId}}\')
    ->creationRules(\'unique:users,email\')
    ->updateRules(\'unique:users,email,{{resourceId}}\')',
                ],
                [
                    'title' => 'Check Request Data Format',
                    'description' => 'Ensure the request data matches the expected field names and formats.',
                ],
            ];
        }

        // Database/Model Issues
        elseif (str_contains($issueLower, 'model') || str_contains($issueLower, 'database') || str_contains($issueLower, 'query')) {
            $category = 'database';
            $diagnosis = 'This appears to be a database or model-related issue.';
            $solutions = [
                [
                    'title' => 'Verify Model Configuration',
                    'description' => 'Check that your repository is pointing to the correct model.',
                    'code' => 'class YourRepository extends Repository
{
    public static $model = App\\Models\\YourModel::class;
}',
                ],
                [
                    'title' => 'Check Database Connection',
                    'description' => 'Verify your database connection is working.',
                    'commands' => ['php artisan migrate:status'],
                ],
                [
                    'title' => 'Review Model Relationships',
                    'description' => 'Ensure model relationships are properly defined if using relationship fields.',
                ],
            ];
        }

        // Field/Display Issues
        elseif (str_contains($issueLower, 'field') || str_contains($issueLower, 'display') || str_contains($issueLower, 'show')) {
            $category = 'fields';
            $diagnosis = 'This is related to field configuration or display issues.';
            $solutions = [
                [
                    'title' => 'Check Field Definition',
                    'description' => 'Verify your fields are properly defined in the repository.',
                    'code' => 'public function fields(RestifyRequest $request): array
{
    return [
        field(\'name\')->rules(\'required\'),
        // Add other fields here
    ];
}',
                ],
                [
                    'title' => 'Review Field Visibility Rules',
                    'description' => 'Check if fields are hidden based on visibility rules.',
                ],
            ];
        }

        // Performance Issues
        elseif (str_contains($issueLower, 'slow') || str_contains($issueLower, 'performance') || str_contains($issueLower, 'timeout')) {
            $category = 'performance';
            $diagnosis = 'This appears to be a performance issue that may require optimization.';
            $solutions = [
                [
                    'title' => 'Optimize Database Queries',
                    'description' => 'Review and optimize database queries, add eager loading for relationships.',
                    'code' => '// In your repository
public function with(): array
{
    return [\'relation1\', \'relation2\'];
}',
                ],
                [
                    'title' => 'Add Pagination',
                    'description' => 'Ensure proper pagination is configured for large datasets.',
                ],
                [
                    'title' => 'Review Indexing',
                    'description' => 'Check database indexes for frequently queried fields.',
                ],
            ];
        }

        // Configuration Issues
        else {
            $category = 'configuration';
            $diagnosis = 'This may be a configuration issue. Check your setup and environment.';
            $solutions = [
                [
                    'title' => 'Verify Package Installation',
                    'description' => 'Ensure Laravel Restify is properly installed and configured.',
                    'commands' => ['composer require binaryk/laravel-restify'],
                ],
                [
                    'title' => 'Check Service Provider',
                    'description' => 'Verify the Restify service provider is registered.',
                ],
                [
                    'title' => 'Clear Application Cache',
                    'description' => 'Clear various caches that might be causing issues.',
                    'commands' => [
                        'php artisan config:clear',
                        'php artisan cache:clear',
                        'php artisan view:clear',
                    ],
                ],
            ];
        }

        return [
            'category' => $category,
            'diagnosis' => $diagnosis,
            'solutions' => $solutions,
        ];
    }

    protected function generateDebuggingSteps(string $category): string
    {
        $steps = "## Debugging Steps\n\n";

        $steps .= "1. **Enable Debug Mode**\n";
        $steps .= "   - Set `APP_DEBUG=true` in your `.env` file\n";
        $steps .= "   - Check `storage/logs/laravel.log` for detailed error messages\n\n";

        $steps .= "2. **Test API Endpoints**\n";
        $steps .= "   - Use tools like Postman or curl to test your API endpoints\n";
        $steps .= "   - Check request/response headers and data format\n\n";

        if (in_array($category, ['routing', 'authentication'])) {
            $steps .= "3. **Route Debugging**\n";
            $steps .= "   - Run `php artisan route:list` to see all registered routes\n";
            $steps .= "   - Look for your repository routes in the output\n\n";
        }

        if (in_array($category, ['database', 'fields'])) {
            $steps .= "3. **Database Debugging**\n";
            $steps .= "   - Enable query logging to see generated SQL\n";
            $steps .= "   - Check database connection and table structure\n\n";
        }

        $steps .= "4. **Check Dependencies**\n";
        $steps .= "   - Verify all required packages are installed\n";
        $steps .= "   - Check for version compatibility issues\n\n";

        return $steps;
    }

    protected function generatePreventionTips(string $category): string
    {
        $tips = "## Prevention Tips\n\n";

        $tips .= "To avoid similar issues in the future:\n\n";
        $tips .= "- Always test changes in a development environment first\n";
        $tips .= "- Follow Laravel Restify documentation and best practices\n";
        $tips .= "- Use proper version control and track configuration changes\n";
        $tips .= "- Implement proper error handling and logging\n";

        if ($category === 'authentication') {
            $tips .= "- Test authorization logic thoroughly\n";
            $tips .= "- Use Laravel policies for complex authorization rules\n";
        }

        if ($category === 'performance') {
            $tips .= "- Monitor database query performance\n";
            $tips .= "- Use Laravel Telescope for debugging in development\n";
        }

        if ($category === 'validation') {
            $tips .= "- Write unit tests for validation rules\n";
            $tips .= "- Use form request classes for complex validation\n";
        }

        $tips .= "\n";

        return $tips;
    }
}
