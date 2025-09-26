<?php

declare(strict_types=1);

namespace BinarCode\RestifyBoost\Mcp;

use DirectoryIterator;
use Laravel\Mcp\Server;

class RestifyDocs extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Laravel Restify Documentation';

    /**
     * The MCP server's version.
     */
    protected string $version = '1.0.0';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = 'Laravel Restify MCP server providing comprehensive documentation access, API references, code examples, and troubleshooting guides. Helps AI assistants understand and work with Laravel Restify framework features including repositories, fields, filters, authentication, and performance optimization.';

    /**
     * The default pagination length for resources that support pagination.
     */
    public int $defaultPaginationLength = 25;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [];

    protected function boot(): void
    {
        collect($this->discoverTools())->each(fn (string $tool): string => $this->tools[] = $tool);
        collect($this->discoverResources())->each(fn (string $resource): string => $this->resources[] = $resource);
        collect($this->discoverPrompts())->each(fn (string $prompt): string => $this->prompts[] = $prompt);
    }

    /**
     * @return array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected function discoverTools(): array
    {
        $tools = [];

        $excludedTools = config('restify-boost.mcp.tools.exclude', []);
        $toolsPath = __DIR__.DIRECTORY_SEPARATOR.'Tools';

        if (! is_dir($toolsPath)) {
            return [];
        }

        $toolDir = new DirectoryIterator($toolsPath);
        foreach ($toolDir as $toolFile) {
            if ($toolFile->isFile() && $toolFile->getExtension() === 'php') {
                $fqdn = 'BinarCode\\RestifyBoost\\Mcp\\Tools\\'.$toolFile->getBasename('.php');
                if (class_exists($fqdn) && ! in_array($fqdn, $excludedTools, true)) {
                    $tools[] = $fqdn;
                }
            }
        }

        $extraTools = config('restify-boost.mcp.tools.include', []);
        foreach ($extraTools as $toolClass) {
            if (class_exists($toolClass)) {
                $tools[] = $toolClass;
            }
        }

        return $tools;
    }

    /**
     * @return array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected function discoverResources(): array
    {
        $resources = [];

        $excludedResources = config('restify-boost.mcp.resources.exclude', []);
        $resourcesPath = __DIR__.DIRECTORY_SEPARATOR.'Resources';

        if (! is_dir($resourcesPath)) {
            return [];
        }

        $resourceDir = new DirectoryIterator($resourcesPath);
        foreach ($resourceDir as $resourceFile) {
            if ($resourceFile->isFile() && $resourceFile->getExtension() === 'php') {
                $fqdn = 'BinarCode\\RestifyBoost\\Mcp\\Resources\\'.$resourceFile->getBasename('.php');
                if (class_exists($fqdn) && ! in_array($fqdn, $excludedResources, true)) {
                    $resources[] = $fqdn;
                }
            }
        }

        $extraResources = config('restify-boost.mcp.resources.include', []);
        foreach ($extraResources as $resourceClass) {
            if (class_exists($resourceClass)) {
                $resources[] = $resourceClass;
            }
        }

        return $resources;
    }

    /**
     * @return array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected function discoverPrompts(): array
    {
        $prompts = [];

        $excludedPrompts = config('restify-boost.mcp.prompts.exclude', []);
        $promptsPath = __DIR__.DIRECTORY_SEPARATOR.'Prompts';

        if (! is_dir($promptsPath)) {
            return [];
        }

        $promptDir = new DirectoryIterator($promptsPath);
        foreach ($promptDir as $promptFile) {
            if ($promptFile->isFile() && $promptFile->getExtension() === 'php') {
                $fqdn = 'BinarCode\\RestifyBoost\\Mcp\\Prompts\\'.$promptFile->getBasename('.php');
                if (class_exists($fqdn) && ! in_array($fqdn, $excludedPrompts, true)) {
                    $prompts[] = $fqdn;
                }
            }
        }

        $extraPrompts = config('restify-boost.mcp.prompts.include', []);
        foreach ($extraPrompts as $promptClass) {
            if (class_exists($promptClass)) {
                $prompts[] = $promptClass;
            }
        }

        return $prompts;
    }
}
