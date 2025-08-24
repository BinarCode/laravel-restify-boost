<?php

declare(strict_types=1);

namespace BinarCode\RestifyBoost\Commands;

use BinarCode\RestifyBoost\Mcp\RestifyDocs;
use BinarCode\RestifyBoost\Mcp\Tools\GenerateActionTool;
use BinarCode\RestifyBoost\Mcp\Tools\GenerateGetterTool;
use BinarCode\RestifyBoost\Mcp\Tools\GenerateRepositoryTool;
use BinarCode\RestifyBoost\Mcp\Tools\GetCodeExamples;
use BinarCode\RestifyBoost\Mcp\Tools\NavigateDocs;
use BinarCode\RestifyBoost\Mcp\Tools\SearchRestifyDocs;
use Illuminate\Console\Command;
use Laravel\Mcp\Server\Tools\ToolResult;

class ExecuteToolCommand extends Command
{
    protected $signature = 'restify-boost:execute 
                          {tool : The MCP tool to execute (search-restify-docs, get-code-examples, navigate-docs, generate-repository, generate-action, generate-getter)}
                          {--queries=* : Search queries (for search-restify-docs)}
                          {--topic= : Topic for code examples (for get-code-examples)}
                          {--category= : Category filter}
                          {--language= : Language filter (for get-code-examples)}
                          {--action= : Navigation action (for navigate-docs)}
                          {--limit=10 : Maximum number of results}
                          {--token-limit=10000 : Maximum response tokens}
                          {--include-content : Include content summaries (for navigate-docs)}
                          {--model-name= : Model name (for generate-repository/generate-action/generate-getter)}
                          {--include-fields : Include fields from schema (for generate-repository)}
                          {--include-relationships : Include relationships (for generate-repository)}
                          {--repository-name= : Override repository name (for generate-repository)}
                          {--action-name= : Action name (for generate-action)}
                          {--action-type= : Action type: index, show, standalone, invokable, destructive (for generate-action)}
                          {--validation-rules= : Validation rules as JSON (for generate-action)}
                          {--getter-name= : Getter name (for generate-getter)}
                          {--getter-type= : Getter type: invokable, extended (for generate-getter)}
                          {--scope= : Getter scope: index, show, both (for generate-getter)}
                          {--uri-key= : Custom URI key (for generate-action/generate-getter)}
                          {--namespace= : Override namespace (for generate-repository/generate-action/generate-getter)}
                          {--force : Force overwrite existing file}';

    protected $description = 'Execute a specific MCP tool directly from the command line';

    protected array $availableTools = [
        'search-restify-docs' => SearchRestifyDocs::class,
        'get-code-examples' => GetCodeExamples::class,
        'navigate-docs' => NavigateDocs::class,
        'generate-repository' => GenerateRepositoryTool::class,
        'generate-action' => GenerateActionTool::class,
        'generate-getter' => GenerateGetterTool::class,
    ];

