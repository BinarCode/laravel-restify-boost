<?php

declare(strict_types=1);

namespace BinarCode\RestifyBoost\Mcp\Prompts;

use BinarCode\RestifyBoost\Services\DocIndexer;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;

class RestifyHowTo extends Prompt
{
    public function __construct(protected DocIndexer $indexer) {}

    /**
     * The prompt's name.
     */
    protected string $name = 'restify-how-to';

    /**
     * The prompt's title.
     */
    protected string $title = 'Laravel Restify How-To Guide';

    /**
     * Get the prompt's arguments.
     *
     * @return array<int, \Laravel\Mcp\Server\Prompts\Argument>
     */
    public function arguments(): array
    {
        return [
            new \Laravel\Mcp\Server\Prompts\Argument(
                name: 'task',
                description: 'What you want to accomplish (e.g., "create a repository", "add custom validation", "implement authentication", "create a custom field")',
                required: true
            ),
            new \Laravel\Mcp\Server\Prompts\Argument(
                name: 'context',
                description: 'Additional context about your specific use case or requirements',
                required: false
            ),
            new \Laravel\Mcp\Server\Prompts\Argument(
                name: 'difficulty',
                description: 'Preferred explanation level: "beginner", "intermediate", "advanced"',
                required: false
            ),
        ];
    }

