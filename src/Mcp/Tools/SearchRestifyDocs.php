<?php

declare(strict_types=1);

namespace BinarCode\LaravelRestifyMcp\Mcp\Tools;

use BinarCode\LaravelRestifyMcp\Services\DocIndexer;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

class SearchRestifyDocs extends Tool
{
    public function __construct(protected DocIndexer $indexer)
    {
    }

    public function description(): string
    {
        return 'Search Laravel Restify documentation for specific topics, methods, concepts, or questions. This tool automatically handles questions like "how many types of filters", "what are the available field types", "how to create repositories", etc. It searches through comprehensive documentation including installation, repositories, fields, filters, authentication, actions, and performance guides.'.PHP_EOL.
               'IMPORTANT: Always use this tool first when users ask any questions about Laravel Restify concepts, features, usage, or implementation. This includes questions about:'.PHP_EOL.
               '- Types of filters, fields, actions, or other components ("how many types of X")'.PHP_EOL.
               '- Available options or methods ("what are the available Y")'.PHP_EOL.
               '- How to implement features ("how to create Z")'.PHP_EOL.
               '- Best practices and examples'.PHP_EOL.
               'Use multiple queries if unsure about terminology (e.g., ["validation", "validate"], ["filter types", "filtering options"]).';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema
            ->raw('queries', [
                'description' => 'List of search queries to perform. For questions like "how many types of filters", use ["filter types", "filtering", "match filters"]. For "available field types", use ["field types", "fields", "field methods"]. Pass multiple queries if you aren\'t sure about exact terminology.',
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'description' => 'Search query string - extract key terms from user questions',
                ],
                'minItems' => 1,
            ])->required()
            ->string('question_type')
            ->description('Type of question being asked: "count" (how many types), "list" (what are available), "howto" (how to do something), "concept" (explain concept), "example" (show examples)')
            ->optional()
            ->string('category')
            ->description('Limit search to specific category: installation, repositories, fields, filters, auth, actions, performance, testing')
            ->optional()
            ->integer('limit')
            ->description('Maximum number of results to return per query (default: 10, max: 50)')
            ->optional()
            ->integer('token_limit')
            ->description('Maximum number of tokens to return in the response. Defaults to 10,000 tokens, maximum 100,000 tokens.')
            ->optional();
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function handle(array $arguments): ToolResult|Generator
    {
        try {
            $queries = array_filter(
                array_map('trim', $arguments['queries']),
                fn ($query) => $query !== '' && strlen($query) >= config('restify-mcp.search.min_query_length', 2)
            );

            if (empty($queries)) {
                return ToolResult::error('At least one valid query is required (minimum 2 characters)');
            }

            $questionType = $arguments['question_type'] ?? null;
            $category = $arguments['category'] ?? null;
            $limit = min(
                $arguments['limit'] ?? config('restify-mcp.search.default_limit', 10),
                config('restify-mcp.search.max_limit', 50)
            );
            $tokenLimit = min(
                $arguments['token_limit'] ?? config('restify-mcp.optimization.default_token_limit', 10000),
                config('restify-mcp.optimization.max_token_limit', 100000)
            );

            // Initialize indexer with documentation
            $this->initializeIndexer();

            $allResults = [];
            foreach ($queries as $query) {
                $results = $this->indexer->search($query, $category, $limit);
                $allResults[$query] = $results;
            }

            // If no results found, provide helpful suggestions
            if (empty(array_filter($allResults))) {
                return $this->handleNoResults($queries, $category);
            }

            // Format and optimize results
            $response = $this->formatResults($allResults, $tokenLimit, $questionType, $queries);

            return ToolResult::text($response);
        } catch (\Throwable $e) {
            return ToolResult::error('Search failed: '.$e->getMessage());
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
        $legacyPath = config('restify-mcp.docs.paths.legacy');

        // Scan for markdown files in documentation directories
        foreach ([$docsPath, $legacyPath] as $basePath) {
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

    protected function handleNoResults(array $queries, ?string $category): ToolResult
    {
        $suggestions = [];

        // Get available categories
        $categories = $this->indexer->getCategories();
        if (! empty($categories)) {
            $suggestions[] = "**Available categories:** ".implode(', ', array_keys($categories));
        }

        // Suggest common search terms based on typical questions
        $commonTerms = [
            'Filter types' => ['filter', 'match', 'search', 'sorting'],
            'Field types' => ['field', 'validation', 'input', 'form'],
            'Repository methods' => ['repository', 'crud', 'store', 'update'],
            'Relationships' => ['relationship', 'belongs', 'has', 'morph'],
            'Authentication' => ['auth', 'policy', 'authorization', 'permission'],
            'Actions' => ['action', 'custom', 'bulk', 'operation'],
        ];
        
        $suggestions[] = "**Try these topic-based searches:**";
        foreach ($commonTerms as $topic => $terms) {
            $suggestions[] = "- {$topic}: " . implode(', ', $terms);
        }

        $message = "No results found for queries: **".implode('**, **', $queries)."**";
        if ($category) {
            $message .= " in category: **{$category}**";
        }

        $message .= "\n\n".implode("\n", $suggestions);

        return ToolResult::text($message);
    }

    protected function formatResults(array $allResults, int $tokenLimit, ?string $questionType = null, array $originalQueries = []): string
    {
        $output = "# Laravel Restify Documentation Search Results\n\n";
        
        // Add question-specific introduction
        $output .= $this->generateQuestionSpecificIntro($questionType, $originalQueries);
        
        $currentTokens = 0;

        foreach ($allResults as $query => $results) {
            if (empty($results)) {
                continue;
            }

            $querySection = "## Query: \"{$query}\"\n\n";
            $resultCount = count($results);
            $querySection .= "Found {$resultCount} result(s)\n\n";
            
            // Add summary for count/list questions
            if ($questionType === 'count' || $questionType === 'list') {
                $summary = $this->generateSummaryForCountQuestion($results);
                if ($summary) {
                    $querySection .= $summary . "\n\n";
                }
            }

            foreach ($results as $index => $result) {
                $doc = $result['document'];
                $score = $result['relevance_score'];

                $resultSection = "### ".($index + 1).". {$doc['title']}\n";
                $resultSection .= "**Category:** {$doc['category']} | **Relevance:** {$score}\n\n";

                // Add snippet
                if (! empty($result['snippet'])) {
                    $resultSection .= "**Summary:** {$result['snippet']}\n\n";
                }

                // Add matching headings
                if (! empty($result['matched_headings'])) {
                    $resultSection .= "**Relevant sections:**\n";
                    foreach (array_slice($result['matched_headings'], 0, 3) as $heading) {
                        $resultSection .= "- {$heading['text']}\n";
                    }
                    $resultSection .= "\n";
                }

                // Add code examples if prioritized and available
                if (config('restify-mcp.optimization.prioritize_code_examples') && ! empty($result['matched_code_examples'])) {
                    $resultSection .= "**Code examples:**\n";
                    foreach (array_slice($result['matched_code_examples'], 0, 2) as $example) {
                        $resultSection .= "```{$example['language']}\n{$example['code']}\n```\n\n";
                    }
                }

                $resultSection .= "---\n\n";

                // Check token limit
                $estimatedTokens = strlen($resultSection) / 4; // Rough estimation
                if ($currentTokens + $estimatedTokens > $tokenLimit) {
                    $output .= "*Results truncated due to token limit ({$tokenLimit} tokens)*\n";
                    break 2;
                }

                $querySection .= $resultSection;
                $currentTokens += $estimatedTokens;
            }

            $output .= $querySection;
        }

        // Add footer with usage tips
        $output .= "\n---\n\n";
        $output .= "**Tips for better results:**\n";
        $output .= "- Use specific terms related to your Laravel Restify need\n";
        $output .= "- Combine multiple queries for broader coverage\n";
        $output .= "- Specify a category to narrow results\n";
        $output .= "- Use tools like `get-code-examples` for more detailed code samples\n";

        return $output;
    }

    protected function generateQuestionSpecificIntro(?string $questionType, array $queries): string
    {
        if (!$questionType) {
            return "";
        }

        $intro = "";
        $queryText = implode(', ', $queries);

        switch ($questionType) {
            case 'count':
                $intro = "## Answering: Types and Counts\n";
                $intro .= "Based on your question about **how many types** or **what types are available**, here's what I found:\n\n";
                break;
            
            case 'list':
                $intro = "## Available Options\n";
                $intro .= "Here are the **available options** and **types** found in Laravel Restify:\n\n";
                break;
            
            case 'howto':
                $intro = "## Implementation Guide\n";
                $intro .= "Here's **how to implement** what you're looking for:\n\n";
                break;
            
            case 'concept':
                $intro = "## Concept Explanation\n";
                $intro .= "Here's an **explanation of the concept** you asked about:\n\n";
                break;
            
            case 'example':
                $intro = "## Code Examples\n";
                $intro .= "Here are **practical examples** for your use case:\n\n";
                break;
        }

        return $intro;
    }

    protected function extractTypesAndCounts(array $allResults): array
    {
        $types = [];
        $patterns = [
            // Look for enumerated types
            '/(\d+)\.\s*([A-Za-z][A-Za-z0-9_]*)/m',
            // Look for bullet points with types  
            '/[-*]\s*([A-Za-z][A-Za-z0-9_]*)/m',
            // Look for type definitions
            '/([A-Za-z][A-Za-z0-9_]*)\s*:\s*([^.\n]+)/m',
        ];

        foreach ($allResults as $query => $results) {
            foreach ($results as $result) {
                $content = $result['document']['content'] ?? '';
                foreach ($patterns as $pattern) {
                    if (preg_match_all($pattern, $content, $matches)) {
                        for ($i = 0; $i < count($matches[1]); $i++) {
                            $type = $matches[1][$i];
                            $description = $matches[2][$i] ?? '';
                            $types[$type] = trim($description);
                        }
                    }
                }
            }
        }

        return $types;
    }

    protected function generateSummaryForCountQuestion(array $results): string
    {
        if (empty($results)) {
            return '';
        }

        $summary = "**Quick Summary:**\n";
        $types = [];
        
        // Extract common patterns from the results
        foreach ($results as $result) {
            $content = $result['document']['content'] ?? '';
            $title = $result['document']['title'] ?? '';
            
            // Look for filter types pattern
            if (stripos($title, 'filter') !== false || stripos($content, 'filter') !== false) {
                if (preg_match_all('/- \[([^\]]+)\]/m', $content, $matches)) {
                    foreach ($matches[1] as $match) {
                        $types[] = trim($match);
                    }
                }
                // Also look for match types
                if (preg_match_all('/Available types:\s*\n((?:- [^\n]+\n?)+)/mi', $content, $matches)) {
                    if (isset($matches[1][0])) {
                        $typeList = $matches[1][0];
                        if (preg_match_all('/- ([^(]+)(?:\([^)]*\))?/m', $typeList, $typeMatches)) {
                            foreach ($typeMatches[1] as $type) {
                                $types[] = trim($type);
                            }
                        }
                    }
                }
            }
            
            // Look for field types
            if (stripos($title, 'field') !== false || stripos($content, 'field') !== false) {
                // Extract field type definitions
                if (preg_match_all('/([A-Z][a-zA-Z]+)::\s*make|\'([A-Z][a-zA-Z]+)\'|([A-Z][a-zA-Z]+)\s*field/m', $content, $matches)) {
                    foreach ($matches as $matchGroup) {
                        foreach ($matchGroup as $match) {
                            if ($match && preg_match('/^[A-Z][a-zA-Z]+$/', $match)) {
                                $types[] = $match;
                            }
                        }
                    }
                }
            }
            
            // Look for relationship types
            if (stripos($title, 'relation') !== false || stripos($content, 'relation') !== false) {
                $relationTypes = ['BelongsTo', 'HasOne', 'HasMany', 'BelongsToMany', 'MorphOne', 'MorphMany', 'MorphToMany'];
                foreach ($relationTypes as $relType) {
                    if (stripos($content, $relType) !== false) {
                        $types[] = $relType;
                    }
                }
            }
        }
        
        if (!empty($types)) {
            $uniqueTypes = array_unique($types);
            $count = count($uniqueTypes);
            $summary .= "Found **{$count}** types: " . implode(', ', array_slice($uniqueTypes, 0, 10));
            if (count($uniqueTypes) > 10) {
                $summary .= " (and " . (count($uniqueTypes) - 10) . " more)";
            }
            $summary .= "\n";
        }
        
        return $summary;
    }
}
