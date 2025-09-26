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

class GenerateGetterTool extends Tool
{
    public function description(): string
    {
        return 'Generate a Laravel Restify getter class. Getters allow you to define custom GET-only operations for your repositories to retrieve additional data without modifying the main CRUD operations. This tool can create invokable getters (simple __invoke method) or extended getters (with handle method), and can be scoped to index (multiple items), show (single model), or both contexts.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'getter_name' => $schema->string()
                ->description('Name of the getter class (e.g., "StripeInformation", "UserStats", "ExportData")'),
            'getter_type' => $schema->string()
                ->description('Type of getter: "invokable" (simple __invoke method) or "extended" (extends Getter class with handle method)'),
            'scope' => $schema->string()
                ->description('Getter scope: "index" (multiple items), "show" (single model), or "both" (default - can be used in both contexts)'),
            'model_name' => $schema->string()
                ->description('Name of the model this getter works with (optional, used for show getters to generate proper method signature)'),
            'uri_key' => $schema->string()
                ->description('Custom URI key for the getter (defaults to kebab-case of class name)'),
            'namespace' => $schema->string()
                ->description('Override default namespace (auto-detected from existing getters)'),
            'force' => $schema->boolean()
                ->description('Overwrite existing getter file if it exists'),
        ];
    }

    public function handle(Request $request): Response
    {
        try {
            $getterName = trim($request->get('getter_name'));
            $getterType = $request->get('getter_type') ?? 'extended';
            $scope = $request->get('scope') ?? 'both';
            $modelName = $request->get('model_name') ?? null;
            $uriKey = $request->get('uri_key') ?? null;
            $customNamespace = $request->get('namespace') ?? null;
            $force = $request->get('force') ?? false;

            if (empty($getterName)) {
                return Response::error('Getter name is required');
            }

            // Validate getter type
            $validGetterTypes = ['invokable', 'extended'];
            if (! in_array($getterType, $validGetterTypes)) {
                return Response::error('Invalid getter type. Must be one of: '.implode(', ', $validGetterTypes));
            }

            // Validate scope
            $validScopes = ['index', 'show', 'both'];
            if (! in_array($scope, $validScopes)) {
                return Response::error('Invalid scope. Must be one of: '.implode(', ', $validScopes));
            }

            // Step 1: Analyze existing getter patterns
            $getterInfo = $this->analyzeGetterPatterns();

            // Step 2: Generate getter details
            $getterDetails = $this->generateGetterDetails(
                $getterName,
                $customNamespace,
                $getterInfo
            );

            // Step 3: Check if getter already exists
            if (! $force && File::exists($getterDetails['file_path'])) {
                return Response::error(
                    "Getter already exists at: {$getterDetails['file_path']}\n".
                    "Use 'force: true' to overwrite."
                );
            }

            // Step 4: Generate getter content
            $getterContent = $this->generateGetterContent(
                $getterDetails,
                $getterType,
                $scope,
                $modelName,
                $uriKey
            );

            // Step 5: Create the getter file
            $this->createGetterFile($getterDetails['file_path'], $getterContent);

            // Step 6: Generate response
            return $this->generateSuccessResponse($getterDetails, $getterContent);

        } catch (\Throwable $e) {
            return Response::error('Getter generation failed: '.$e->getMessage());
        }
    }

    protected function analyzeGetterPatterns(): array
    {
        $patterns = [
            'namespace' => $this->getRootNamespace().'\\Restify\\Getters',
            'base_path' => app_path('Restify/Getters'),
        ];

        try {
            $finder = new Finder;
            $finder->files()
                ->in(app_path())
                ->name('*Getter.php')
                ->notPath('vendor')
                ->notPath('tests');

            $getterPaths = [];
            foreach ($finder as $file) {
                $relativePath = str_replace(app_path().DIRECTORY_SEPARATOR, '', $file->getRealPath());
                $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

                $pathParts = explode('/', $relativePath);
                array_pop($pathParts); // Remove filename

                if (! empty($pathParts)) {
                    $namespace = $this->getRootNamespace().'\\'.str_replace('/', '\\', implode('/', $pathParts));
                    $basePath = app_path(implode('/', $pathParts));

                    $getterPaths[] = [
                        'namespace' => $namespace,
                        'base_path' => $basePath,
                        'depth' => count($pathParts),
                    ];
                }
            }

            if (! empty($getterPaths)) {
                // Use the most common pattern (or deepest if tie)
                usort($getterPaths, fn ($a, $b) => $b['depth'] <=> $a['depth']);
                $patterns = $getterPaths[0];
            }

        } catch (\Exception $e) {
            // Use defaults on error
        }

        return $patterns;
    }

    protected function generateGetterDetails(
        string $getterName,
        ?string $customNamespace,
        array $getterInfo
    ): array {
        // Ensure getter name ends with 'Getter'
        if (! str_ends_with($getterName, 'Getter')) {
            $getterName .= 'Getter';
        }

        $namespace = $customNamespace ?: $getterInfo['namespace'];
        $basePath = $getterInfo['base_path'];
        $filePath = $basePath.'/'.$getterName.'.php';

        return [
            'getter_name' => $getterName,
            'namespace' => $namespace,
            'file_path' => $filePath,
        ];
    }

    protected function generateGetterContent(
        array $getterDetails,
        string $getterType,
        string $scope,
        ?string $modelName,
        ?string $uriKey
    ): array {
        $imports = [];
        $baseClass = null;
        $properties = [];
        $methods = [];

        // Determine imports and base class based on getter type
        if ($getterType === 'extended') {
            $imports[] = 'Binaryk\\LaravelRestify\\Getters\\Getter';
            $imports[] = 'Illuminate\\Http\\JsonResponse';
            $imports[] = 'Illuminate\\Http\\Request';
            $baseClass = 'Getter';
        } else {
            $imports[] = 'Illuminate\\Http\\JsonResponse';
            $imports[] = 'Illuminate\\Http\\Request';
        }

        // Add model import if specified for show getters
        if ($modelName && $scope !== 'index') {
            $modelClass = $this->resolveModelClass($modelName);
            if ($modelClass) {
                $imports[] = $modelClass;
            }
        }

        // Generate properties
        if ($uriKey) {
            $properties[] = "    public static \$uriKey = '{$uriKey}';";
        }

        // Generate main method based on getter type
        if ($getterType === 'invokable') {
            $methods[] = $this->generateInvokeMethod($scope, $modelName);
        } else {
            $methods[] = $this->generateHandleMethod($scope, $modelName);
        }

        return [
            'namespace' => $getterDetails['namespace'],
            'getter_name' => $getterDetails['getter_name'],
            'imports' => array_unique($imports),
            'base_class' => $baseClass,
            'properties' => $properties,
            'methods' => $methods,
            'getter_type' => $getterType,
            'scope' => $scope,
        ];
    }

    protected function generateInvokeMethod(string $scope, ?string $modelName): string
    {
        $method = '    public function __invoke(Request $request';

        // Add model parameter for show getters
        if ($scope === 'show' && $modelName) {
            $modelVar = '$'.Str::camel($modelName);
            $modelParam = Str::studly($modelName).' '.$modelVar;
            $method .= ", {$modelParam}";
        }

        $method .= "): JsonResponse\n";
        $method .= "    {\n";

        if ($scope === 'show' && $modelName) {
            $modelVar = '$'.Str::camel($modelName);
            $method .= "        // Get additional data for the specific model\n";
            $method .= "        // Example: \$additionalData = {$modelVar}->someRelationship;\n\n";
        } else {
            $method .= "        // Get additional data for the repository\n";
            $method .= "        // Example: \$stats = \$request->user()->getStats();\n\n";
        }

        $method .= "        return response()->json([\n";
        $method .= "            'data' => [\n";
        $method .= "                'message' => 'Getter executed successfully',\n";
        $method .= "                // Add your custom data here\n";
        $method .= "            ],\n";
        $method .= "        ]);\n";
        $method .= '    }';

        return $method;
    }

    protected function generateHandleMethod(string $scope, ?string $modelName): string
    {
        $method = '    public function handle(Request $request';

        // Add model parameter for show getters
        if ($scope === 'show' && $modelName) {
            $modelVar = '$'.Str::camel($modelName);
            $modelParam = Str::studly($modelName).' '.$modelVar;
            $method .= ", {$modelParam}";
        }

        $method .= "): JsonResponse\n";
        $method .= "    {\n";

        if ($scope === 'show' && $modelName) {
            $modelVar = '$'.Str::camel($modelName);
            $method .= "        // Get additional data for the specific model\n";
            $method .= "        // Example: \$additionalData = {$modelVar}->someRelationship;\n\n";
        } else {
            $method .= "        // Get additional data for the repository\n";
            $method .= "        // Example: \$stats = \$request->user()->getStats();\n\n";
        }

        $method .= "        return response()->json([\n";
        $method .= "            'data' => [\n";
        $method .= "                'message' => 'Getter executed successfully',\n";
        $method .= "                // Add your custom data here\n";
        $method .= "            ],\n";
        $method .= "        ]);\n";
        $method .= '    }';

        return $method;
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

    protected function createGetterFile(string $filePath, array $content): void
    {
        // Ensure directory exists
        $directory = dirname($filePath);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // Generate getter file content
        $fileContent = $this->generateGetterFileContent($content);

        // Write the file
        File::put($filePath, $fileContent);
    }

    protected function generateGetterFileContent(array $content): string
    {
        // Build imports
        $imports = array_unique($content['imports']);
        sort($imports);
        $importsString = implode("\n", array_map(fn ($import) => "use {$import};", $imports));

        // Build class declaration
        $classDeclaration = "class {$content['getter_name']}";
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

    protected function generateSuccessResponse(array $getterDetails, array $content): Response
    {
        $response = "# Getter Generated Successfully!\n\n";
        $response .= "**Getter:** `{$getterDetails['getter_name']}`\n";
        $response .= "**Type:** `{$content['getter_type']}`\n";
        $response .= "**Scope:** `{$content['scope']}`\n";
        $response .= "**File:** `{$getterDetails['file_path']}`\n";
        $response .= "**Namespace:** `{$getterDetails['namespace']}`\n\n";

        // Add getter type specific information
        switch ($content['getter_type']) {
            case 'invokable':
                $response .= "## Invokable Getter\n\n";
                $response .= "This getter uses the **simple invokable format** with an `__invoke()` method.\n\n";
                break;
            case 'extended':
                $response .= "## Extended Getter\n\n";
                $response .= "This getter **extends the Getter base class** with a `handle()` method.\n\n";
                break;
        }

        // Add scope specific information
        switch ($content['scope']) {
            case 'index':
                $response .= "## Index Getter Usage\n\n";
                $response .= "This getter works with **multiple items**. Usage:\n\n";
                $response .= "1. Register in repository:\n";
                $response .= "```php\n";
                $response .= "public function getters(RestifyRequest \$request): array\n";
                $response .= "{\n";
                $response .= "    return [\n";
                $response .= "        {$getterDetails['getter_name']}::new()->onlyOnIndex(),\n";
                $response .= "    ];\n";
                $response .= "}\n";
                $response .= "```\n\n";
                $response .= "2. Call via API:\n";
                $response .= "```http\n";
                $response .= 'GET: api/restify/models/getters/'.Str::kebab(str_replace('Getter', '', $getterDetails['getter_name']))."\n";
                $response .= "```\n";
                break;

            case 'show':
                $response .= "## Show Getter Usage\n\n";
                $response .= "This getter works with a **single model**. Usage:\n\n";
                $response .= "1. Register in repository:\n";
                $response .= "```php\n";
                $response .= "public function getters(RestifyRequest \$request): array\n";
                $response .= "{\n";
                $response .= "    return [\n";
                $response .= "        {$getterDetails['getter_name']}::new()->onlyOnShow(),\n";
                $response .= "    ];\n";
                $response .= "}\n";
                $response .= "```\n\n";
                $response .= "2. Call via API:\n";
                $response .= "```http\n";
                $response .= 'GET: api/restify/models/1/getters/'.Str::kebab(str_replace('Getter', '', $getterDetails['getter_name']))."\n";
                $response .= "```\n";
                break;

            case 'both':
                $response .= "## Flexible Getter Usage\n\n";
                $response .= "This getter can be used in **both index and show contexts**. Usage:\n\n";
                $response .= "1. Register in repository:\n";
                $response .= "```php\n";
                $response .= "public function getters(RestifyRequest \$request): array\n";
                $response .= "{\n";
                $response .= "    return [\n";
                $response .= "        {$getterDetails['getter_name']}::new(), // Available on both index and show\n";
                $response .= "    ];\n";
                $response .= "}\n";
                $response .= "```\n\n";
                $response .= "2. Call via API:\n";
                $response .= "```http\n";
                $response .= "# Index context (multiple items)\n";
                $response .= 'GET: api/restify/models/getters/'.Str::kebab(str_replace('Getter', '', $getterDetails['getter_name']))."\n\n";
                $response .= "# Show context (single model)\n";
                $response .= 'GET: api/restify/models/1/getters/'.Str::kebab(str_replace('Getter', '', $getterDetails['getter_name']))."\n";
                $response .= "```\n";
                break;
        }

        $response .= "\n## Next Steps\n\n";
        $response .= "1. **Review the generated getter** at `{$getterDetails['file_path']}`\n";
        $response .= '2. **Implement the business logic** in the '.($content['getter_type'] === 'invokable' ? '__invoke' : 'handle')." method\n";
        $response .= "3. **Register the getter** in your repository's `getters()` method\n";
        $response .= "4. **Add authorization** using `->canSee()` if needed\n";
        $response .= "5. **Test the getter** via API calls or feature tests\n\n";

        $response .= "## Getter Features Generated\n\n";

        if (! empty($content['properties'])) {
            $response .= "✅ **Properties**: Custom URI key and getter settings\n";
        }

        $response .= '✅ **Method**: '.ucfirst($content['getter_type'])." method with proper signature\n";

        if ($content['scope'] !== 'both') {
            $response .= "✅ **Scope**: Configured for {$content['scope']} context\n";
        }

        $response .= "\n## Additional Resources\n\n";
        $response .= "- **Authorization**: Use `->canSee(fn(\$request) => ...)` for access control\n";
        $response .= "- **Custom URI Key**: Set `\$uriKey` property for consistent API endpoints\n";
        $response .= "- **GET-only**: Remember getters should only perform read operations\n";

        return Response::text($response);
    }

    protected function getRootNamespace(): string
    {
        return 'App';
    }
}