    public function handle(): int
    {
        $toolName = $this->argument('tool');

        if (! array_key_exists($toolName, $this->availableTools)) {
            $this->error("Unknown tool: {$toolName}");
            $this->line('Available tools: '.implode(', ', array_keys($this->availableTools)));

            return self::FAILURE;
        }

        $this->info("Executing MCP tool: {$toolName}");
        $this->newLine();

        try {
            // Initialize the MCP server to ensure all services are available
            $server = app(RestifyDocs::class);
            $server->boot();

            // Get the tool instance
            $toolClass = $this->availableTools[$toolName];
            $tool = app($toolClass);

            // Prepare arguments based on the tool
            $arguments = $this->prepareArguments($toolName);

            // Execute the tool
            $result = $tool->handle($arguments);

            // Display the result
            $this->displayResult($result);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Tool execution failed: '.$e->getMessage());
            if ($this->getOutput()->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    protected function prepareArguments(string $toolName): array
    {
        $arguments = [];

        switch ($toolName) {
            case 'search-restify-docs':
                $queries = $this->option('queries');
                if (empty($queries)) {
                    $this->error('--queries option is required for search-restify-docs');
                    exit(self::FAILURE);
                }

                $arguments = [
                    'queries' => is_array($queries) ? $queries : [$queries],
                    'category' => $this->option('category'),
                    'limit' => (int) $this->option('limit'),
                    'token_limit' => (int) $this->option('token-limit'),
                ];
                break;

            case 'get-code-examples':
                $topic = $this->option('topic');
                if (! $topic) {
                    $this->error('--topic option is required for get-code-examples');
                    exit(self::FAILURE);
                }

                $arguments = [
                    'topic' => $topic,
                    'language' => $this->option('language'),
                    'category' => $this->option('category'),
                    'limit' => (int) $this->option('limit'),
                    'include_context' => true,
                ];
                break;

            case 'navigate-docs':
                $action = $this->option('action') ?: 'overview';

                $arguments = [
                    'action' => $action,
                    'category' => $this->option('category'),
                    'include_content' => $this->option('include-content') !== false,
                    'limit' => (int) $this->option('limit'),
                ];
                break;

            case 'generate-repository':
                $modelName = $this->option('model-name');
                if (! $modelName) {
                    $this->error('--model-name option is required for generate-repository');
                    exit(self::FAILURE);
                }

                $arguments = [
                    'model_name' => $modelName,
                    'include_fields' => $this->option('include-fields') !== false,
                    'include_relationships' => $this->option('include-relationships') !== false,
                    'repository_name' => $this->option('repository-name'),
                    'namespace' => $this->option('namespace'),
                    'force' => $this->option('force') !== false,
                ];
                break;

            case 'generate-action':
                $actionName = $this->option('action-name');
                if (! $actionName) {
                    $this->error('--action-name option is required for generate-action');
                    exit(self::FAILURE);
                }

                $validationRules = null;
                if ($rulesJson = $this->option('validation-rules')) {
                    $validationRules = json_decode($rulesJson, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $this->error('Invalid JSON in --validation-rules option');
                        exit(self::FAILURE);
                    }
                }

                $arguments = [
                    'action_name' => $actionName,
                    'action_type' => $this->option('action-type') ?: 'index',
                    'model_name' => $this->option('model-name'),
                    'validation_rules' => $validationRules,
                    'uri_key' => $this->option('uri-key'),
                    'namespace' => $this->option('namespace'),
                    'force' => $this->option('force') !== false,
                ];
                break;

            case 'generate-getter':
                $getterName = $this->option('getter-name');
                if (! $getterName) {
                    $this->error('--getter-name option is required for generate-getter');
                    exit(self::FAILURE);
                }

                $arguments = [
                    'getter_name' => $getterName,
                    'getter_type' => $this->option('getter-type') ?: 'extended',
                    'scope' => $this->option('scope') ?: 'both',
                    'model_name' => $this->option('model-name'),
                    'uri_key' => $this->option('uri-key'),
                    'namespace' => $this->option('namespace'),
                    'force' => $this->option('force') !== false,
                ];
                break;
        }

        // Filter out null values
        return array_filter($arguments, fn ($value) => $value !== null);
    }

    protected function displayResult(ToolResult $result): void
    {
        if ($result->isError) {
            $this->error('Tool Error:');
            $this->line($result->content);

            return;
        }

        $this->info('Tool Result:');
        $this->line(str_repeat('-', 50));
        $this->line($result->content);
        $this->line(str_repeat('-', 50));

        // Show result metadata
        if (strlen($result->content) > 1000) {
            $wordCount = str_word_count($result->content);
            $estimatedTokens = (int) ceil(strlen($result->content) / 4);
            $this->newLine();
            $this->comment("Result size: {$wordCount} words (~{$estimatedTokens} tokens)");
        }
    }
}
