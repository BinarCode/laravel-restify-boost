<?php

declare(strict_types=1);

namespace BinarCode\LaravelRestifyMcp\Mcp\Tools;

use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolResult;

class GenerateMatchFilterTool extends Tool
{
    public function name(): string
    {
        return 'generate-match-filter';
    }

    public function description(): string
    {
        return 'Generate Laravel Restify match filter classes for exact matching and custom filtering logic. Supports all match types: string, int, bool, datetime, between, array, and custom filters with complex logic.';
    }

    public function parameters(): array
    {
        return [
            'name' => [
                'type' => 'string',
                'description' => 'The name of the match filter class (e.g., ActivePostMatchFilter)',
                'required' => true,
            ],
            'attribute' => [
                'type' => 'string',
                'description' => 'The database attribute/column to filter on (e.g., status, active, category)',
                'required' => true,
            ],
            'type' => [
                'type' => 'string',
                'description' => 'The match filter type',
                'enum' => ['string', 'int', 'integer', 'bool', 'boolean', 'datetime', 'between', 'array', 'custom'],
                'default' => 'string',
            ],
            'partial' => [
                'type' => 'boolean',
                'description' => 'Whether to use partial matching (LIKE queries) for text fields',
                'default' => false,
            ],
            'custom_logic' => [
                'type' => 'string',
                'description' => 'Custom filtering logic description for complex filters (only when type is custom)',
            ],
            'repository' => [
                'type' => 'string',
                'description' => 'The repository class name to add the filter to (optional)',
            ],
            'namespace' => [
                'type' => 'string',
                'description' => 'Custom namespace for the filter class',
                'default' => 'App\\Restify\\Filters',
            ],
        ];
    }

    public function handle(array $arguments): \Laravel\Mcp\Server\Tools\ToolResult
    {
        $name = $arguments['name'];
        $attribute = $arguments['attribute'];
        $type = $arguments['type'] ?? 'string';
        $partial = $arguments['partial'] ?? false;
        $customLogic = $arguments['custom_logic'] ?? null;
        $repository = $arguments['repository'] ?? null;
        $namespace = $arguments['namespace'] ?? 'App\\Restify\\Filters';

        // Generate the filter class
        $filterClass = $this->generateFilterClass($name, $attribute, $type, $partial, $customLogic, $namespace);

        // Generate repository integration example
        $repositoryExample = $this->generateRepositoryExample($attribute, $name, $type, $repository);

        // Generate usage examples
        $usageExamples = $this->generateUsageExamples($attribute, $type);

        return ToolResult::content([
            'filter_class' => $filterClass,
            'repository_integration' => $repositoryExample,
            'usage_examples' => $usageExamples,
            'file_path' => $this->getFilePath($name, $namespace),
            'instructions' => $this->getInstructions($name, $attribute, $type),
        ]);
    }

    private function generateFilterClass(string $name, string $attribute, string $type, bool $partial, ?string $customLogic, string $namespace): string
    {
        $className = $this->ensureFilterSuffix($name);

        if ($type === 'custom') {
            return $this->generateCustomFilterClass($className, $attribute, $customLogic, $namespace);
        }

        return $this->generateSimpleMatchFilter($className, $attribute, $type, $partial, $namespace);
    }

    private function generateCustomFilterClass(string $className, string $attribute, ?string $customLogic, string $namespace): string
    {
        $logicComment = $customLogic ? "// {$customLogic}" : '// Add your custom filtering logic here';

        return "<?php

declare(strict_types=1);

namespace {$namespace};

use Binaryk\\LaravelRestify\\Filters\\MatchFilter;
use Binaryk\\LaravelRestify\\Http\\Requests\\RestifyRequest;
use Illuminate\\Database\\Eloquent\\Builder;
use Illuminate\\Database\\Eloquent\\Relations\\Relation;

class {$className} extends MatchFilter
{
    /**
     * Apply the filter to the query.
     *
     * @param RestifyRequest \$request
     * @param Builder|Relation \$query
     * @param mixed \$value
     * @return Builder|Relation
     */
    public function filter(RestifyRequest \$request, Builder|Relation \$query, \$value)
    {
        {$logicComment}
        
        // Example implementations:
        
        // Simple exact match:
        // return \$query->where('{$attribute}', \$value);
        
        // Partial text matching:
        // return \$query->where('{$attribute}', 'LIKE', \"%{\$value}%\");
        
        // Complex logic with multiple conditions:
        // return \$query->when(\$value === 'active', function (\$q) {
        //     return \$q->where('status', 'published')->where('deleted_at', null);
        // });
        
        // Array-based filtering:
        // return \$query->whereIn('{$attribute}', is_array(\$value) ? \$value : [\$value]);
        
        // Date range filtering:
        // if (str_contains(\$value, ',')) {
        //     [\$start, \$end] = explode(',', \$value, 2);
        //     return \$query->whereBetween('{$attribute}', [\$start, \$end]);
        // }
        
        return \$query->where('{$attribute}', \$value);
    }
    
    /**
     * Get the filter's display name.
     *
     * @return string
     */
    public function name(): string
    {
        return '{$attribute}';
    }
}";
    }

