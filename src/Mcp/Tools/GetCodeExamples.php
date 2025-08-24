<?php

declare(strict_types=1);

namespace BinarCode\LaravelRestifyMcp\Mcp\Tools;

use BinarCode\LaravelRestifyMcp\Services\DocIndexer;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

class GetCodeExamples extends Tool
{
    public function __construct(protected DocIndexer $indexer) {}

    public function description(): string
    {
        return 'Get specific code examples from Laravel Restify documentation. This tool extracts and formats code examples for specific features like repositories, fields, filters, actions, and authentication. Perfect for understanding implementation patterns and getting copy-paste ready code snippets.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema
            ->string('topic')
            ->description('The topic or feature you need code examples for (e.g., "repository", "field validation", "custom filter", "authentication")')
            ->required()
            ->string('language')
            ->description('Filter by programming language (php, javascript, json, yaml, etc.)')
            ->optional()
            ->string('category')
            ->description('Limit to specific documentation category: installation, repositories, fields, filters, auth, actions, performance, testing')
            ->optional()
            ->integer('limit')
            ->description('Maximum number of examples to return (default: 10, max: 25)')
            ->optional()
            ->boolean('include_context')
            ->description('Include surrounding documentation context for each example (default: true)')
            ->optional();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function handle(array $arguments): ToolResult|Generator
    {
        try {
            $topic = trim($arguments['topic']);
            if (empty($topic)) {
                return ToolResult::error('Topic is required');
            }

            $language = $arguments['language'] ?? null;
            $category = $arguments['category'] ?? null;
            $limit = min($arguments['limit'] ?? 10, 25);
            $includeContext = $arguments['include_context'] ?? true;

            // Initialize indexer
            $this->initializeIndexer();

            // Search for documents containing the topic
            $searchResults = $this->indexer->search($topic, $category, 50);

            if (empty($searchResults)) {
                return $this->handleNoExamples($topic, $category);
            }

            // Extract code examples from search results
            $codeExamples = $this->extractCodeExamples($searchResults, $language, $topic);

            if (empty($codeExamples)) {
                return $this->handleNoCodeExamples($topic, $language);
            }

            // Limit and format results
            $limitedExamples = array_slice($codeExamples, 0, $limit);
            $response = $this->formatCodeExamples($limitedExamples, $topic, $includeContext);

            return ToolResult::text($response);
        } catch (\Throwable $e) {
            return ToolResult::error('Failed to get code examples: '.$e->getMessage());
        }
    }

    protected function initializeIndexer(): void
    {
        $paths = $this->getDocumentationPaths();
        $this->indexer->indexDocuments($paths);
    }

    protected function getDocumentationPaths(): array
    {
        $paths = [];
        $docsPath = config('restify-mcp.docs.paths.primary');

        foreach ([$docsPath] as $basePath) {
            if (is_dir($basePath)) {
                $paths = array_merge($paths, $this->scanDirectoryForMarkdown($basePath));
            }
        }

        return $paths;
    }

    protected function scanDirectoryForMarkdown(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    protected function extractCodeExamples(array $searchResults, ?string $language, string $topic): array
    {
        $allExamples = [];

        foreach ($searchResults as $result) {
            $doc = $result['document'];
            $relevanceScore = $result['relevance_score'];

            foreach ($doc['code_examples'] as $example) {
                // Filter by language if specified
                if ($language !== null && strtolower($example['language']) !== strtolower($language)) {
                    continue;
                }

                // Calculate relevance based on code content and context
                $codeRelevance = $this->calculateCodeRelevance($example, $topic);
                $totalRelevance = ($relevanceScore * 0.7) + ($codeRelevance * 0.3);

                $allExamples[] = [
                    'code' => $example['code'],
                    'language' => $example['language'],
                    'lines' => $example['lines'],
                    'document' => [
                        'title' => $doc['title'],
                        'category' => $doc['category'],
                        'file_path' => $doc['file_path'],
                    ],
                    'relevance_score' => round($totalRelevance, 2),
                    'context' => $this->extractCodeContext($doc, $example['code']),
                ];
            }
        }

        // Sort by relevance
        usort($allExamples, fn ($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        return $allExamples;
    }

    protected function calculateCodeRelevance(array $example, string $topic): float
    {
        $code = strtolower($example['code']);
        $topicTerms = explode(' ', strtolower($topic));

        $relevance = 0;
        foreach ($topicTerms as $term) {
            if (stripos($code, $term) !== false) {
                $relevance += 1;
                // Bonus for exact matches
                if (preg_match('/\b'.preg_quote($term, '/').'\b/', $code)) {
                    $relevance += 0.5;
                }
            }
        }

        // Normalize by code length
        return $relevance / (strlen($code) / 100 + 1);
    }

    protected function extractCodeContext(array $doc, string $code): string
    {
        $rawContent = $doc['raw_content'];
        $codePos = strpos($rawContent, $code);

        if ($codePos === false) {
            return '';
        }

        // Find the paragraph or section before the code block
        $beforeCode = substr($rawContent, 0, $codePos);
        $lines = explode("\n", $beforeCode);

        // Look for the last non-empty line that looks like context
        $contextLines = [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim($lines[$i]);

            if (empty($line) || str_starts_with($line, '```')) {
                continue;
            }

            $contextLines[] = $line;

            // Stop if we have enough context or hit a heading
            if (count($contextLines) >= 3 || str_starts_with($line, '#')) {
                break;
            }
        }

        return implode(' ', array_reverse($contextLines));
    }

    protected function formatCodeExamples(array $examples, string $topic, bool $includeContext): string
    {
        $output = "# Laravel Restify Code Examples: {$topic}\n\n";
        $output .= 'Found '.count($examples)." relevant code example(s)\n\n";

        foreach ($examples as $index => $example) {
            $output .= '## '.($index + 1).". {$example['document']['title']}\n";
            $output .= "**Category:** {$example['document']['category']} | **Relevance:** {$example['relevance_score']}\n\n";

            if ($includeContext && ! empty($example['context'])) {
                $output .= "**Context:** {$example['context']}\n\n";
            }

            $output .= "```{$example['language']}\n";
            $output .= $example['code']."\n";
            $output .= "```\n\n";

            if ($example['lines'] > 20) {
                $output .= "*This is a {$example['lines']}-line example*\n\n";
            }

            $output .= "---\n\n";
        }

        // Add usage tips
        $output .= "**Tips:**\n";
        $output .= "- Adapt these examples to your specific use case\n";
        $output .= "- Check the full documentation for additional context\n";
        $output .= "- Use `search-restify-docs` for more detailed explanations\n";

        return $output;
    }

    protected function handleNoExamples(string $topic, ?string $category): ToolResult
    {
        $message = "No documentation found for topic: **{$topic}**";
        if ($category) {
            $message .= " in category: **{$category}**";
        }

        $message .= "\n\n**Suggestions:**\n";
        $message .= "- Try broader search terms (e.g., 'repository' instead of 'custom repository pattern')\n";
        $message .= "- Check available categories with the search tool\n";
        $message .= "- Use `search-restify-docs` to explore available documentation\n";

        return ToolResult::text($message);
    }

    protected function handleNoCodeExamples(string $topic, ?string $language): ToolResult
    {
        $message = "No code examples found for topic: **{$topic}**";
        if ($language) {
            $message .= " in language: **{$language}**";
        }

        $message .= "\n\n**Suggestions:**\n";
        $message .= "- Try without language filter to see all available examples\n";
        $message .= "- Check if documentation uses different terminology\n";
        $message .= "- Use `search-restify-docs` to find conceptual information\n";

        return ToolResult::text($message);
    }
}
