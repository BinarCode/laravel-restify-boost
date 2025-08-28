# Restify Boost


## Introduction

Restify Boost accelerates AI-assisted [Laravel Restify](https://github.com/binarcode/laravel-restify) development by providing comprehensive documentation access and code generation tools through the Model Context Protocol.

At its foundation, this package is an MCP server equipped with specialized tools designed to streamline Laravel Restify development workflows. The server provides access to complete documentation, API references, code examples, and troubleshooting guides.

## Resources

- **[Laravel Restify](https://github.com/binarcode/laravel-restify)** - The main Laravel Restify package
- **[Official Documentation](https://restify.binarcode.com)** - Complete Laravel Restify documentation
- **[Free Templates](https://restifytemplates.com)** - Ready-to-use Laravel Restify templates


The first fully customizable Laravel [JSON:API](https://jsonapi.org) builder with MCP and GraphQL support. "CRUD" and protect your resources with 0 (zero) extra line of code.

<div>
<a href="https://restifytemplates.com">
<img alt="Save weeks of API development" src="/src/Docs/starter-kit.png">
</a>
</div>

## Installation

Restify Boost can be installed via Composer:

```bash
composer require binarcode/restify-boost --dev
```

Next, install the MCP server:

```bash
php artisan restify-boost:install
```

Once installed, you're ready to start coding with Cursor, Claude Code, or your AI agent of choice.

## Available MCP Tools

| Name                    | Notes                                                                                                      |
| ----------------------- |------------------------------------------------------------------------------------------------------------|
| Search Restify Docs     | Search through comprehensive Laravel Restify documentation with relevance scoring                          |
| Get Code Examples       | Extract specific code examples from documentation with language and category filtering                      |
| Navigate Docs           | Browse documentation structure and categories with overview and detailed navigation                        |
| Generate Repository     | Generate Laravel Restify repository classes with proper structure and methods                             |
| Generate Action         | Generate Laravel Restify action classes for custom business logic                                         |
| Generate Getter         | Generate Laravel Restify getter classes for data transformation                                           |
| Generate Match Filter   | Generate Laravel Restify match filter classes for advanced filtering                                      |
| Debug Application       | Comprehensive debugging tool for Laravel Restify applications with health checks and diagnostics          |
| Upgrade Restify         | Automated tool to upgrade Laravel Restify from version 9.x to 10.x with migration assistance             |

## Available Documentation

| Package | Coverage |
|---------|----------|
| Laravel Restify | Complete documentation including API methods, field types, authentication, authorization, and performance guides |

## Specialized Tools

### Debug Application Tool

The Debug Application Tool provides comprehensive diagnostics for Laravel Restify applications, helping developers identify and resolve common issues quickly.

#### Features

- **System Health Check**: Laravel version, PHP compatibility, environment validation, cache/storage testing
- **Configuration Analysis**: App config, database setup, Restify configuration validation  
- **Database Health**: Connection testing, migration status, performance checks
- **Restify Analysis**: Repository discovery, route validation, service provider verification
- **Performance Analysis**: Memory usage, cache/session driver optimization suggestions
- **Automatic Issue Detection**: Severity classification with detailed reporting
- **Safe Auto-Fixes**: Automatically resolve common configuration problems

#### Parameters

- `check_type` (optional): Target specific areas - 'all', 'config', 'database', 'restify', 'performance', 'health' (default: 'all')
- `detailed_output` (optional): Include comprehensive diagnostic details (default: true)
- `fix_issues` (optional): Automatically resolve common configuration problems (default: false)
- `export_report` (optional): Save detailed markdown reports to storage/logs (default: false)
- `include_suggestions` (optional): Provide actionable improvement recommendations (default: true)

#### Usage Examples

```bash
# Run complete diagnostic
Debug Application with check_type="all"

# Check only database health
Debug Application with check_type="database"

# Run with automatic fixes
Debug Application with fix_issues=true

# Export detailed report
Debug Application with export_report=true detailed_output=true
```

### Upgrade Restify Tool

The Upgrade Restify Tool automates the migration process from Laravel Restify 9.x to 10.x, ensuring smooth transitions with comprehensive analysis and backup creation.

#### Features

- **PHP Attributes Migration**: Converts static `$model` properties to modern `#[Model]` attributes
- **Field-Level Configuration**: Migrates static `$search`/`$sort` arrays to field-level methods
- **Configuration Compatibility**: Checks and validates config file compatibility
- **Backup Creation**: Automatically creates backups before making changes
- **Comprehensive Reporting**: Detailed analysis with migration recommendations
- **Interactive Mode**: Confirmation prompts for each repository migration
- **Complexity Scoring**: Evaluates repository complexity for prioritization

#### Parameters

- `dry_run` (optional): Preview changes without applying them (default: true)
- `migrate_attributes` (optional): Convert static `$model` properties to PHP attributes (default: true)
- `migrate_fields` (optional): Convert static `$search`/`$sort` arrays to field-level methods (default: true)
- `check_config` (optional): Check and report config file compatibility (default: true)
- `backup_files` (optional): Create backups of modified files (default: true)
- `interactive` (optional): Prompt for confirmation before each change (default: true)
- `path` (optional): Specific path to scan for repositories (defaults to app/Restify)

#### Migration Process

1. **Repository Discovery**: Scans for Laravel Restify repositories in standard locations
2. **Analysis Phase**: Evaluates each repository for migration requirements
3. **Backup Creation**: Creates timestamped backups of files before modification
4. **Attribute Migration**: Converts `public static $model = Model::class;` to `#[Model(Model::class)]`
5. **Field Migration**: Moves search/sort configuration to field-level methods
6. **Configuration Check**: Validates config file compatibility with v10
7. **Comprehensive Reporting**: Provides detailed migration report with next steps

#### Usage Examples

```bash
# Dry run analysis (recommended first step)
Upgrade Restify with dry_run=true

# Apply migrations with backups
Upgrade Restify with dry_run=false backup_files=true

# Migrate only attributes
Upgrade Restify with dry_run=false migrate_fields=false

# Non-interactive mode
Upgrade Restify with dry_run=false interactive=false

# Custom repository path
Upgrade Restify with path="/app/Custom/Repositories"
```

#### Before & After Examples

**Before (Laravel Restify 9.x):**
```php
class PostRepository extends Repository
{
    public static string $model = Post::class;
    
    public static array $search = ['title', 'content'];
    public static array $sort = ['created_at', 'title'];
    
    public function fields(RestifyRequest $request): array
    {
        return [
            field('title'),
            field('content'),
        ];
    }
}
```

**After (Laravel Restify 10.x):**
```php
use Binaryk\LaravelRestify\Attributes\Model;

#[Model(Post::class)]
class PostRepository extends Repository
{
    public function fields(RestifyRequest $request): array
    {
        return [
            field('title')->searchable()->sortable(),
            field('content')->searchable(),
        ];
    }
}
```

## Manually Registering the MCP Server

Sometimes you may need to manually register the Restify Boost MCP server with your editor of choice. You should register the MCP server using the following details:

<table>
<tr><td><strong>Command</strong></td><td><code>php</code></td></tr>
<tr><td><strong>Args</strong></td><td><code>./artisan restify-boost:start</code></td></tr>
</table>

JSON Example:

```json
{
    "mcpServers": {
        "restify-boost": {
            "command": "php",
            "args": ["./artisan", "restify-boost:start"]
        }
    }
}
```

## Contributing

Thank you for considering contributing to Restify Boost! Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Eduard Lupacescu](https://github.com/binarcode) - BinarCode
- [All Contributors](../../contributors)

## License

Restify Boost is open-sourced software licensed under the [MIT license](LICENSE.md).
