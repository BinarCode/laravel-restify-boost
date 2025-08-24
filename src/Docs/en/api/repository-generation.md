---
title: Repository Generation 
menuTitle: Repository Generation 
category: API 
position: 11
---

# Repository Generation with Relationships

Laravel Restify's repository generation command has been enhanced with intelligent path detection and automatic relationship generation, following Laravel Restify best practices.

## Intelligent Path Detection

The repository generator now automatically detects your project's repository organization pattern and creates new repositories in the appropriate location.

### Supported Patterns

1. **Grouped by Model** - `App/Restify/Users/UserRepository.php`
2. **Domain Driven** - `App/Restify/Domains/User/UserRepository.php`
3. **Module Based** - `App/Restify/Admin/UserRepository.php`
4. **Flat Structure** - `App/Restify/UserRepository.php` (default)

### How It Works

When you run:
```bash
php artisan restify:repository PostRepository
```

The command will:
1. First check the `app/Restify` directory for existing repositories
2. If none found in `app/Restify`, scan the entire `app/` directory
3. Analyze the location patterns of found repositories
4. Apply the same pattern to the new repository
5. Display the detected pattern and target location

This prioritization ensures that repositories in the standard `App/Restify` location are preferred over other locations.

### Example Output

```bash
$ php artisan restify:repository PostRepository
Detected repository pattern: grouped-by-model
Repository will be created in: App\Restify
Repository created successfully.
```

If your project has `UserRepository` in `App/Restify/Users/`, the new `PostRepository` will be created in `App/Restify/Posts/`.

## Automatic Relationship Detection

When you run the repository generation command:

```bash
php artisan restify:repository PostRepository
```

The command will:
1. Analyze your database schema for foreign key columns
2. Generate regular fields in the `fields()` method
3. Generate BelongsTo and HasMany relationships in a separate `static include()` method

## Generated Structure

For a `posts` table with `user_id` and `category_id` columns, and a `comments` table with `post_id`, the generated repository will look like:

```php
<?php

namespace App\Restify;

use App\Models\Post;
use Binaryk\LaravelRestify\Http\Requests\RestifyRequest;
use Binaryk\LaravelRestify\Fields\BelongsTo;
use Binaryk\LaravelRestify\Fields\HasMany;

class PostRepository extends Repository
{
    public static string $model = Post::class;

    public static function include(): array
    {
        return [
            BelongsTo::make('user', UserRepository::class),
            BelongsTo::make('category', CategoryRepository::class),
            HasMany::make('comments', CommentRepository::class),
        ];
    }

    public function fields(RestifyRequest $request): array
    {
        return [
            id(),
            field('title')->required(),
            field('content')->textarea()->required(),
            field('created_at')->datetime()->readonly(),
            field('updated_at')->datetime()->readonly(),
        ];
    }
}
```

## How It Works

### BelongsTo Detection
- Columns ending with `_id` (except `id` itself) are detected as BelongsTo relationships
- The relationship name is derived from the column name (e.g., `user_id` → `user`)
- The command attempts to find the related repository class automatically

### HasMany Detection
- The command scans other tables for foreign keys pointing to the current model
- For example, if `comments` table has `post_id`, it generates `HasMany::make('comments')`
- Repository classes are automatically resolved when possible

### Repository Resolution
The command searches for repository classes in these locations:
- `App\Restify\{Model}Repository`
- `App\Http\Restify\{Model}Repository`

If a repository isn't found, the relationship is still generated without the repository parameter, allowing Laravel Restify to auto-resolve it.

## Benefits

1. **Separation of Concerns**: Fields and relationships are kept in separate methods
2. **Clean Code**: Foreign key fields are not duplicated in the fields array
3. **Automatic Detection**: Reduces manual work when setting up repositories
4. **Follows Best Practices**: Uses the `static include()` method as recommended in Laravel Restify documentation

## Customization

You can always modify the generated relationships after creation:

```php
public static function include(): array
{
    return [
        BelongsTo::make('user', UserRepository::class)->searchable('name'),
        BelongsTo::make('category')->nullable(),
        HasMany::make('comments')->sortable('created_at'),
        
        // Add more relationships manually
        MorphMany::make('tags'),
        BelongsToMany::make('subscribers')->withPivot('subscribed_at'),
    ];
}
```

## Override Confirmation

If a repository already exists at the target location, the command will ask for confirmation before overriding:

```bash
$ php artisan restify:repository UserRepository
Detected repository pattern: flat
Repository will be created in: App\Restify
Repository already exists at: /path/to/app/Restify/UserRepository.php
Do you want to override it? (yes/no) [no]:
```

You can skip this confirmation by using the `--force` option:

```bash
php artisan restify:repository UserRepository --force
```

## Disabling Automatic Generation

If you prefer to handle relationships manually, use the `--no-fields` option:

```bash
php artisan restify:repository PostRepository --no-fields
```

This will generate a repository with only the `id()` field and no relationships.