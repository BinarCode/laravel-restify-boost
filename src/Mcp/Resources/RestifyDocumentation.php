<?php

declare(strict_types=1);

namespace BinarCode\RestifyBoost\Mcp\Resources;

use BinarCode\RestifyBoost\Services\DocParser;
use Laravel\Mcp\Server\Contracts\Resources\Content;
use Laravel\Mcp\Server\Resource;

class RestifyDocumentation extends Resource
{
    public function __construct(protected DocParser $parser) {}

    public function description(): string
    {
        return 'Complete Laravel Restify documentation including installation guides, repositories, fields, filters, authentication, actions, and performance optimization. This resource provides structured access to all documentation content for comprehensive understanding of the framework.';
    }

    public function read(): string|Content
    {
        try {
            $documentation = $this->loadDocumentation();

            $response = [
                'package' => 'Laravel Restify',
                'version' => $this->getRestifyVersion(),
                'description' => 'A Laravel package that provides a powerful way to build RESTful APIs with ease',
                'documentation' => $documentation,
                'structure' => $this->getDocumentationStructure($documentation),
                'last_updated' => now()->toIso8601String(),
            ];

            return json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return "Error loading Laravel Restify documentation: {$e->getMessage()}";
        }
    }

    protected function loadDocumentation(): array
    {
        $primaryPath = config('restify-boost.docs.paths.primary');
        $fallbackPath = config('restify-boost.docs.paths.fallback');

        $documentation = [];

        // Load primary documentation
        if (is_dir($primaryPath)) {
            $documentation['current'] = $this->loadDocumentationFromPath($primaryPath, 'v2');
        }

        // Load fallback documentation
        if (is_dir($fallbackPath)) {
            $documentation['fallback'] = $this->loadDocumentationFromPath($fallbackPath, 'v2');
        }

        return $documentation;
    }

    protected function loadDocumentationFromPath(string $basePath, string $version): array
    {
        $sections = [];
        $categories = config('restify-boost.categories', []);

        foreach ($categories as $categoryKey => $categoryConfig) {
            $categoryDocs = [];
            $paths = $categoryConfig['paths'] ?? [];

            foreach ($paths as $pathPattern) {
                $files = $this->findDocumentationFiles($basePath, $pathPattern);
                foreach ($files as $filePath) {
                    $doc = $this->parser->parse($filePath);
                    if ($doc !== null) {
                        $categoryDocs[] = [
                            'title' => $doc['title'],
                            'summary' => $doc['summary'],
                            'content' => $this->truncateContent($doc['content']),
                            'headings' => $doc['headings'],
                            'code_examples_count' => count($doc['code_examples']),
                            'estimated_tokens' => $doc['estimated_tokens'],
                            'file_path' => basename($filePath),
                        ];
                    }
                }
            }

            if (! empty($categoryDocs)) {
                $sections[$categoryKey] = [
                    'name' => $categoryConfig['name'],
                    'documents' => $categoryDocs,
                    'total_documents' => count($categoryDocs),
                ];
            }
        }

        return [
            'version' => $version,
            'sections' => $sections,
            'total_sections' => count($sections),
            'total_documents' => array_sum(array_column($sections, 'total_documents')),
        ];
    }

    protected function findDocumentationFiles(string $basePath, string $pattern): array
    {
        $files = [];

        // Handle glob patterns
        if (str_contains($pattern, '*')) {
            $globPattern = $basePath.'/'.$pattern;
            $files = glob($globPattern) ?: [];
        } else {
            // Handle specific file paths
            $filePath = $basePath.'/'.$pattern;
            if (file_exists($filePath)) {
                $files[] = $filePath;
            }
        }

        // Filter for markdown files
        return array_filter($files, fn ($file) => str_ends_with($file, '.md'));
    }

    protected function truncateContent(string $content): string
    {
        $maxLength = config('restify-boost.docs.processing.max_content_length', 10000);

        if (strlen($content) <= $maxLength) {
            return $content;
        }

        $strategy = config('restify-boost.optimization.truncate_strategy', 'smart');

        return match ($strategy) {
            'smart' => $this->smartTruncate($content, $maxLength),
            'hard' => substr($content, 0, $maxLength).'...',
            default => $content,
        };
    }

    protected function smartTruncate(string $content, int $maxLength): string
    {
        if (strlen($content) <= $maxLength) {
            return $content;
        }

        // Try to truncate at sentence boundary
        $truncated = substr($content, 0, $maxLength);
        $lastSentence = strrpos($truncated, '.');

        if ($lastSentence !== false && $lastSentence > $maxLength * 0.8) {
            return substr($truncated, 0, $lastSentence + 1);
        }

        // Try to truncate at word boundary
        $lastSpace = strrpos($truncated, ' ');
        if ($lastSpace !== false && $lastSpace > $maxLength * 0.9) {
            return substr($truncated, 0, $lastSpace).'...';
        }

        return $truncated.'...';
    }

    protected function getDocumentationStructure(array $documentation): array
    {
        $structure = [];

        foreach ($documentation as $versionKey => $versionData) {
            $structure[$versionKey] = [
                'version' => $versionData['version'],
                'sections' => array_map(function ($section) {
                    return [
                        'name' => $section['name'],
                        'document_count' => $section['total_documents'],
                        'topics' => array_column($section['documents'], 'title'),
                    ];
                }, $versionData['sections']),
            ];
        }

        return $structure;
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

        // Fallback: check composer.json
        $composerJson = base_path('composer.json');
        if (file_exists($composerJson)) {
            $composerData = json_decode(file_get_contents($composerJson), true);
            $version = $composerData['require']['binaryk/laravel-restify'] ?? null;
            if ($version) {
                return ltrim($version, '^~');
            }
        }

        return 'unknown';
    }
}
