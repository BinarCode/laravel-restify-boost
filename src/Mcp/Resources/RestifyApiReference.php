<?php

declare(strict_types=1);

namespace BinarCode\RestifyBoost\Mcp\Resources;

use BinarCode\RestifyBoost\Services\DocParser;
use Laravel\Mcp\Server\Contracts\Resources\Content;
use Laravel\Mcp\Server\Resource;

class RestifyApiReference extends Resource
{
    public function __construct(protected DocParser $parser) {}

    public function description(): string
    {
        return 'Complete Laravel Restify API reference with detailed method signatures, field types, relationship patterns, and implementation examples. Includes repositories, fields, relations, actions, filters, authentication, and MCP-specific features.';
    }

    public function read(): string|Content
    {
        try {
            $apiReference = $this->buildApiReference();

            $response = [
                'package' => 'Laravel Restify API Reference',
                'version' => $this->getRestifyVersion(),
                'description' => 'A comprehensive API reference for Laravel Restify - a powerful Laravel package for building RESTful APIs',
                'api_reference' => $apiReference,
                'quick_start' => $this->getQuickStartGuide(),
                'generated_at' => now()->toIso8601String(),
            ];

            return json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return "Error generating Laravel Restify API reference: {$e->getMessage()}";
        }
    }

    protected function buildApiReference(): array
    {
        return [
            'repositories' => $this->getRepositoryReference(),
            'fields' => $this->getFieldReference(),
            'relations' => $this->getRelationReference(),
            'actions' => $this->getActionReference(),
            'filters' => $this->getFilterReference(),
            'authentication' => $this->getAuthReference(),
            'mcp_integration' => $this->getMcpReference(),
            'bulk_operations' => $this->getBulkOperationsReference(),
            'lifecycle_hooks' => $this->getLifecycleHooksReference(),
        ];
    }

