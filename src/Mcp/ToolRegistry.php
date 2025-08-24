<?php

declare(strict_types=1);

namespace BinarCode\LaravelRestifyMcp\Mcp;

use Symfony\Component\Finder\Finder;

class ToolRegistry
{
    /** @var array<class-string> */
    private static array $allowedTools = [];

    /** @var array<class-string> */
    private static array $excludedTools = [];

    /**
     * Initialize the tool registry with auto-discovery.
     */
    public static function initialize(): void
    {
        self::$allowedTools = self::discoverTools();
        self::$excludedTools = config('restify-mcp.mcp.tools.exclude', []);
    }

    /**
     * Check if a tool class is allowed to be executed.
     */
    public static function isToolAllowed(string $toolClass): bool
    {
        if (empty(self::$allowedTools)) {
            self::initialize();
        }

        return in_array($toolClass, self::$allowedTools) && ! in_array($toolClass, self::$excludedTools);
    }

    /**
     * Get all allowed tool classes.
     *
     * @return array<class-string>
     */
    public static function getAllowedTools(): array
    {
        if (empty(self::$allowedTools)) {
            self::initialize();
        }

        return array_diff(self::$allowedTools, self::$excludedTools);
    }

    /**
     * Auto-discover tool classes.
     *
     * @return array<class-string>
     */
    private static function discoverTools(): array
    {
        $tools = [];
        $toolDir = implode(DIRECTORY_SEPARATOR, [__DIR__, 'Tools']);

        if (! is_dir($toolDir)) {
            return $tools;
        }

        $finder = Finder::create()
            ->in($toolDir)
            ->files()
            ->name('*.php');

        foreach ($finder as $toolFile) {
            $fullyClassifiedClassName = 'BinarCode\\LaravelRestifyMcp\\Mcp\\Tools\\'.$toolFile->getBasename('.php');
            if (class_exists($fullyClassifiedClassName)) {
                $tools[] = $fullyClassifiedClassName;
            }
        }

        // Add any additional tools from config
        $additionalTools = config('restify-mcp.mcp.tools.include', []);
        $tools = array_merge($tools, $additionalTools);

        return array_unique($tools);
    }
}
