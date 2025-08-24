# Laravel Restify MCP Server

[![Latest Version on Packagist](https://img.shields.io/packagist/v/binarcode/laravel-restify-mcp.svg?style=flat-square)](https://packagist.org/packages/binarcode/laravel-restify-mcp)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/binarcode/laravel-restify-mcp/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/binarcode/laravel-restify-mcp/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/binarcode/laravel-restify-mcp/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/binarcode/laravel-restify-mcp/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/binarcode/laravel-restify-mcp.svg?style=flat-square)](https://packagist.org/packages/binarcode/laravel-restify-mcp)

A Laravel package that exposes Laravel Restify documentation through the Model Context Protocol (MCP), enabling AI assistants to access comprehensive framework documentation, API references, code examples, and troubleshooting guides.

This package provides intelligent documentation search, structured how-to guides, and real-time troubleshooting assistance for Laravel Restify development through AI-powered tools.

## Features

- **🔍 Intelligent Documentation Search**: Search through comprehensive Laravel Restify documentation with relevance scoring
- **📚 Structured API Reference**: Access organized API methods, field types, and implementation patterns  
- **💡 Interactive How-To Guides**: Get step-by-step guidance for common development tasks
- **🔧 Smart Troubleshooting**: AI-powered diagnosis and solutions for common issues
- **⚡ Performance Optimized**: Built-in caching and token optimization for fast responses
- **🎯 Category-Based Navigation**: Browse documentation by topic (repositories, fields, auth, etc.)
- **📖 Code Examples**: Curated, contextual code snippets for quick implementation

## Requirements

- PHP 8.1 or higher
- Laravel 10.0 or higher
- Laravel Restify package installed in your project

**Optional (for AI assistant integration):**
- Laravel MCP package: `laravel/mcp` (recommended)
- Alternative: `php-mcp/laravel` or `opgginc/laravel-mcp-server`

## Installation

### Step 1: Install the Package

Install the package via Composer:

```bash
composer require binarcode/laravel-restify-mcp
```

### Step 2: Install and Configure

Run the interactive installation command:

```bash
php artisan restify-mcp:install
```

This setup wizard will:
- 📋 Publish the configuration file to `config/restify-mcp.php`
- ✅ Verify Laravel Restify installation and documentation
- 🔌 Check for MCP packages and provide recommendations
- 🤖 Guide you through AI assistant setup (Claude Code, Cursor, etc.)
- 🚀 Provide next steps and testing instructions

The installation is **interactive** and will ask about your setup preferences, just like Laravel Boost does!

### Step 2.5: Install MCP Package (Optional)

For AI assistant integration, install one of these MCP packages:

**Recommended (Official Laravel):**
```bash
composer require laravel/mcp
```

**Alternative Options:**
```bash
# Community Laravel MCP SDK
composer require php-mcp/laravel

# Enterprise HTTP-based MCP server
composer require opgginc/laravel-mcp-server
```

> 💡 **Tip**: Run `php artisan restify-mcp:install` again after installing an MCP package to update your configuration.

### Step 3: Verify Installation

**Important**: The following commands must be run from within a Laravel application that has both Laravel Restify and this MCP package installed. They will not work in the package development directory.

To verify that everything is working correctly in your Laravel app:

1. **Check if the package commands are available:**
```bash
php artisan list | grep restify-mcp
```
You should see three commands: `restify-mcp:install`, `restify-mcp:start`, and `restify-mcp:execute`

2. **Test the documentation search:**
```bash
php artisan restify-mcp:execute search-restify-docs --queries="repository"
```

3. **Browse available documentation:**
```bash
php artisan restify-mcp:execute navigate-docs --action="overview"
```

If you see documentation results, your installation is working correctly!

> **⚠️ Important**: These commands only work within a Laravel application, not in this package's development directory. Make sure you have:
> - A Laravel application (10.0+)
> - Laravel Restify installed (`composer require binaryk/laravel-restify`)
> - This MCP package installed (`composer require binarcode/laravel-restify-mcp`)

### Optional: Start MCP Server for Testing

You can start the MCP server manually for testing:

```bash
php artisan restify-mcp:start
```

**Note**: In production, MCP servers are typically started automatically by AI assistants that connect to them.

## Available Artisan Commands

The package provides several Artisan commands for managing the MCP server:

### Installation Command
```bash
php artisan restify-mcp:install
```
Sets up the package configuration and verifies Laravel Restify documentation availability.

### Start MCP Server
```bash
php artisan restify-mcp:start [--port=8080] [--host=localhost]
```
Starts the MCP server manually for testing and development. Optional parameters:
- `--port`: Server port (default: 8080)
- `--host`: Server host (default: localhost)

### Execute MCP Tools
```bash
php artisan restify-mcp:execute <tool-name> [options]
```
Execute specific MCP tools directly from the command line. Available tools:
- `search-restify-docs`
- `get-code-examples`  
- `navigate-docs`

Examples:
```bash
# Search documentation
php artisan restify-mcp:execute search-restify-docs --queries="repository,validation" --category="repositories"

# Get code examples
php artisan restify-mcp:execute get-code-examples --topic="custom field" --language="php"

# Navigate documentation
php artisan restify-mcp:execute navigate-docs --action="overview"
```

## Usage

Once installed, the MCP server will be automatically registered and available to AI assistants that support the Model Context Protocol. The server provides several tools and resources:

### Available MCP Tools

#### 1. Search Restify Documentation
```
Tool: search-restify-docs
Purpose: Search through Laravel Restify documentation
Parameters:
  - queries: Array of search terms
  - category: Optional category filter  
  - limit: Max results (default: 10)
  - token_limit: Max response tokens (default: 10,000)
```

#### 2. Get Code Examples
```
Tool: get-code-examples
Purpose: Extract specific code examples from documentation
Parameters:
  - topic: Feature or concept to find examples for
  - language: Optional language filter (php, js, etc.)
  - category: Optional category filter
  - limit: Max examples (default: 10)
```

#### 3. Navigate Documentation
```
Tool: navigate-docs
Purpose: Browse documentation structure and categories
Parameters:
  - action: "overview", "list-categories", or "category"
  - category: Specific category to browse
  - include_content: Include document summaries (default: true)
```

### Available MCP Resources

- **restify-documentation**: Complete Laravel Restify documentation content
- **restify-api-reference**: Structured API reference with method signatures

### Available MCP Prompts

- **restify-how-to**: Step-by-step guidance for accomplishing tasks
- **restify-troubleshooting**: Diagnostic help for common issues

### Example AI Assistant Interactions

**Searching for documentation:**
```
Human: How do I create a custom field in Laravel Restify?
AI: I'll search the Laravel Restify documentation for information about custom fields.
[Uses search-restify-docs tool with query "custom field"]
```

**Getting code examples:**
```
Human: Show me code examples for repository validation
AI: Let me get some validation examples from the Restify documentation.
[Uses get-code-examples tool with topic "repository validation"]
```

**Troubleshooting:**
```
Human: I'm getting a 422 validation error in my Restify API
AI: I can help you troubleshoot that validation error.
[Uses restify-troubleshooting prompt with the error details]
```

## Configuration

The package provides extensive configuration options in `config/restify-mcp.php`:

### Documentation Paths
Configure where the package looks for Laravel Restify documentation:
```php
'docs' => [
    'paths' => [
        'primary' => base_path('vendor/binaryk/laravel-restify/docs-v2/content/en'),
        'legacy' => base_path('vendor/binaryk/laravel-restify/docs/content/en'),
    ],
],
```

### Cache Configuration
Optimize performance with intelligent caching:
```php
'cache' => [
    'enabled' => true,
    'store' => 'file',
    'ttl' => 3600, // 1 hour
],
```

### Search & Response Optimization  
Fine-tune search behavior and token limits:
```php
'search' => [
    'default_limit' => 10,
    'max_limit' => 50,
],
'optimization' => [
    'default_token_limit' => 10000,
    'max_token_limit' => 100000,
],
```

### MCP Component Control
Enable/disable specific tools, resources, or prompts:
```php
'mcp' => [
    'tools' => [
        'exclude' => [
            // 'BinarCode\LaravelRestifyMcp\Mcp\Tools\SearchRestifyDocs',
        ],
    ],
],
```

## Development

### Package Development

This package is designed to be used within Laravel applications. For development and testing:

1. **Clone the repository:**
```bash
git clone https://github.com/binarcode/laravel-restify-mcp.git
cd laravel-restify-mcp
```

2. **Install dependencies:**
```bash
composer install
```

3. **Test the package:**
   - Create a fresh Laravel application
   - Install Laravel Restify: `composer require binaryk/laravel-restify`
   - Install this package locally using Composer path repository or by publishing to Packagist

### Testing

Run the test suite:
```bash
composer test
```

Run code style fixes:
```bash
composer format
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## How It Works

This package integrates with Laravel applications to provide seamless documentation access through the Model Context Protocol:

1. **Documentation Parsing**: Automatically discovers and parses Laravel Restify documentation from vendor directories
2. **Intelligent Indexing**: Builds searchable indexes with relevance scoring and categorization
3. **MCP Server Registration**: Registers as an MCP server that AI assistants can connect to
4. **Real-time Processing**: Processes queries and returns optimized responses with code examples and explanations

The package follows the Laravel Boost pattern for MCP server architecture, ensuring consistency and maintainability.

## AI Assistant Integration

This package is designed to work with AI assistants that support the Model Context Protocol, such as:

- Claude Code
- Cursor AI
- Codeium
- Other MCP-compatible AI tools

Once installed, AI assistants can automatically discover and use the Laravel Restify documentation to provide more accurate and contextual assistance with your Laravel Restify development.

## Credits

- [Eduard Lupacescu](https://github.com/binarcode) - BinarCode
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
