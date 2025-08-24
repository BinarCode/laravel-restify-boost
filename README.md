# Restify Boost

[![Latest Version on Packagist](https://img.shields.io/packagist/v/binarcode/restify-boost.svg?style=flat-square)](https://packagist.org/packages/binarcode/restify-boost)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/binarcode/restify-boost/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/binarcode/restify-boost/actions?query=workflow%3Arun-tests+branch%3Amain)

The first fully customizable Laravel [JSON:API](https://jsonapi.org) builder with MCP and GraphQL support. "CRUD" and protect your resources with 0 (zero) extra line of code.

<div>
<a href="https://restifytemplates.com">
<img alt="Save weeks of API development" src="/docs/starter-kit.png">
</a>
</div>

## Introduction

Restify Boost accelerates AI-assisted [Laravel Restify](https://github.com/binarcode/laravel-restify) development by providing comprehensive documentation access and code generation tools through the Model Context Protocol.

At its foundation, this package is an MCP server equipped with specialized tools designed to streamline Laravel Restify development workflows. The server provides access to complete documentation, API references, code examples, and troubleshooting guides.

## Resources

- **[Laravel Restify](https://github.com/binarcode/laravel-restify)** - The main Laravel Restify package
- **[Official Documentation](https://restify.binarcode.com)** - Complete Laravel Restify documentation
- **[Free Templates](https://restifytemplates.com)** - Ready-to-use Laravel Restify templates

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

## Available Documentation

| Package | Coverage |
|---------|----------|
| Laravel Restify | Complete documentation including API methods, field types, authentication, authorization, and performance guides |

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
