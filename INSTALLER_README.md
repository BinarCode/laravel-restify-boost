# Laravel Restify Installer

An automated installation and setup tool for [Laravel Restify](https://restify.binarcode.com) - a Laravel package for building JSON:API compliant REST APIs.

## Quick Start

```bash
# Make the script executable (if not already)
chmod +x install-restify.sh

# Run the installer
./install-restify.sh
```

## What This Tool Does

The installer automates the complete Laravel Restify setup process:

### ✅ Requirements Validation
- Checks PHP version (>= 8.0)
- Validates Laravel project structure
- Verifies Laravel framework version compatibility

### 📦 Package Installation
- Installs `binaryk/laravel-restify` via Composer
- Handles minimum stability requirements automatically

### 🔧 Setup & Configuration
- Runs `php artisan restify:setup` command
- Publishes configuration files
- Creates RestifyServiceProvider
- Sets up the `app/Restify` directory structure
- Generates base Repository class and UserRepository

### 🗃️ Database Setup
- Prompts to run migrations
- Optionally installs `doctrine/dbal` for mock data generation
- Can generate sample user data with `restify:stub`

### ⚙️ Optional Configurations
- **Sanctum Authentication**: Enable `auth:sanctum` middleware
- **API Prefix**: Customize API endpoint prefix (default: `/api/restify`)
- **Repository Generation**: Auto-generate repositories for existing models

## Features

### Interactive Setup
The installer provides interactive prompts for optional features:
- Enable/disable authentication middleware
- Customize API prefix
- Generate mock data
- Auto-generate repositories for existing models

### Error Handling
- Validates all requirements before installation
- Provides clear error messages
- Exits gracefully on failures

### Colored Output
- Green ✅ for success messages
- Red ❌ for errors
- Yellow ⚠️ for warnings  
- Blue ℹ️ for information

## Requirements

- PHP >= 8.0
- Laravel Framework >= 8.0
- Composer installed
- `bc` command (for version comparison)

## What Gets Created

After successful installation:

```
config/
├── restify.php                           # Main configuration

app/
├── Providers/
│   └── RestifyServiceProvider.php        # Auto-registered service provider
└── Restify/
    ├── Repository.php                    # Base repository class
    └── UserRepository.php                # Example user repository

database/migrations/
└── xxxx_xx_xx_create_action_logs_table.php  # Action logs migration
```

## Usage Examples

### Basic Installation
```bash
./install-restify.sh
```

### What You Get After Installation

1. **API Endpoints**: Immediate access to RESTful endpoints
   ```
   GET    /api/restify/users          # List users
   POST   /api/restify/users          # Create user  
   GET    /api/restify/users/{id}     # Show user
   PATCH  /api/restify/users/{id}     # Update user
   DELETE /api/restify/users/{id}     # Delete user
   ```

2. **JSON:API Compliance**: All responses follow JSON:API specification
3. **Repository Pattern**: Clean separation of API logic from models
4. **Built-in Features**: Filtering, sorting, pagination, field selection

### Post-Installation Commands

Generate new repositories:
```bash
php artisan restify:repository PostRepository
php artisan restify:repository PostRepository --all  # With model, policy, migration
```

Generate repositories for all models:
```bash
php artisan restify:generate:repositories
```

Generate policies:
```bash
php artisan restify:policy PostPolicy
```

Create mock data:
```bash
php artisan restify:stub users --count=50
```

## Configuration

### API Prefix
Default: `/api/restify`
Configure in `config/restify.php`:
```php
'base' => '/api/restify',
```

### Middleware
Default middleware stack in `config/restify.php`:
```php
'middleware' => [
    'api',
    'auth:sanctum',  // Optional - enabled via installer
    Binaryk\LaravelRestify\Http\Middleware\DispatchRestifyStartingEvent::class,
    Binaryk\LaravelRestify\Http\Middleware\AuthorizeRestify::class,
]
```

### Authentication
For Sanctum authentication, ensure you have:
1. Laravel Sanctum installed and configured
2. `auth:sanctum` middleware enabled (installer option)
3. API tokens configured for your users

## Troubleshooting

### Minimum Stability Issues
If you encounter minimum stability errors, the installer will suggest setting:
```json
{
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

### Permission Issues
Make sure the script is executable:
```bash
chmod +x install-restify.sh
```

### Laravel Version Compatibility
- Laravel 8.x → Restify <= 6.x
- Laravel 9.x → Restify ^7.x  
- Laravel 10.x → Restify ^8.x

## Links

- [Laravel Restify Documentation](https://restify.binarcode.com)
- [GitHub Repository](https://github.com/binaryk/laravel-restify)
- [JSON:API Specification](https://jsonapi.org/)

## License

This installer tool is open-sourced software licensed under the MIT license.