    private function generateSimpleMatchFilter(string $className, string $attribute, string $type, bool $partial, string $namespace): string
    {
        $filterLogic = $this->getFilterLogic($attribute, $type, $partial);

        return "<?php

declare(strict_types=1);

namespace {$namespace};

use Binaryk\\LaravelRestify\\Filters\\MatchFilter;
use Binaryk\\LaravelRestify\\Http\\Requests\\RestifyRequest;
use Illuminate\\Database\\Eloquent\\Builder;
use Illuminate\\Database\\Eloquent\\Relations\\Relation;

class {$className} extends MatchFilter
{
    /**
     * Apply the filter to the query.
     *
     * @param RestifyRequest \$request
     * @param Builder|Relation \$query
     * @param mixed \$value
     * @return Builder|Relation
     */
    public function filter(RestifyRequest \$request, Builder|Relation \$query, \$value)
    {
{$filterLogic}
    }
    
    /**
     * Get the filter's display name.
     *
     * @return string
     */
    public function name(): string
    {
        return '{$attribute}';
    }
}";
    }

    private function getFilterLogic(string $attribute, string $type, bool $partial): string
    {
        return match ($type) {
            'string', 'text' => $partial
                ? "        return \$query->where('{$attribute}', 'LIKE', \"%{\$value}%\");"
                : "        return \$query->where('{$attribute}', \$value);",

            'int', 'integer' => "        return \$query->where('{$attribute}', (int) \$value);",

            'bool', 'boolean' => "        return \$query->where('{$attribute}', filter_var(\$value, FILTER_VALIDATE_BOOLEAN));",

            'datetime' => "        // Handle single date or date range
        if (str_contains(\$value, ',')) {
            [\$start, \$end] = explode(',', \$value, 2);
            return \$query->whereBetween('{$attribute}', [\$start, \$end]);
        }
        
        return \$query->whereDate('{$attribute}', \$value);",

            'between' => "        // Expects comma-separated values: value1,value2
        if (str_contains(\$value, ',')) {
            [\$start, \$end] = explode(',', \$value, 2);
            return \$query->whereBetween('{$attribute}', [\$start, \$end]);
        }
        
        return \$query->where('{$attribute}', \$value);",

            'array' => "        // Handle comma-separated values or array
        \$values = is_array(\$value) ? \$value : explode(',', \$value);
        return \$query->whereIn('{$attribute}', \$values);",

            default => "        return \$query->where('{$attribute}', \$value);",
        };
    }

    private function generateRepositoryExample(string $attribute, string $name, string $type, ?string $repository): string
    {
        $className = $this->ensureFilterSuffix($name);
        $repositoryName = $repository ?? 'YourRepository';

        // Simple array configuration
        $simpleConfig = "// Option 1: Simple configuration using \$match array
public static array \$match = [
    '{$attribute}' => '{$type}',
];";

        // Custom filter configuration
        $customConfig = "// Option 2: Custom filter using matches() method
public static function matches(): array
{
    return [
        '{$attribute}' => {$className}::make(),
    ];
}";

        if ($type === 'custom') {
            return "// Add to your {$repositoryName} class:

{$customConfig}

// Note: When using custom matches() method, the \$match array is ignored.
// Make sure to include all your match filters in the matches() method.";
        }

        return "// Add to your {$repositoryName} class:

{$simpleConfig}

// Or for more control:

{$customConfig}

// Note: When using custom matches() method, the \$match array is ignored.
// Make sure to include all your match filters in the matches() method.";
    }

