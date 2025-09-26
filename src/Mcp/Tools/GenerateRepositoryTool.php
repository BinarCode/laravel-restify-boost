<?php

declare(strict_types=1);

namespace BinarCode\RestifyBoost\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class GenerateRepositoryTool extends Tool
{
    protected ?string $confirmedModelClass = null;

    /**
     * The tool's description.
     */
    protected string $description = 'Generate a Laravel Restify repository class based on an existing Eloquent model. This tool analyzes the model\'s database schema, detects relationships, generates appropriate fields, and follows existing repository organization patterns in your project. It can auto-detect foreign keys, generate proper field types, and create relationship methods.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'model_name' => $schema->string()
                ->description('Name of the Eloquent model to generate repository for (e.g., "User", "BlogPost")'),
            'include_fields' => $schema->boolean()
                ->description('Generate fields from model database schema')
                ->optional(),
            'include_relationships' => $schema->boolean()
                ->description('Generate relationships (BelongsTo/HasMany) from schema analysis')
                ->optional(),
            'repository_name' => $schema->string()
                ->description('Override default repository name (default: {Model}Repository)')
                ->optional(),
            'namespace' => $schema->string()
                ->description('Override default namespace (auto-detected from existing repositories)')
                ->optional(),
            'force' => $schema->boolean()
                ->description('Overwrite existing repository file if it exists')
                ->optional(),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        try {
            $modelName = trim($request->get('model_name'));
            $includeFields = $request->get('include_fields', true);
            $includeRelationships = $request->get('include_relationships', true);
            $customRepositoryName = $request->get('repository_name');
            $customNamespace = $request->get('namespace');
            $force = $request->get('force', false);

            if (empty($modelName)) {
                return Response::error('Model name is required');
            }

            // Step 1: Find and resolve the model
            $modelClass = $this->resolveModelClass($modelName);
            if (! $modelClass) {
                return Response::error("Could not find model: {$modelName}");
            }

            // Step 2: Analyze existing repository patterns
            $repositoryInfo = $this->analyzeRepositoryPatterns();

            // Step 3: Generate repository details
            $repositoryDetails = $this->generateRepositoryDetails(
                $modelClass,
                $customRepositoryName,
                $customNamespace,
                $repositoryInfo
            );

            // Step 4: Check if repository already exists
            if (! $force && File::exists($repositoryDetails['file_path'])) {
                return Response::error(
                    "Repository already exists at: {$repositoryDetails['file_path']}\n".
                    "Use 'force: true' to overwrite."
                );
            }

            // Step 5: Generate repository content
            $repositoryContent = $this->generateRepositoryContent(
                $modelClass,
                $repositoryDetails,
                $includeFields,
                $includeRelationships
            );

            // Step 6: Create the repository file
            $this->createRepositoryFile($repositoryDetails['file_path'], $repositoryContent);

            // Step 7: Generate response
            return $this->generateSuccessResponse($modelClass, $repositoryDetails, $repositoryContent);

        } catch (\Throwable $e) {
            return Response::error('Repository generation failed: '.$e->getMessage());
        }
    }

    protected function resolveModelClass(string $modelName): ?string
    {
        // Clean the model name
        $modelName = str_replace(['/', '\\'], '', trim($modelName));

        // Try common model locations
        $commonLocations = [
            "App\\Models\\{$modelName}",
            "App\\{$modelName}",
            $this->getRootNamespace()."\\Models\\{$modelName}",
            $this->getRootNamespace()."\\{$modelName}",
        ];

        foreach ($commonLocations as $location) {
            if (class_exists($location) && $this->isEloquentModel($location)) {
                return $location;
            }
        }

        // Search the entire app directory
        $foundModels = $this->searchForModelInApp($modelName);

        if (count($foundModels) === 1) {
            return $foundModels[0];
        } elseif (count($foundModels) > 1) {
            // Return the first one found, preferring Models namespace
            usort($foundModels, function ($a, $b) {
                $aInModels = str_contains($a, '\\Models\\');
                $bInModels = str_contains($b, '\\Models\\');

                if ($aInModels && ! $bInModels) {
                    return -1;
                }
                if (! $aInModels && $bInModels) {
                    return 1;
                }

                return 0;
            });

            return $foundModels[0];
        }

        return null;
    }

    protected function searchForModelInApp(string $modelName): array
    {
        $models = [];

        try {
            $finder = new Finder;
            $finder->files()
                ->in(app_path())
                ->name('*.php')
                ->notPath('Http')
                ->notPath('Console')
                ->notPath('Exceptions')
                ->notPath('Providers');

            foreach ($finder as $file) {
                $relativePath = str_replace(app_path().DIRECTORY_SEPARATOR, '', $file->getRealPath());
                $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
                $className = 'App\\'.str_replace(['/', '.php'], ['\\', ''], $relativePath);

                if (class_exists($className) && $this->isEloquentModel($className)) {
                    $baseName = class_basename($className);
                    if (strcasecmp($baseName, $modelName) === 0 ||
                        strcasecmp($baseName, Str::plural($modelName)) === 0 ||
                        strcasecmp($baseName, Str::singular($modelName)) === 0) {
                        $models[] = $className;
                    }
                }
            }
        } catch (\Exception $e) {
            // Return empty array on failure
        }

        return array_unique($models);
    }

    protected function isEloquentModel(string $className): bool
    {
        try {
            $reflection = new ReflectionClass($className);

            return $reflection->isInstantiable() &&
                   $reflection->isSubclassOf('Illuminate\\Database\\Eloquent\\Model');
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function analyzeRepositoryPatterns(): array
    {
        $patterns = [
            'namespace' => $this->getRootNamespace().'\\Restify',
            'pattern' => 'flat',
            'base_path' => app_path('Restify'),
        ];

        try {
            $finder = new Finder;
            $finder->files()
                ->in(app_path())
                ->name('*Repository.php')
                ->notPath('vendor')
                ->notPath('tests');

            $repositoryPaths = [];
            foreach ($finder as $file) {
                $relativePath = str_replace(app_path().DIRECTORY_SEPARATOR, '', $file->getRealPath());
                $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

                $pathParts = explode('/', $relativePath);
                array_pop($pathParts); // Remove filename

                if (! empty($pathParts)) {
                    $namespace = $this->getRootNamespace().'\\'.str_replace('/', '\\', implode('/', $pathParts));
                    $basePath = app_path(implode('/', $pathParts));

                    $repositoryPaths[] = [
                        'namespace' => $namespace,
                        'base_path' => $basePath,
                        'depth' => count($pathParts),
                    ];
                }
            }

            if (! empty($repositoryPaths)) {
                // Use the most common pattern (or deepest if tie)
                usort($repositoryPaths, fn ($a, $b) => $b['depth'] <=> $a['depth']);
                $patterns = $repositoryPaths[0];
            }

        } catch (\Exception $e) {
            // Use defaults on error
        }

        return $patterns;
    }

    protected function generateRepositoryDetails(
        string $modelClass,
        ?string $customRepositoryName,
        ?string $customNamespace,
        array $repositoryInfo
    ): array {
        $modelBaseName = class_basename($modelClass);
        $repositoryName = $customRepositoryName ?: $modelBaseName.'Repository';
        $namespace = $customNamespace ?: $repositoryInfo['namespace'];
        $basePath = $repositoryInfo['base_path'];

        // Ensure repository name ends with 'Repository'
        if (! str_ends_with($repositoryName, 'Repository')) {
            $repositoryName .= 'Repository';
        }

        $filePath = $basePath.'/'.$repositoryName.'.php';

        return [
            'repository_name' => $repositoryName,
            'namespace' => $namespace,
            'file_path' => $filePath,
            'model_class' => $modelClass,
            'model_base_name' => $modelBaseName,
        ];
    }

    protected function generateRepositoryContent(
        string $modelClass,
        array $repositoryDetails,
        bool $includeFields,
        bool $includeRelationships
    ): array {
        $model = new $modelClass;
        $table = $model->getTable();

        $fields = [];
        $relationships = [];
        $imports = ['Binaryk\\LaravelRestify\\Http\\Requests\\RestifyRequest'];

        if ($includeFields && Schema::hasTable($table)) {
            $fieldsResult = $this->generateFields($table);
            $fields = $fieldsResult['fields'];
            $imports = array_merge($imports, $fieldsResult['imports']);
        }

        if ($includeRelationships && Schema::hasTable($table)) {
            $relationshipsResult = $this->generateRelationships($modelClass, $table);
            $relationships = $relationshipsResult['relationships'];
            $imports = array_merge($imports, $relationshipsResult['imports']);
        }

        return [
            'namespace' => $repositoryDetails['namespace'],
            'repository_name' => $repositoryDetails['repository_name'],
            'model_class' => $modelClass,
            'model_base_name' => $repositoryDetails['model_base_name'],
            'fields' => $fields,
            'relationships' => $relationships,
            'imports' => array_unique($imports),
        ];
    }

    protected function generateFields(string $table): array
    {
        $columns = Schema::getColumnListing($table);
        $fields = ['            id(),'];
        $imports = [];

        foreach ($columns as $column) {
            if ($column === 'id') {
                continue;
            }

            // Skip foreign key columns as they'll be handled by relationships
            if (str_ends_with($column, '_id') && $column !== 'id') {
                continue;
            }

            $field = $this->generateFieldForColumn($table, $column);
            if ($field) {
                $fields[] = $field['code'];
                $imports = array_merge($imports, $field['imports']);
            }
        }

        return [
            'fields' => $fields,
            'imports' => array_unique($imports),
        ];
    }

    protected function generateFieldForColumn(string $table, string $column): ?array
    {
        try {
            $columnType = Schema::getColumnType($table, $column);
            $field = "            field('{$column}')";
            $imports = [];

            // Add type-specific modifiers
            switch ($columnType) {
                case 'string':
                case 'varchar':
                    if (str_contains($column, 'email')) {
                        $field .= '->email()';
                    } elseif (str_contains($column, 'password')) {
                        $field .= '->password()->storable()';
                    }
                    break;

                case 'text':
                case 'longtext':
                case 'mediumtext':
                    $field .= '->textarea()';
                    break;

                case 'integer':
                case 'bigint':
                case 'smallint':
                    $field .= '->number()';
                    break;

                case 'boolean':
                case 'tinyint':
                    $field .= '->boolean()';
                    break;

                case 'date':
                    $field .= '->date()';
                    break;

                case 'datetime':
                case 'timestamp':
                    $field .= '->datetime()';
                    break;

                case 'decimal':
                case 'float':
                case 'double':
                    $field .= '->number()';
                    break;

                case 'json':
                    $field .= '->json()';
                    break;
            }

            // Check if column is nullable
            $isNullable = $this->isColumnNullable($table, $column);
            if ($isNullable) {
                $field .= '->nullable()';
            }

            // Handle special readonly fields
            if (in_array($column, ['created_at', 'updated_at', 'deleted_at', 'email_verified_at'])) {
                $field .= '->readonly()';
            }

            // Add required validation for non-nullable fields
            if (! $isNullable &&
                ! in_array($column, ['created_at', 'updated_at', 'deleted_at', 'remember_token']) &&
                ! str_contains($field, 'readonly()')) {
                $field .= '->required()';
            }

            $field .= ',';

            return [
                'code' => $field,
                'imports' => $imports,
            ];

        } catch (\Exception $e) {
            return null;
        }
    }

    protected function isColumnNullable(string $table, string $column): bool
    {
        try {
            $connection = Schema::getConnection();
            $dbName = $connection->getDatabaseName();

            $results = $connection->select('
                SELECT IS_NULLABLE 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = ? 
                AND TABLE_NAME = ? 
                AND COLUMN_NAME = ?
            ', [$dbName, $table, $column]);

            return ! empty($results) && $results[0]->IS_NULLABLE === 'YES';
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function generateRelationships(string $modelClass, string $table): array
    {
        $relationships = [];
        $imports = [];

        // Generate BelongsTo relationships
        $columns = Schema::getColumnListing($table);
        foreach ($columns as $column) {
            if (str_ends_with($column, '_id') && $column !== 'id') {
                $relationship = $this->generateBelongsToRelationship($column);
                if ($relationship) {
                    $relationships[] = $relationship['code'];
                    $imports = array_merge($imports, $relationship['imports']);
                }
            }
        }

        // Generate HasMany relationships
        $hasManyRelationships = $this->detectHasManyRelationships($modelClass, $table);
        foreach ($hasManyRelationships as $relationship) {
            $relationships[] = $relationship['code'];
            $imports = array_merge($imports, $relationship['imports']);
        }

        // Add relationship field imports
        if (! empty($relationships)) {
            $imports[] = 'Binaryk\\LaravelRestify\\Fields\\BelongsTo';
            $imports[] = 'Binaryk\\LaravelRestify\\Fields\\HasMany';
        }

        return [
            'relationships' => $relationships,
            'imports' => array_unique($imports),
        ];
    }

    protected function generateBelongsToRelationship(string $column): ?array
    {
        $relationName = Str::camel(Str::beforeLast($column, '_id'));
        $modelName = Str::studly(Str::beforeLast($column, '_id'));

        // Try to find the related model
        $relatedModel = $this->findRelatedModel($modelName);
        if (! $relatedModel) {
            return [
                'code' => "            BelongsTo::make('{$relationName}'),",
                'imports' => [],
            ];
        }

        return [
            'code' => "            BelongsTo::make('{$relationName}'),",
            'imports' => [],
        ];
    }

    protected function detectHasManyRelationships(string $modelClass, string $table): array
    {
        $relationships = [];
        $modelBaseName = class_basename($modelClass);
        $expectedForeignKey = Str::snake($modelBaseName).'_id';

        try {
            $tables = Schema::getAllTables();

            foreach ($tables as $tableObj) {
                $otherTable = is_object($tableObj) ?
                    ($tableObj->name ?? $tableObj->tablename ?? reset($tableObj)) :
                    $tableObj;

                if ($otherTable === $table) {
                    continue;
                }

                if (Schema::hasColumn($otherTable, $expectedForeignKey)) {
                    $relationName = Str::camel(Str::plural($otherTable));

                    $relationships[] = [
                        'code' => "            HasMany::make('{$relationName}'),",
                        'imports' => [],
                    ];
                }
            }
        } catch (\Exception $e) {
            // Return empty on error
        }

        return $relationships;
    }

    protected function findRelatedModel(string $modelName): ?string
    {
        $possibleClasses = [
            "App\\Models\\{$modelName}",
            "App\\{$modelName}",
            $this->getRootNamespace()."\\Models\\{$modelName}",
            $this->getRootNamespace()."\\{$modelName}",
        ];

        foreach ($possibleClasses as $class) {
            if (class_exists($class) && $this->isEloquentModel($class)) {
                return $class;
            }
        }

        return null;
    }

    protected function createRepositoryFile(string $filePath, array $content): void
    {
        // Ensure directory exists
        $directory = dirname($filePath);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // Generate repository file content
        $fileContent = $this->generateRepositoryFileContent($content);

        // Write the file
        File::put($filePath, $fileContent);
    }

    protected function generateRepositoryFileContent(array $content): string
    {
        $imports = array_merge(['Binaryk\\LaravelRestify\\Http\\Requests\\RestifyRequest'], $content['imports']);
        $imports = array_unique($imports);
        sort($imports);

        $importsString = implode("\n", array_map(fn ($import) => "use {$import};", $imports));

        $fieldsString = empty($content['fields']) ?
            '            id(),' :
            implode("\n", $content['fields']);

        $relationshipsSection = '';
        if (! empty($content['relationships'])) {
            $relationshipsString = implode("\n", $content['relationships']);
            $relationshipsSection = "\n\n    public static function include(): array\n    {\n        return [\n{$relationshipsString}\n        ];\n    }";
        }

        return "<?php

declare(strict_types=1);

namespace {$content['namespace']};

{$importsString}
use {$content['model_class']};
use Binaryk\\LaravelRestify\\Repository;

class {$content['repository_name']} extends Repository
{
    public static \$model = {$content['model_base_name']}::class;

    public function fields(RestifyRequest \$request): array
    {
        return [
{$fieldsString}
        ];
    }{$relationshipsSection}
}
";
    }

    protected function generateSuccessResponse(string $modelClass, array $repositoryDetails, array $content): ToolResult
    {
        $response = "# Repository Generated Successfully!\n\n";
        $response .= "**Repository:** `{$repositoryDetails['repository_name']}`\n";
        $response .= "**Model:** `{$repositoryDetails['model_base_name']}`\n";
        $response .= "**File:** `{$repositoryDetails['file_path']}`\n";
        $response .= "**Namespace:** `{$repositoryDetails['namespace']}`\n\n";

        $response .= "## Generated Features\n\n";

        if (! empty($content['fields'])) {
            $fieldCount = count($content['fields']);
            $response .= "✅ **Fields:** Generated {$fieldCount} field(s) from model schema\n";
        }

        if (! empty($content['relationships'])) {
            $relationCount = count($content['relationships']);
            $response .= "✅ **Relationships:** Generated {$relationCount} relationship(s)\n";
        }

        $response .= "\n## Next Steps\n\n";
        $response .= "1. **Review the generated repository** at `{$repositoryDetails['file_path']}`\n";
        $response .= "2. **Register the repository** in your routes or RestifyServiceProvider\n";
        $response .= "3. **Customize fields and relationships** as needed\n";
        $response .= "4. **Add validation rules, authorization, and custom logic**\n\n";

        if (! empty($content['fields'])) {
            $response .= "## Generated Fields\n\n";
            foreach ($content['fields'] as $field) {
                $fieldLine = trim($field);
                $response .= "- `{$fieldLine}`\n";
            }
            $response .= "\n";
        }

        if (! empty($content['relationships'])) {
            $response .= "## Generated Relationships\n\n";
            foreach ($content['relationships'] as $relationship) {
                $relationshipLine = trim($relationship);
                $response .= "- `{$relationshipLine}`\n";
            }
            $response .= "\n";
        }

        $response .= "## Additional Commands\n\n";
        $response .= "Generate related components:\n";
        $response .= "- **Policy:** `php artisan restify:policy {$repositoryDetails['model_base_name']}`\n";
        $response .= "- **Factory:** `php artisan make:factory {$repositoryDetails['model_base_name']}Factory`\n";
        $response .= '- **Migration:** `php artisan make:migration create_'.Str::snake(Str::plural($repositoryDetails['model_base_name']))."_table`\n";

        return Response::text($response);
    }

    protected function getRootNamespace(): string
    {
        return 'App';
    }
}
