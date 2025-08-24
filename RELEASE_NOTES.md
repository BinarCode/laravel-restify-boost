# Release Notes

## v1.0.0 - Initial Release

We're excited to announce the first stable release of **Laravel Restify MCP Server** – a comprehensive Model Context Protocol server designed to accelerate AI-assisted Laravel Restify development.

### 🚀 What's New

Laravel Restify MCP Server provides AI assistants (like Claude Code, Cursor, and other MCP-compatible tools) with direct access to complete Laravel Restify documentation and code generation capabilities.

### 🛠️ Available MCP Tools

This release includes 7 powerful MCP tools to streamline your Laravel Restify development workflow:

#### Documentation & Search Tools
- **Search Restify Docs** - Search through comprehensive Laravel Restify documentation with relevance scoring
- **Get Code Examples** - Extract specific code examples from documentation with language and category filtering  
- **Navigate Docs** - Browse documentation structure and categories with overview and detailed navigation

#### Code Generation Tools  
- **Generate Repository** - Generate Laravel Restify repository classes with proper structure and methods
- **Generate Action** - Generate Laravel Restify action classes for custom business logic
- **Generate Getter** - Generate Laravel Restify getter classes for data transformation
- **Generate Match Filter** - Generate Laravel Restify match filter classes for advanced filtering

### 📚 Documentation Coverage

Complete Laravel Restify documentation access including:
- API methods and endpoints
- Field types and configurations
- Authentication and authorization patterns
- Performance optimization guides
- Best practices and troubleshooting

### 🔧 Installation

Install via Composer:

```bash
composer require binarcode/laravel-restify-mcp --dev
```

Install the MCP server:

```bash
php artisan restify-mcp:install
```

### ⚡ Requirements

- PHP 8.1+
- Laravel 10.0+ | 11.0+ | 12.0+
- Laravel MCP package (^0.1.1)

### 🎯 Manual Registration

Register the MCP server manually with your AI assistant:

**Command**: `php`  
**Args**: `./artisan restify-mcp:start`

### 🙏 Credits

- [Eduard Lupacescu](https://github.com/binarcode) - BinarCode
- [All Contributors](../../contributors)

---

**Full Changelog**: https://github.com/binarcode/laravel-restify-mcp/commits/v1.0.0