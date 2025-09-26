<?php

declare(strict_types=1);

namespace BinarCode\RestifyBoost\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Symfony\Component\Finder\Finder;

class GenerateActionTool extends Tool
{
    public function description(): string
    {
        return 'Generate a Laravel Restify action class. Actions allow you to define custom operations for your repositories beyond basic CRUD. This tool can create different types of actions: index actions (for multiple models), show actions (for single models), standalone actions (no models required), invokable actions, or destructive actions. It follows existing project patterns and generates appropriate validation rules.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action_name' => $schema->string()
                ->description('Name of the action class (e.g., "PublishPost", "DisableProfile", "ExportUsers")'),
            'action_type' => $schema->string()
                ->description('Type of action: "index" (for multiple models), "show" (for single model), "standalone" (no models), "invokable" (simple __invoke method), or "destructive" (extends DestructiveAction)'),
            'model_name' => $schema->string()
                ->description('Name of the model this action works with (optional, used for show/index actions)'),
            'validation_rules' => $schema->object()
                ->description('Validation rules for the action payload as key-value pairs'),
            'uri_key' => $schema->string()
                ->description('Custom URI key for the action (defaults to kebab-case of class name)'),
            'namespace' => $schema->string()
                ->description('Override default namespace (auto-detected from existing actions)'),
            'force' => $schema->boolean()
                ->description('Overwrite existing action file if it exists'),
        ];
    }

    public function handle(Request $request): Response
    {
        try {
            $actionName = trim($request->get('action_name'));
            $actionType = $request->get('action_type') ?? 'index';
            $modelName = $request->get('model_name') ?? null;
            $validationRules = $request->get('validation_rules') ?? [];
            $uriKey = $request->get('uri_key') ?? null;
            $customNamespace = $request->get('namespace') ?? null;
            $force = $request->get('force') ?? false;

            if (empty($actionName)) {
                return Response::error('Action name is required');
            }

            // Validate action type
            $validActionTypes = ['index', 'show', 'standalone', 'invokable', 'destructive'];
            if (! in_array($actionType, $validActionTypes)) {
                return Response::error('Invalid action type. Must be one of: '.implode(', ', $validActionTypes));
            }

            // Step 1: Analyze existing action patterns
            $actionInfo = $this->analyzeActionPatterns();

            // Step 2: Generate action details
            $actionDetails = $this->generateActionDetails(
                $actionName,
                $actionType,
                $modelName,
                $customNamespace,
                $actionInfo
            );

            // Step 3: Check if action already exists
            if (! $force && File::exists($actionDetails['file_path'])) {
                return Response::error(
                    "Action already exists at: {$actionDetails['file_path']}\n".
                    "Use 'force: true' to overwrite."
                );
            }

            // Step 4: Generate action content
            $actionContent = $this->generateActionContent(
                $actionDetails,
                $actionType,
                $modelName,
                $validationRules,
                $uriKey
            );

            // Step 5: Create the action file
            $this->createActionFile($actionDetails['file_path'], $actionContent);

            // Step 6: Generate response
            return $this->generateSuccessResponse($actionDetails, $actionContent);

        } catch (\Throwable $e) {
            return Response::error('Action generation failed: '.$e->getMessage());
        }
    }

    protected function analyzeActionPatterns(): array
    {
        $patterns = [
            'namespace' => $this->getRootNamespace().'\\Restify\\Actions',
            'base_path' => app_path('Restify/Actions'),
        ];

        try {
            $finder = new Finder;
            $finder->files()
                ->in(app_path())
                ->name('*Action.php')
                ->notPath('vendor')
                ->notPath('tests');

            $actionPaths = [];
            foreach ($finder as $file) {
                $relativePath = str_replace(app_path().DIRECTORY_SEPARATOR, '', $file->getRealPath());
                $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

                $pathParts = explode('/', $relativePath);
                array_pop($pathParts); // Remove filename

                if (! empty($pathParts)) {
                    $namespace = $this->getRootNamespace().'\\'.str_replace('/', '\\', implode('/', $pathParts));
                    $basePath = app_path(implode('/', $pathParts));

                    $actionPaths[] = [
                        'namespace' => $namespace,
                        'base_path' => $basePath,
                        'depth' => count($pathParts),
                    ];
                }
            }

            if (! empty($actionPaths)) {
                // Use the most common pattern (or deepest if tie)
                usort($actionPaths, fn ($a, $b) => $b['depth'] <=> $a['depth']);
                $patterns = $actionPaths[0];
            }

        } catch (\Exception $e) {
            // Use defaults on error
        }

        return $patterns;
    }

    protected function generateActionDetails(
        string $actionName,
        string $actionType,
        ?string $modelName,
        ?string $customNamespace,
        array $actionInfo
    ): array {
        // Ensure action name ends with 'Action'
        if (! str_ends_with($actionName, 'Action')) {
            $actionName .= 'Action';
        }

        $namespace = $customNamespace ?: $actionInfo['namespace'];
        $basePath = $actionInfo['base_path'];
        $filePath = $basePath.'/'.$actionName.'.php';

        return [
            'action_name' => $actionName,
            'namespace' => $namespace,
            'file_path' => $filePath,
            'model_name' => $modelName,
            'action_type' => $actionType,
        ];
    }

    protected function generateActionContent(
        array $actionDetails,
        string $actionType,
        ?string $modelName,
        array $validationRules,
        ?string $uriKey
    ): array {
        $imports = [];
        $baseClass = 'Action';
        $properties = [];
        $methods = [];

        // Determine imports and base class based on action type
        switch ($actionType) {
            case 'invokable':
                $imports[] = 'Illuminate\\Http\\Request';
                $baseClass = null; // No base class for invokable actions
                break;
            case 'destructive':
                $imports[] = 'Binaryk\\LaravelRestify\\Actions\\DestructiveAction';
                $imports[] = 'Binaryk\\LaravelRestify\\Http\\Requests\\ActionRequest';
                $imports[] = 'Illuminate\\Http\\JsonResponse';
                $baseClass = 'DestructiveAction';
                break;
            default:
                $imports[] = 'Binaryk\\LaravelRestify\\Actions\\Action';
                $imports[] = 'Binaryk\\LaravelRestify\\Http\\Requests\\ActionRequest';
                $imports[] = 'Illuminate\\Http\\JsonResponse';
                break;
        }

        // Add model import if specified
        if ($modelName && $actionType !== 'standalone') {
            $modelClass = $this->resolveModelClass($modelName);
            if ($modelClass) {
                $imports[] = $modelClass;
            }
        }

        // Add Collection import for index actions
        if ($actionType === 'index') {
            $imports[] = 'Illuminate\\Support\\Collection';
        }

        // Generate properties
        if ($uriKey) {
            $properties[] = "    public static string \$uriKey = '{$uriKey}';";
        }

        if ($actionType === 'standalone') {
            $properties[] = '    public bool $standalone = true;';
        }

        // Generate handle method based on action type
        $methods[] = $this->generateHandleMethod($actionType, $modelName, $validationRules);

        // Generate validation rules method if rules provided
        if (! empty($validationRules)) {
            $methods[] = $this->generateRulesMethod($validationRules);
        }

        // Generate indexQuery method for index actions
        if ($actionType === 'index' && $modelName) {
            $methods[] = $this->generateIndexQueryMethod();
        }

        return [
            'namespace' => $actionDetails['namespace'],
            'action_name' => $actionDetails['action_name'],
            'imports' => array_unique($imports),
            'base_class' => $baseClass,
            'properties' => $properties,
            'methods' => $methods,
            'action_type' => $actionType,
        ];
    }

    protected function generateHandleMethod(string $actionType, ?string $modelName, array $validationRules): string
    {
        $validation = '';
        if (! empty($validationRules)) {
            $validation = "\n        \$request->validate(\$this->rules());\n";
        }

        switch ($actionType) {
            case 'invokable':
                return "    public function __invoke(Request \$request)\n".
                       '    {'.
                       $validation.
                       "\n        return response()->json([\n".
                       "            'message' => 'Action completed successfully',\n".
                       "        ]);\n".
                       '    }';

            case 'show':
                $modelVar = $modelName ? '$'.Str::camel($modelName) : '$model';
                $modelParam = $modelName ? Str::studly($modelName).' '.$modelVar : 'Model '.$modelVar;

                return "    public function handle(ActionRequest \$request, {$modelParam}): JsonResponse\n".
                       '    {'.
                       $validation.
                       "\n        // Perform action on single model\n".
                       "        // \$model->update(['status' => 'processed']);\n\n".
                       "        return response()->json([\n".
                       "            'message' => 'Action completed successfully',\n".
                       "        ]);\n".
                       '    }';

            case 'standalone':
                return "    public function handle(ActionRequest \$request): JsonResponse\n".
                       '    {'.
                       $validation.
                       "\n        // Perform standalone action (no models involved)\n".
                       "        // Example: \$request->user()->update(['some_field' => 'value']);\n\n".
                       "        return response()->json([\n".
                       "            'message' => 'Action completed successfully',\n".
                       "        ]);\n".
                       '    }';

            case 'index':
            case 'destructive':
            default:
                $collectionVar = $modelName ? '$'.Str::camel(Str::plural($modelName)) : '$models';

                return "    public function handle(ActionRequest \$request, Collection {$collectionVar}): JsonResponse\n".
                       '    {'.
                       $validation.
                       "\n        // Perform action on multiple models\n".
                       "        // {$collectionVar}->each(function (\$model) {\n".
                       "        //     \$model->update(['status' => 'processed']);\n".
                       "        // });\n\n".
                       "        return response()->json([\n".
                       "            'message' => 'Action completed successfully',\n".
                       "            'processed' => {$collectionVar}->count(),\n".
                       "        ]);\n".
                       '    }';
        }
    }

    protected function generateRulesMethod(array $validationRules): string
    {
        $rulesArray = [];
        foreach ($validationRules as $field => $rules) {
            if (is_array($rules)) {
                $ruleString = "['".implode("', '", $rules)."']";
            } else {
                $ruleString = "'{$rules}'";
            }
            $rulesArray[] = "        '{$field}' => {$ruleString}";
        }

        $rulesString = implode(",\n", $rulesArray);

        return "    public function rules(): array\n".
               "    {\n".
               "        return [\n".
               $rulesString."\n".
               "        ];\n".
               '    }';
    }

    protected function generateIndexQueryMethod(): string
    {
        return "    public static function indexQuery(RestifyRequest \$request, \$query)\n".
               "    {\n".
               "        // Customize the query before models are retrieved\n".
               "        // Example: \$query->where('status', 'active');\n".
               '    }';
    }

    protected function resolveModelClass(string $modelName): ?string
    {
        $commonLocations = [
            "App\\Models\\{$modelName}",
            "App\\{$modelName}",
            $this->getRootNamespace()."\\Models\\{$modelName}",
            $this->getRootNamespace()."\\{$modelName}",
        ];

        foreach ($commonLocations as $location) {
            if (class_exists($location)) {
                return $location;
            }
        }

        return null;
    }

    protected function createActionFile(string $filePath, array $content): void
    {
        // Ensure directory exists
        $directory = dirname($filePath);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // Generate action file content
        $fileContent = $this->generateActionFileContent($content);

        // Write the file
        File::put($filePath, $fileContent);
    }

    protected function generateActionFileContent(array $content): string
    {
        // Build imports
        $imports = array_unique($content['imports']);
        sort($imports);
        $importsString = implode("\n", array_map(fn ($import) => "use {$import};", $imports));

        // Build class declaration
        $classDeclaration = "class {$content['action_name']}";
        if ($content['base_class']) {
            $classDeclaration .= " extends {$content['base_class']}";
        }

        // Build properties
        $propertiesString = empty($content['properties']) ? '' :
            implode("\n", $content['properties'])."\n";

        // Build methods
        $methodsString = implode("\n\n", $content['methods']);

        return "<?php

declare(strict_types=1);

namespace {$content['namespace']};

{$importsString}

{$classDeclaration}
{
{$propertiesString}
{$methodsString}
}
";
    }

    protected function generateSuccessResponse(array $actionDetails, array $content): Response
    {
        $response = "# Action Generated Successfully!\n\n";
        $response .= "**Action:** `{$actionDetails['action_name']}`\n";
        $response .= "**Type:** `{$actionDetails['action_type']}`\n";
        $response .= "**File:** `{$actionDetails['file_path']}`\n";
        $response .= "**Namespace:** `{$actionDetails['namespace']}`\n\n";

        // Add action type specific information
        switch ($content['action_type']) {
            case 'index':
                $response .= "## Index Action\n\n";
                $response .= "This action works with **multiple models**. Usage:\n\n";
                $response .= "1. Register in repository:\n";
                $response .= "```php\n";
                $response .= "public function actions(RestifyRequest \$request): array\n";
                $response .= "{\n";
                $response .= "    return [\n";
                $response .= "        {$actionDetails['action_name']}::new()->onlyOnIndex(),\n";
                $response .= "    ];\n";
                $response .= "}\n";
                $response .= "```\n\n";
                $response .= "2. Call via API:\n";
                $response .= "```http\n";
                $response .= 'POST: api/restify/models/actions?action='.Str::kebab(str_replace('Action', '', $actionDetails['action_name']))."\n";
                $response .= "{\n";
                $response .= "  \"repositories\": [1, 2, 3]\n";
                $response .= "}\n";
                $response .= "```\n";
                break;

            case 'show':
                $response .= "## Show Action\n\n";
                $response .= "This action works with a **single model**. Usage:\n\n";
                $response .= "1. Register in repository:\n";
                $response .= "```php\n";
                $response .= "public function actions(RestifyRequest \$request): array\n";
                $response .= "{\n";
                $response .= "    return [\n";
                $response .= "        {$actionDetails['action_name']}::new()->onlyOnShow(),\n";
                $response .= "    ];\n";
                $response .= "}\n";
                $response .= "```\n\n";
                $response .= "2. Call via API:\n";
                $response .= "```http\n";
                $response .= 'POST: api/restify/models/1/actions?action='.Str::kebab(str_replace('Action', '', $actionDetails['action_name']))."\n";
                $response .= "{}\n";
                $response .= "```\n";
                break;

            case 'standalone':
                $response .= "## Standalone Action\n\n";
                $response .= "This action works **without models**. Usage:\n\n";
                $response .= "1. Register in repository:\n";
                $response .= "```php\n";
                $response .= "public function actions(RestifyRequest \$request): array\n";
                $response .= "{\n";
                $response .= "    return [\n";
                $response .= "        {$actionDetails['action_name']}::new()->standalone(),\n";
                $response .= "    ];\n";
                $response .= "}\n";
                $response .= "```\n\n";
                $response .= "2. Call via API:\n";
                $response .= "```http\n";
                $response .= 'POST: api/restify/models/actions?action='.Str::kebab(str_replace('Action', '', $actionDetails['action_name']))."\n";
                $response .= "{}\n";
                $response .= "```\n";
                break;

            case 'invokable':
                $response .= "## Invokable Action\n\n";
                $response .= "This is a **simple invokable action**. Usage:\n\n";
                $response .= "1. Register in repository:\n";
                $response .= "```php\n";
                $response .= "public function actions(RestifyRequest \$request): array\n";
                $response .= "{\n";
                $response .= "    return [\n";
                $response .= "        new {$actionDetails['action_name']},\n";
                $response .= "    ];\n";
                $response .= "}\n";
                $response .= "```\n";
                break;

            case 'destructive':
                $response .= "## Destructive Action\n\n";
                $response .= "This action is marked as **destructive** (for deletions, etc.). Usage:\n\n";
                $response .= "1. Register in repository:\n";
                $response .= "```php\n";
                $response .= "public function actions(RestifyRequest \$request): array\n";
                $response .= "{\n";
                $response .= "    return [\n";
                $response .= "        {$actionDetails['action_name']}::new(),\n";
                $response .= "    ];\n";
                $response .= "}\n";
                $response .= "```\n";
                break;
        }

        $response .= "\n## Next Steps\n\n";
        $response .= "1. **Review the generated action** at `{$actionDetails['file_path']}`\n";
        $response .= "2. **Implement the business logic** in the handle method\n";
        $response .= "3. **Register the action** in your repository's `actions()` method\n";
        $response .= "4. **Add authorization** using `->canSee()` if needed\n";
        $response .= "5. **Test the action** via API calls or feature tests\n\n";

        $response .= "## Action Features Generated\n\n";

        if (! empty($content['properties'])) {
            $response .= "✅ **Properties**: Custom URI key and action settings\n";
        }

        if (str_contains($methodsString = implode('', $content['methods']), 'rules()')) {
            $response .= "✅ **Validation**: Custom validation rules method\n";
        }

        if (str_contains($methodsString, 'indexQuery')) {
            $response .= "✅ **Index Query**: Custom query filtering method\n";
        }

        $response .= "\n## Additional Resources\n\n";
        $response .= "- **Action Logs**: Add `HasActionLogs` trait to your model for action logging\n";
        $response .= "- **Authorization**: Use `->canSee(fn(\$request) => ...)` for access control\n";
        $response .= "- **Custom URI Key**: Set `\$uriKey` property for consistent API endpoints\n";

        return Response::text($response);
    }

    protected function getRootNamespace(): string
    {
        return 'App';
    }
}