    private function generateUsageExamples(string $attribute, string $type): array
    {
        $examples = [];

        switch ($type) {
            case 'string':
            case 'text':
                $examples[] = [
                    'description' => 'Exact string match',
                    'url' => "GET /api/restify/posts?{$attribute}=\"Some Title\"",
                    'sql' => "WHERE {$attribute} = 'Some Title'",
                ];
                $examples[] = [
                    'description' => 'Negated string match',
                    'url' => "GET /api/restify/posts?-{$attribute}=\"Some Title\"",
                    'sql' => "WHERE {$attribute} != 'Some Title'",
                ];
                break;

            case 'int':
            case 'integer':
                $examples[] = [
                    'description' => 'Integer match',
                    'url' => "GET /api/restify/posts?{$attribute}=42",
                    'sql' => "WHERE {$attribute} = 42",
                ];
                break;

            case 'bool':
            case 'boolean':
                $examples[] = [
                    'description' => 'Boolean true match',
                    'url' => "GET /api/restify/posts?{$attribute}=true",
                    'sql' => "WHERE {$attribute} = 1",
                ];
                $examples[] = [
                    'description' => 'Boolean false match',
                    'url' => "GET /api/restify/posts?{$attribute}=false",
                    'sql' => "WHERE {$attribute} = 0",
                ];
                break;

            case 'datetime':
                $examples[] = [
                    'description' => 'Single date match',
                    'url' => "GET /api/restify/posts?{$attribute}=2023-12-01",
                    'sql' => "WHERE DATE({$attribute}) = '2023-12-01'",
                ];
                $examples[] = [
                    'description' => 'Date range match',
                    'url' => "GET /api/restify/posts?{$attribute}=2023-12-01,2023-12-31",
                    'sql' => "WHERE {$attribute} BETWEEN '2023-12-01' AND '2023-12-31'",
                ];
                break;

            case 'between':
                $examples[] = [
                    'description' => 'Between values match',
                    'url' => "GET /api/restify/posts?{$attribute}=10,100",
                    'sql' => "WHERE {$attribute} BETWEEN 10 AND 100",
                ];
                break;

            case 'array':
                $examples[] = [
                    'description' => 'Array/list match',
                    'url' => "GET /api/restify/posts?{$attribute}=1,2,3",
                    'sql' => "WHERE {$attribute} IN (1, 2, 3)",
                ];
                break;

            case 'custom':
                $examples[] = [
                    'description' => 'Custom filter usage',
                    'url' => "GET /api/restify/posts?{$attribute}=your_value",
                    'sql' => 'Custom logic defined in your filter class',
                ];
                break;
        }

        // Common examples for all types
        $examples[] = [
            'description' => 'Null value match',
            'url' => "GET /api/restify/posts?{$attribute}=null",
            'sql' => "WHERE {$attribute} IS NULL",
        ];

        return $examples;
    }

    private function getFilePath(string $name, string $namespace): string
    {
        $className = $this->ensureFilterSuffix($name);
        $namespacePath = str_replace('\\', '/', str_replace('App\\', 'app/', $namespace));

        return "{$namespacePath}/{$className}.php";
    }

    private function getInstructions(string $name, string $attribute, string $type): array
    {
        $className = $this->ensureFilterSuffix($name);

        return [
            '1. Save the filter class' => 'Save the generated class to the specified file path',
            '2. Add to repository' => 'Add the filter configuration to your repository using either $match array or matches() method',
            '3. Test the filter' => 'Use the provided URL examples to test your match filter',
            '4. Customize as needed' => $type === 'custom'
                ? 'Implement your custom filtering logic in the filter() method'
                : "The filter is ready to use with the {$type} type matching",
            '5. Available endpoints' => "Your filter will be available at: GET /api/restify/{repository}?{$attribute}=value",
            '6. Get available filters' => 'List all repository filters: GET /api/restify/{repository}/filters?only=matches',
        ];
    }

    private function ensureFilterSuffix(string $name): string
    {
        if (! str_ends_with($name, 'Filter')) {
            return $name.'Filter';
        }

        return $name;
    }
}