    protected function getRepositoryReference(): array
    {
        return [
            'description' => 'Repository is the core of Laravel Restify - manages CRUD operations and API endpoints',
            'base_class' => 'Binaryk\\LaravelRestify\\Repositories\\Repository',
            'key_properties' => [
                '$model' => 'string - The Eloquent model class (e.g., Post::class)',
                '$uriKey' => 'string - Custom URI segment for endpoints (optional)',
                '$public' => 'bool|array - Allow unauthenticated access to GET endpoints',
                '$withs' => 'array - Force eager load relationships',
                '$groupBy' => 'array - Group results by columns',
                '$middleware' => 'array - Apply middleware to repository routes',
            ],
            'core_methods' => [
                'fields(RestifyRequest $request)' => 'Define the fields exposed by the repository',
                'fieldsForIndex(RestifyRequest $request)' => 'Fields specific to index requests',
                'fieldsForShow(RestifyRequest $request)' => 'Fields specific to show requests',
                'fieldsForStore(RestifyRequest $request)' => 'Fields specific to store requests',
                'fieldsForUpdate(RestifyRequest $request)' => 'Fields specific to update requests',
                'related()' => 'Define relationships available for eager loading',
                'filters(RestifyRequest $request)' => 'Define available filters',
                'actions(RestifyRequest $request)' => 'Define available actions',
            ],
            'mcp_methods' => [
                'fieldsForMcpIndex(RestifyRequest $request)' => 'Optimized fields for AI index requests (saves 60-70% tokens)',
                'fieldsForMcpShow(RestifyRequest $request)' => 'Focused fields for AI detail views (saves 40-50% tokens)',
                'fieldsForMcpStore(RestifyRequest $request)' => 'Fields AI agents can use for creation',
                'fieldsForMcpUpdate(RestifyRequest $request)' => 'Fields AI agents can modify',
                'fieldsForMcpStoreBulk(RestifyRequest $request)' => 'Efficient AI bulk creation fields',
                'fieldsForMcpUpdateBulk(RestifyRequest $request)' => 'Efficient AI bulk update fields',
                'fieldsForMcpGetter(RestifyRequest $request)' => 'Analytical and computed fields for AI consumption',
            ],
            'lifecycle_hooks' => [
                'stored($model, $request)' => 'Called after single resource creation',
                'updated($model, $request)' => 'Called after single resource update',
                'deleted($status, $request)' => 'Called after single resource deletion',
                'storedBulk(Collection $models, $request)' => 'Called after bulk creation',
                'updatedBulk(Collection $models, $request)' => 'Called after bulk update',
                'deletedBulk(Collection $models, $request)' => 'Called after bulk deletion',
            ],
            'authorization_methods' => [
                'allowToShow(RestifyRequest $request)' => 'Authorize showing resource',
                'allowToStore(RestifyRequest $request, $payload = null)' => 'Authorize creating resource',
                'allowToUpdate(RestifyRequest $request, $payload = null)' => 'Authorize updating resource',
                'allowToDestroy(RestifyRequest $request)' => 'Authorize deleting resource',
                'allowToBulkStore(RestifyRequest $request, $payload = null)' => 'Authorize bulk creation',
                'allowToUpdateBulk(RestifyRequest $request, $payload = null)' => 'Authorize bulk updates',
                'allowToDestroyBulk(RestifyRequest $request, $payload = null)' => 'Authorize bulk deletion',
            ],
            'endpoints' => [
                'GET /api/restify/{repository}' => 'List resources (index)',
                'GET /api/restify/{repository}/{id}' => 'Show specific resource',
                'POST /api/restify/{repository}' => 'Create new resource',
                'PUT|PATCH /api/restify/{repository}/{id}' => 'Update resource',
                'DELETE /api/restify/{repository}/{id}' => 'Delete resource',
                'POST /api/restify/{repository}/bulk' => 'Bulk create',
                'POST /api/restify/{repository}/bulk/update' => 'Bulk update',
                'DELETE /api/restify/{repository}/bulk/delete' => 'Bulk delete',
                'GET /api/restify/{repository}/actions' => 'List available actions',
                'POST /api/restify/{repository}/actions?action={name}' => 'Execute action',
            ],
            'example' => '<?php

namespace App\\Restify;

use App\\Models\\Post;
use Binaryk\\LaravelRestify\\Http\\Requests\\RestifyRequest;
use Binaryk\\LaravelRestify\\Repositories\\Repository;

class PostRepository extends Repository
{
    public static string $model = Post::class;

    public function fields(RestifyRequest $request): array
    {
        return [
            field(\'title\')->required(),
            field(\'content\')->rules(\'required\'),
            field(\'published_at\')->nullable(),
            field(\'author_id\')->value(auth()->id())->hidden(),
        ];
    }

    // MCP-optimized fields (saves tokens for AI)
    public function fieldsForMcpIndex(RestifyRequest $request): array
    {
        return [
            field(\'id\'),
            field(\'title\'),
            field(\'published_at\'),
        ];
    }

    public static function related(): array
    {
        return [
            HasMany::make(\'comments\'),
            BelongsTo::make(\'author\', UserRepository::class),
        ];
    }
}',
        ];
    }

    protected function getFieldReference(): array
    {
        return [
            'description' => 'Fields define how model attributes are exposed and validated in the API',
            'base_class' => 'Binaryk\\LaravelRestify\\Fields\\Field',
            'field_types' => [
                'Field' => 'Basic field for any attribute',
                'Text' => 'Text input field',
                'Textarea' => 'Textarea input field',
                'Select' => 'Select dropdown field',
                'Boolean' => 'Boolean/checkbox field',
                'Number' => 'Number input field',
                'Date' => 'Date picker field',
                'DateTime' => 'DateTime picker field',
                'File' => 'File upload field',
                'Image' => 'Image upload field with validation',
            ],
            'field_methods' => [
                'rules(...$rules)' => 'Add validation rules',
                'storingRules(...$rules)' => 'Rules only for creation',
                'updatingRules(...$rules)' => 'Rules only for updates',
                'storeBulkRules(...$rules)' => 'Rules for bulk creation',
                'updateBulkRules(...$rules)' => 'Rules for bulk updates',
                'required()' => 'Mark field as required',
                'nullable()' => 'Allow null values',
                'readonly()' => 'Make field read-only',
                'hidden()' => 'Hide field from responses',
                'value($value)' => 'Set default value for field',
                'default($callback)' => 'Set callback for default display value',
                'label($label)' => 'Set custom field label/attribute name',
            ],
            'validation_helpers' => [
                'required()' => 'Mark field as required',
                'nullable()' => 'Allow null values',
                'email()' => 'Validate as email',
                'numeric()' => 'Validate as numeric',
                'integer()' => 'Validate as integer',
                'boolean()' => 'Validate as boolean',
                'string()' => 'Validate as string',
                'array()' => 'Validate as array',
                'url()' => 'Validate as URL',
                'uuid()' => 'Validate as UUID',
                'date()' => 'Validate as date',
                'min($value)' => 'Set minimum value/length',
                'max($value)' => 'Set maximum value/length',
                'between($min, $max)' => 'Set value/length between min and max',
                'unique($table, $column)' => 'Validate as unique in database',
                'exists($table, $column)' => 'Validate existence in database',
                'confirmed()' => 'Must be confirmed (field_confirmation)',
                'regex($pattern)' => 'Validate with regex pattern',
                'in(array $values)' => 'Must be one of given values',
                'notIn(array $values)' => 'Must not be one of given values',
            ],
            'authorization_methods' => [
                'canSee($callback)' => 'Control field visibility',
                'canStore($callback)' => 'Control if field can be stored',
                'canUpdate($callback)' => 'Control if field can be updated',
                'canPatch($callback)' => 'Control PATCH operations',
                'canUpdateBulk($callback)' => 'Control bulk updates',
            ],
            'callbacks' => [
                'fillCallback($callback)' => 'Transform value from request before storing',
                'storeCallback($callback)' => 'Transform value during creation',
                'updateCallback($callback)' => 'Transform value during update',
                'storeBulkCallback($callback)' => 'Transform value during bulk creation',
                'indexCallback($callback)' => 'Transform value for index display',
                'showCallback($callback)' => 'Transform value for show display',
                'resolveCallback($callback)' => 'Transform value for both index and show',
            ],
            'mcp_methods' => [
                'showOnMcp($callback = true)' => 'Control visibility in MCP requests',
                'hideFromMcp($callback = true)' => 'Hide field from MCP requests',
                'toolSchema($callback)' => 'Define custom schema for MCP tools',
            ],
            'file_methods' => [
                'disk($disk)' => 'Specify storage disk',
                'path($path)' => 'Custom storage path',
                'storeAs($filename)' => 'Custom filename',
                'storeOriginalName($column)' => 'Store original filename',
                'storeSize($column)' => 'Store file size',
                'deletable($deletable = true)' => 'Allow file deletion',
            ],
            'example' => 'field(\'email\')
    ->required()
    ->email()
    ->unique(\'users\', \'email\')
    ->max(255)
    ->canSee(fn($request) => $request->user()->isAdmin())
    ->fillCallback(fn($request, $model, $attribute) => strtolower($request->input($attribute)))',
        ];
    }

    protected function getRelationReference(): array
    {
        return [
            'description' => 'Relations define how to eager load and manage model relationships through the API',
            'relation_types' => [
                'BelongsTo' => 'One-to-one inverse relationship',
                'HasOne' => 'One-to-one relationship',
                'HasMany' => 'One-to-many relationship',
                'BelongsToMany' => 'Many-to-many relationship',
                'MorphOne' => 'Polymorphic one-to-one',
                'MorphMany' => 'Polymorphic one-to-many',
                'MorphToMany' => 'Polymorphic many-to-many',
            ],
            'relation_methods' => [
                'searchable(...$attributes)' => 'Make relation searchable (BelongsTo only)',
                'sortable($attribute)' => 'Allow sorting by relation attribute',
                'withPivot(...$fields)' => 'Include pivot fields (many-to-many)',
                'canAttach($callback)' => 'Authorize attach operations',
                'canDetach($callback)' => 'Authorize detach operations',
                'canSync($callback)' => 'Authorize sync operations',
                'attachCallback($callback)' => 'Override attach logic',
                'detachCallback($callback)' => 'Override detach logic',
                'syncCallback($callback)' => 'Override sync logic',
            ],
            'endpoints' => [
                'GET /api/restify/{repository}?include={relation}' => 'Include relation in response',
                'POST /api/restify/{repository}/{id}/attach/{relation}' => 'Attach related models',
                'POST /api/restify/{repository}/{id}/detach/{relation}' => 'Detach related models',
                'POST /api/restify/{repository}/{id}/sync/{relation}' => 'Sync related models',
            ],
            'query_examples' => [
                '?include=posts' => 'Include posts relationship',
                '?include=posts[id,title]' => 'Include posts with specific columns',
                '?include=user.posts,comments' => 'Include nested relationships',
                '?include=posts.comments[comment]' => 'Deep nested with column selection',
            ],
            'example' => 'public static function related(): array
{
    return [
        BelongsTo::make(\'user\')->searchable(\'name\', \'email\'),
        HasMany::make(\'comments\'),
        BelongsToMany::make(\'tags\')
            ->withPivot(field(\'created_at\'))
            ->canAttach(fn($request) => $request->user()->isAdmin()),
    ];
}',
        ];
    }

    protected function getActionReference(): array
    {
        return [
            'description' => 'Actions allow custom operations on resources beyond standard CRUD',
            'base_class' => 'Binaryk\\LaravelRestify\\Actions\\Action',
            'action_methods' => [
                'handle(ActionRequest $request, Collection $models)' => 'Execute the action logic',
                'fields()' => 'Define fields required for the action',
                'authorize($request, $repository)' => 'Authorize action execution',
                'name()' => 'Define the action display name',
                'uriKey()' => 'Define the URI key for the action',
            ],
            'endpoints' => [
                'GET /api/restify/{repository}/actions' => 'List available actions',
                'POST /api/restify/{repository}/actions?action={name}' => 'Execute index action',
                'POST /api/restify/{repository}/{id}/actions?action={name}' => 'Execute individual action',
            ],
            'example' => '<?php

namespace App\\Restify\\Actions;

use Binaryk\\LaravelRestify\\Actions\\Action;
use Binaryk\\LaravelRestify\\Http\\Requests\\ActionRequest;
use Illuminate\\Support\\Collection;

class PublishPost extends Action
{
    public function handle(ActionRequest $request, Collection $models)
    {
        $models->each(function ($post) {
            $post->update([\'published_at\' => now()]);
        });

        return $this->message(\'Posts published successfully\');
    }

    public function authorize($request, $repository): bool
    {
        return $request->user()->can(\'publish\', $repository->model());
    }
}',
        ];
    }

    protected function getFilterReference(): array
    {
        return [
            'description' => 'Filters provide searching, sorting, and matching capabilities for API queries',
            'filter_types' => [
                'SearchableFilter' => 'Full-text search across fields',
                'MatchFilter' => 'Exact match filtering',
                'SortableFilter' => 'Sorting capabilities',
                'SelectFilter' => 'Dropdown-style filtering',
                'BooleanFilter' => 'Boolean filtering',
            ],
            'query_parameters' => [
                '?search=term' => 'Search across searchable fields',
                '?{field}=value' => 'Exact match filter',
                '?sort={field}' => 'Sort ascending by field',
                '?sort=-{field}' => 'Sort descending by field',
                '?page=2&per_page=50' => 'Pagination controls',
            ],
            'example' => 'public function filters(RestifyRequest $request): array
{
    return [
        SearchableFilter::make()->searchables([
            \'title\', \'content\', \'user.name\'
        ]),
        SortableFilter::make()->sortables([
            \'title\', \'created_at\', \'user.name\'
        ]),
        MatchFilter::make(\'status\', fn($query, $value) =>
            $query->where(\'status\', $value)
        ),
    ];
}',
        ];
    }

    protected function getAuthReference(): array
    {
        return [
            'description' => 'Authentication and authorization methods for controlling API access',
            'gate_methods' => [
                'viewRestify' => 'Control general API access',
            ],
            'policy_methods' => [
                'allowRestify(User $user)' => 'Allow access to repository',
                'show(User $user, Model $model)' => 'View specific resource',
                'store(User $user)' => 'Create new resource',
                'update(User $user, Model $model)' => 'Update resource',
                'delete(User $user, Model $model)' => 'Delete resource',
                'storeBulk(User $user)' => 'Bulk create authorization',
                'updateBulk(User $user)' => 'Bulk update authorization',
                'deleteBulk(User $user)' => 'Bulk delete authorization',
                'attachUsers(User $user, Model $model, User $userToAttach)' => 'Attach relationship',
                'detachUsers(User $user, Model $model, User $userToDetach)' => 'Detach relationship',
                'syncPermissions(User $user, Model $model, Collection $ids)' => 'Sync relationship',
            ],
            'example' => '<?php

namespace App\\Policies;

use App\\Models\\Post;
use App\\Models\\User;

class PostPolicy
{
    public function allowRestify(?User $user): bool
    {
        return $user !== null;
    }

    public function show(?User $user, Post $post): bool
    {
        return $post->published_at || $user?->id === $post->author_id;
    }

    public function store(User $user): bool
    {
        return $user->can(\'create-posts\');
    }
}',
        ];
    }

    protected function getMcpReference(): array
    {
        return [
            'description' => 'Model Context Protocol integration for AI-optimized API interactions',
            'mcp_field_methods' => [
                'fieldsForMcpIndex' => 'Optimized fields for AI listing (saves 60-70% tokens)',
                'fieldsForMcpShow' => 'Focused fields for AI detail views (saves 40-50% tokens)',
                'fieldsForMcpStore' => 'Fields AI agents can use for creation',
                'fieldsForMcpUpdate' => 'Fields AI agents can modify',
                'fieldsForMcpStoreBulk' => 'Efficient AI bulk creation',
                'fieldsForMcpUpdateBulk' => 'Efficient AI bulk updates',
                'fieldsForMcpGetter' => 'Analytical fields for AI consumption',
            ],
            'mcp_visibility' => [
                'showOnMcp($callback)' => 'Control field visibility in MCP requests',
                'hideFromMcp($callback)' => 'Hide field from MCP requests',
                'toolSchema($callback)' => 'Define custom schema for MCP tools',
            ],
            'field_priority' => [
                '1. MCP-specific methods' => 'fieldsForMcpIndex, fieldsForMcpShow, etc.',
                '2. Request-specific methods' => 'fieldsForIndex, fieldsForShow, etc.',
                '3. Default fields method' => 'fields()',
            ],
            'benefits' => [
                'Token optimization' => 'Reduces API response size by 40-70%',
                'AI-friendly data' => 'Provides structured, relevant data for AI consumption',
                'Computed fields' => 'Include analytical data like word counts, reading time',
                'Schema definitions' => 'Help AI tools understand data structure',
            ],
            'example' => 'public function fieldsForMcpIndex(RestifyRequest $request): array
{
    return [
        field(\'id\'),
        field(\'title\'),
        field(\'excerpt\'),
        field(\'published_at\'),
    ];
}

public function fieldsForMcpGetter(RestifyRequest $request): array
{
    return [
        field(\'word_count\', fn() => str_word_count(strip_tags($this->content))),
        field(\'reading_time\', fn() => ceil(str_word_count(strip_tags($this->content)) / 200)),
        field(\'sentiment_score\', fn() => $this->calculateSentiment()),
    ];
}',
        ];
    }

    protected function getBulkOperationsReference(): array
    {
        return [
            'description' => 'Bulk operations for efficient batch processing of multiple resources',
            'endpoints' => [
                'POST /api/restify/{repository}/bulk' => 'Create multiple resources',
                'POST /api/restify/{repository}/bulk/update' => 'Update multiple resources',
                'DELETE /api/restify/{repository}/bulk/delete' => 'Delete multiple resources',
            ],
            'payload_examples' => [
                'bulk_store' => '[
    {"title": "Post 1", "content": "Content 1"},
    {"title": "Post 2", "content": "Content 2"}
]',
                'bulk_update' => '[
    {"id": 1, "title": "Updated Post 1"},
    {"id": 2, "title": "Updated Post 2"}
]',
                'bulk_delete' => '[1, 2, 3, 4, 5]',
            ],
            'validation' => [
                'storeBulkRules()' => 'Validation for bulk creation',
                'updateBulkRules()' => 'Validation for bulk updates',
            ],
            'authorization' => [
                'storeBulk(User $user)' => 'Policy method for bulk creation',
                'updateBulk(User $user)' => 'Policy method for bulk updates',
                'deleteBulk(User $user)' => 'Policy method for bulk deletion',
            ],
            'lifecycle_hooks' => [
                'storedBulk(Collection $models, $request)' => 'After bulk creation',
                'updatedBulk(Collection $models, $request)' => 'After bulk updates',
                'deletedBulk(Collection $models, $request)' => 'After bulk deletion',
            ],
        ];
    }

    protected function getLifecycleHooksReference(): array
    {
        return [
            'description' => 'Lifecycle hooks for performing actions at specific points during repository operations',
            'single_resource_events' => [
                'stored($model, $request)' => 'After resource creation',
                'updated($model, $request)' => 'After resource update',
                'deleted($status, $request)' => 'After resource deletion',
            ],
            'bulk_operation_events' => [
                'storedBulk(Collection $models, $request)' => 'After bulk creation',
                'updatedBulk(Collection $models, $request)' => 'After bulk updates',
                'savedBulk(Collection $models, $request)' => 'After any bulk save operation',
                'deletedBulk(Collection $models, $request)' => 'After bulk deletion',
            ],
            'common_use_cases' => [
                'Logging and auditing' => 'Track all changes to resources',
                'Cache management' => 'Clear or update caches when data changes',
                'Search indexing' => 'Update search indexes after modifications',
                'Notifications' => 'Send emails, push notifications, or webhooks',
                'External API integration' => 'Sync changes with third-party services',
            ],
            'example' => 'public static function stored($model, $request)
{
    // Log the creation
    Log::info("Post created: {$model->title}");

    // Send notifications
    NotificationService::notifyNewPost($model);

    // Update caches
    cache()->forget(\'recent_posts\');
}',
        ];
    }

    protected function getQuickStartGuide(): array
    {
        return [
            'installation' => [
                'composer_install' => 'composer require binaryk/laravel-restify',
                'publish_config' => 'php artisan vendor:publish --tag=restify-config',
                'create_repository' => 'php artisan restify:repository PostRepository',
            ],
            'basic_repository' => '<?php

namespace App\\Restify;

use App\\Models\\Post;
use Binaryk\\LaravelRestify\\Http\\Requests\\RestifyRequest;
use Binaryk\\LaravelRestify\\Repositories\\Repository;

class PostRepository extends Repository
{
    public static string $model = Post::class;

    public function fields(RestifyRequest $request): array
    {
        return [
            field(\'title\')->required(),
            field(\'content\')->required(),
            field(\'published_at\')->nullable(),
        ];
    }
}',
            'endpoints_available' => [
                'GET /api/restify/posts' => 'List all posts',
                'GET /api/restify/posts/1' => 'Show specific post',
                'POST /api/restify/posts' => 'Create new post',
                'PUT /api/restify/posts/1' => 'Update post',
                'DELETE /api/restify/posts/1' => 'Delete post',
            ],
        ];
    }

    protected function getRestifyVersion(): string
    {
        $composerLock = base_path('composer.lock');
        if (file_exists($composerLock)) {
            $lockData = json_decode(file_get_contents($composerLock), true);
            foreach (($lockData['packages'] ?? []) as $package) {
                if ($package['name'] === 'binaryk/laravel-restify') {
                    return $package['version'];
                }
            }
        }

        return 'unknown';
    }
}
