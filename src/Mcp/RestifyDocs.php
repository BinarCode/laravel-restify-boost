<?php

declare(strict_types=1);

namespace BinarCode\RestifyBoost\Mcp;

use Laravel\Mcp\Server;

class RestifyDocs extends Server
{
    public string $serverName = 'Laravel Restify Documentation';

    public string $serverVersion = '1.0.0';

    public string $instructions = 'Laravel Restify MCP server providing comprehensive documentation access, API references, code examples, and troubleshooting guides. Helps AI assistants understand and work with Laravel Restify framework features including repositories, fields, filters, authentication, and performance optimization.';

    public int $defaultPaginationLength = 25;

    public function boot(): void
    {
        $this->discoverTools();
        $this->discoverResources();
        $this->discoverPrompts();
    }

    /**
     * @return array<string>
     */
    protected function discoverTools(): array
    {
        $excludedTools = config('restify-mcp.mcp.tools.exclude', []);
        $toolsPath = __DIR__.DIRECTORY_SEPARATOR.'Tools';

        if (! is_dir($toolsPath)) {
            return [];
        }

        $toolDir = new \DirectoryIterator($toolsPath);
        foreach ($toolDir as $toolFile) {
            if ($toolFile->isFile() && $toolFile->getExtension() === 'php') {
                $fqdn = 'BinarCode\\RestifyBoost\\Mcp\\Tools\\'.$toolFile->getBasename('.php');
                if (class_exists($fqdn) && ! in_array($fqdn, $excludedTools, true)) {
                    $this->addTool($fqdn);
                }
            }
        }

        $extraTools = config('restify-mcp.mcp.tools.include', []);
        foreach ($extraTools as $toolClass) {
            if (class_exists($toolClass)) {
                $this->addTool($toolClass);
            }
        }

        return $this->registeredTools;
    }

    /**
     * @return array<string>
     */
    protected function discoverResources(): array
    {
        $excludedResources = config('restify-mcp.mcp.resources.exclude', []);
        $resourcesPath = __DIR__.DIRECTORY_SEPARATOR.'Resources';

        if (! is_dir($resourcesPath)) {
            return [];
        }

        $resourceDir = new \DirectoryIterator($resourcesPath);
        foreach ($resourceDir as $resourceFile) {
            if ($resourceFile->isFile() && $resourceFile->getExtension() === 'php') {
                $fqdn = 'BinarCode\\RestifyBoost\\Mcp\\Resources\\'.$resourceFile->getBasename('.php');
                if (class_exists($fqdn) && ! in_array($fqdn, $excludedResources, true)) {
                    $this->addResource($fqdn);
                }
            }
        }

        $extraResources = config('restify-mcp.mcp.resources.include', []);
        foreach ($extraResources as $resourceClass) {
            if (class_exists($resourceClass)) {
                $this->addResource($resourceClass);
            }
        }

        return $this->registeredResources;
    }

    /**
     * @return array<string>
     */
    protected function discoverPrompts(): array
    {
        $excludedPrompts = config('restify-mcp.mcp.prompts.exclude', []);
        $promptsPath = __DIR__.DIRECTORY_SEPARATOR.'Prompts';

        if (! is_dir($promptsPath)) {
            return [];
        }

        $promptDir = new \DirectoryIterator($promptsPath);
        foreach ($promptDir as $promptFile) {
            if ($promptFile->isFile() && $promptFile->getExtension() === 'php') {
                $fqdn = 'BinarCode\\RestifyBoost\\Mcp\\Prompts\\'.$promptFile->getBasename('.php');
                if (class_exists($fqdn) && ! in_array($fqdn, $excludedPrompts, true)) {
                    $this->addPrompt($fqdn);
                }
            }
        }

        $extraPrompts = config('restify-mcp.mcp.prompts.include', []);
        foreach ($extraPrompts as $promptClass) {
            if (class_exists($promptClass)) {
                $this->addPrompt($promptClass);
            }
        }

        return $this->registeredPrompts;
    }
}