    /**
     * Handle the prompt request.
     */
    public function handle(Request $request): Response
    {
        try {
            $validated = $request->validate([
                'task' => 'required|string|max:200',
                'context' => 'nullable|string|max:500',
                'difficulty' => 'nullable|string|in:beginner,intermediate,advanced',
            ]);

            $task = trim($validated['task']);
            $context = $validated['context'] ?? '';
            $difficulty = strtolower($validated['difficulty'] ?? 'intermediate');

            if (empty($task)) {
                return Response::text('Please specify what task you want to accomplish with Laravel Restify.');
            }

            // Initialize indexer
            $this->initializeIndexer();

            // Search for relevant documentation
            $searchResults = $this->indexer->search($task, null, 10);

            // Generate structured how-to guide
            $howToGuide = $this->generateHowToGuide($task, $context, $difficulty, $searchResults);

            return Response::text($howToGuide);
        } catch (\Throwable $e) {
            return Response::text("I encountered an error while generating the how-to guide: {$e->getMessage()}");
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
        $docsPath = config('restify-boost.docs.paths.primary');
        $legacyPath = config('restify-boost.docs.paths.legacy');

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

    protected function generateHowToGuide(string $task, string $context, string $difficulty, array $searchResults): string
    {
        $guide = "# How to: {$task} with Laravel Restify\n\n";

        if (! empty($context)) {
            $guide .= "**Your context:** {$context}\n\n";
        }

        $guide .= '**Difficulty level:** '.ucfirst($difficulty)."\n\n";

        if (empty($searchResults)) {
            return $this->generateGenericGuide($task, $difficulty);
        }

        // Extract relevant information from search results
        $relevantInfo = $this->extractRelevantInformation($searchResults, $task);

        // Generate step-by-step guide based on difficulty
        $guide .= $this->generateStepByStepGuide($task, $difficulty, $relevantInfo);

        // Add code examples
        if (! empty($relevantInfo['code_examples'])) {
            $guide .= "\n## Code Examples\n\n";
            foreach (array_slice($relevantInfo['code_examples'], 0, 3) as $example) {
                $guide .= "```{$example['language']}\n{$example['code']}\n```\n\n";
            }
        }

        // Add tips and best practices
        $guide .= $this->generateTipsAndBestPractices($task, $difficulty);

        // Add troubleshooting section
        $guide .= $this->generateTroubleshootingSection($task);

        // Add next steps
        $guide .= $this->generateNextSteps($task, $relevantInfo);

        return $guide;
    }

    protected function extractRelevantInformation(array $searchResults, string $task): array
    {
        $info = [
            'steps' => [],
            'code_examples' => [],
            'concepts' => [],
            'related_topics' => [],
        ];

        foreach ($searchResults as $result) {
            $doc = $result['document'];

            // Extract code examples
            if (! empty($result['matched_code_examples'])) {
                $info['code_examples'] = array_merge(
                    $info['code_examples'],
                    $result['matched_code_examples']
                );
            }

            // Extract concepts from headings
            foreach ($result['matched_headings'] ?? [] as $heading) {
                if (stripos($heading['text'], $task) !== false) {
                    $info['concepts'][] = $heading['text'];
                }
            }

            // Track related topics
            $info['related_topics'][] = $doc['title'];
        }

        // Remove duplicates and limit
        $info['code_examples'] = array_slice(array_unique($info['code_examples'], SORT_REGULAR), 0, 5);
        $info['concepts'] = array_unique($info['concepts']);
        $info['related_topics'] = array_unique($info['related_topics']);

        return $info;
    }

    protected function generateStepByStepGuide(string $task, string $difficulty, array $relevantInfo): string
    {
        $guide = "## Step-by-Step Guide\n\n";

        // Generate task-specific steps based on common patterns
        $steps = $this->getTaskSpecificSteps($task, $difficulty);

        foreach ($steps as $index => $step) {
            $stepNumber = $index + 1;
            $guide .= "### Step {$stepNumber}: {$step['title']}\n\n";
            $guide .= "{$step['description']}\n\n";

            if (! empty($step['code'])) {
                $guide .= "```php\n{$step['code']}\n```\n\n";
            }

            if (! empty($step['notes']) && $difficulty !== 'beginner') {
                $guide .= "**Note:** {$step['notes']}\n\n";
            }
        }

        return $guide;
    }

    protected function getTaskSpecificSteps(string $task, string $difficulty): array
    {
        $taskLower = strtolower($task);

        if (str_contains($taskLower, 'repository') || str_contains($taskLower, 'create')) {
            return $this->getRepositorySteps($difficulty);
        }

        if (str_contains($taskLower, 'field')) {
            return $this->getFieldSteps($difficulty);
        }

        if (str_contains($taskLower, 'action')) {
            return $this->getActionSteps($difficulty);
        }

        if (str_contains($taskLower, 'auth') || str_contains($taskLower, 'permission')) {
            return $this->getAuthSteps($difficulty);
        }

        if (str_contains($taskLower, 'filter') || str_contains($taskLower, 'search')) {
            return $this->getFilterSteps($difficulty);
        }

        return $this->getGenericSteps($task, $difficulty);
    }

    protected function getRepositorySteps(string $difficulty): array
    {
        $steps = [
            [
                'title' => 'Create the Repository Class',
                'description' => 'Create a new repository class that extends the base Repository class.',
                'code' => '<?php

namespace App\\Restify;

use App\\Models\\User;
use Binaryk\\LaravelRestify\\Http\\Requests\\RestifyRequest;
use Binaryk\\LaravelRestify\\Repositories\\Repository;

class UserRepository extends Repository
{
    public static $model = User::class;
}',
            ],
            [
                'title' => 'Define Fields',
                'description' => 'Add the fields method to define which model attributes should be available via the API.',
                'code' => 'public function fields(RestifyRequest $request): array
{
    return [
        field(\'id\')->readonly(),
        field(\'name\')->rules(\'required|max:255\'),
        field(\'email\')->rules(\'required|email|unique:users,email,{{resourceId}}\'),
        field(\'created_at\')->readonly(),
    ];
}',
            ],
            [
                'title' => 'Register the Repository',
                'description' => 'Register your repository in a service provider or routes file.',
                'code' => '// In routes/api.php or a service provider
Restify::repositories([
    App\\Restify\\UserRepository::class,
]);',
            ],
        ];

        if ($difficulty === 'advanced') {
            $steps[] = [
                'title' => 'Add Advanced Features',
                'description' => 'Configure additional features like authorization, custom actions, and lifecycle hooks.',
                'code' => 'public function authorize(RestifyRequest $request): bool
{
    return $request->user()->can(\'viewAny\', static::$model);
}

public function beforeStore(RestifyRequest $request, $model)
{
    // Custom logic before storing
}',
                'notes' => 'You can also add filters, actions, and custom endpoints at this stage.',
            ];
        }

        return $steps;
    }

    protected function getFieldSteps(string $difficulty): array
    {
        return [
            [
                'title' => 'Choose Field Type',
                'description' => 'Select the appropriate field type for your data.',
                'code' => '// Common field types
field(\'name\'),                    // Text field
select(\'status\')->options([       // Select field
    \'active\' => \'Active\',
    \'inactive\' => \'Inactive\'
]),
boolean(\'is_active\'),            // Boolean field
belongsTo(\'user\', UserRepository::class), // Relationship',
            ],
            [
                'title' => 'Add Validation Rules',
                'description' => 'Define validation rules for your field.',
                'code' => 'field(\'email\')
    ->rules(\'required|email|unique:users,email,{{resourceId}}\')
    ->creationRules(\'unique:users,email\')
    ->updateRules(\'unique:users,email,{{resourceId}}\')',
            ],
            [
                'title' => 'Configure Field Behavior',
                'description' => 'Set visibility, searchability, and other field properties.',
                'code' => 'field(\'password\')
    ->rules(\'required|min:8\')
    ->hideFromIndex()
    ->hideFromShow()
    ->onlyOnForms()',
            ],
        ];
    }

    protected function getActionSteps(string $difficulty): array
    {
        return [
            [
                'title' => 'Create Action Class',
                'description' => 'Create a new action class extending the base Action class.',
                'code' => '<?php

namespace App\\Restify\\Actions;

use Binaryk\\LaravelRestify\\Actions\\Action;
use Binaryk\\LaravelRestify\\Http\\Requests\\ActionRequest;
use Illuminate\\Support\\Collection;

class PublishPost extends Action
{
    public function handle(ActionRequest $request, Collection $models)
    {
        $models->each(function ($model) {
            $model->update([\'published_at\' => now()]);
        });
    }
}',
            ],
            [
                'title' => 'Register the Action',
                'description' => 'Add the action to your repository\'s actions method.',
                'code' => 'public function actions(RestifyRequest $request): array
{
    return [
        new PublishPost,
    ];
}',
            ],
        ];
    }

    protected function getAuthSteps(string $difficulty): array
    {
        return [
            [
                'title' => 'Define Authorization',
                'description' => 'Implement authorization in your repository.',
                'code' => 'public function authorize(RestifyRequest $request): bool
{
    return $request->user()->can(\'viewAny\', static::$model);
}

public function authorizeToShow(RestifyRequest $request, $model): bool
{
    return $request->user()->can(\'view\', $model);
}',
            ],
        ];
    }

    protected function getFilterSteps(string $difficulty): array
    {
        return [
            [
                'title' => 'Define Searchable Fields',
                'description' => 'Make fields searchable in your repository.',
                'code' => 'public function search(): array
{
    return [
        \'name\',
        \'email\',
    ];
}',
            ],
            [
                'title' => 'Add Match Filters',
                'description' => 'Define exact match filters.',
                'code' => 'public function match(): array
{
    return [
        \'status\',
        \'category_id\',
    ];
}',
            ],
        ];
    }

    protected function getGenericSteps(string $task, string $difficulty): array
    {
        return [
            [
                'title' => 'Understand the Requirement',
                'description' => "First, identify what you want to achieve with: {$task}",
            ],
            [
                'title' => 'Check Documentation',
                'description' => 'Review the Laravel Restify documentation for specific guidance on your task.',
            ],
            [
                'title' => 'Implement the Solution',
                'description' => 'Follow the documented approach for your specific use case.',
            ],
        ];
    }

    protected function generateGenericGuide(string $task, string $difficulty): string
    {
        $guide = "# How to: {$task} with Laravel Restify\n\n";
        $guide .= "I don't have specific documentation for this task, but here's a general approach:\n\n";
        $guide .= "## General Steps\n\n";
        $guide .= "1. **Research the requirement**: Understand exactly what you need to accomplish\n";
        $guide .= "2. **Check the documentation**: Look for similar examples in the Laravel Restify docs\n";
        $guide .= "3. **Start with basics**: Begin with a simple implementation\n";
        $guide .= "4. **Iterate and improve**: Refine your solution based on requirements\n\n";
        $guide .= "Try using the `search-restify-docs` tool with more specific terms related to your task.\n";

        return $guide;
    }

    protected function generateTipsAndBestPractices(string $task, string $difficulty): string
    {
        $tips = "\n## Tips and Best Practices\n\n";
        $tips .= "- Always follow Laravel coding standards and conventions\n";
        $tips .= "- Test your implementation thoroughly\n";
        $tips .= "- Use descriptive names for classes and methods\n";
        $tips .= "- Consider performance implications for large datasets\n";
        $tips .= "- Implement proper error handling\n\n";

        return $tips;
    }

    protected function generateTroubleshootingSection(string $task): string
    {
        $troubleshooting = "## Common Issues\n\n";
        $troubleshooting .= "If you encounter problems:\n\n";
        $troubleshooting .= "1. Check Laravel logs for error details\n";
        $troubleshooting .= "2. Verify your model relationships are properly defined\n";
        $troubleshooting .= "3. Ensure proper authorization is in place\n";
        $troubleshooting .= "4. Use the `restify-troubleshooting` prompt for specific error messages\n\n";

        return $troubleshooting;
    }

    protected function generateNextSteps(string $task, array $relevantInfo): string
    {
        $nextSteps = "## Next Steps\n\n";
        $nextSteps .= "After completing this task, you might want to:\n\n";

        if (! empty($relevantInfo['related_topics'])) {
            $nextSteps .= "**Explore related topics:**\n";
            foreach (array_slice($relevantInfo['related_topics'], 0, 3) as $topic) {
                $nextSteps .= "- {$topic}\n";
            }
            $nextSteps .= "\n";
        }

        $nextSteps .= "**Additional tools:**\n";
        $nextSteps .= "- Use `search-restify-docs` for more detailed information\n";
        $nextSteps .= "- Use `get-code-examples` for more implementation examples\n";
        $nextSteps .= "- Use `navigate-docs` to explore documentation structure\n";

        return $nextSteps;
    }
}
