<?php

declare(strict_types=1);

namespace BinarCode\LaravelRestifyMcp\Mcp\Tools;

use BinarCode\LaravelRestifyMcp\Services\DocIndexer;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

class NavigateDocs extends Tool
{
    public function __construct(protected DocIndexer $indexer) {}

    public function description(): string
    {
        return 'Navigate and browse Laravel Restify documentation by category or get an overview of available documentation structure. This tool helps you understand what documentation is available and provides organized access to different sections like installation, repositories, fields, authentication, etc.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema
            ->string('action')
            ->description('Navigation action: "overview" for documentation structure, "category" to browse a specific category, "list-categories" to see all available categories')
            ->required()
            ->string('category')
            ->description('Specific category to browse (required when action is "category"): installation, repositories, fields, filters, auth, actions, performance, testing')
            ->optional()
            ->boolean('include_content')
            ->description('Include document summaries and content previews (default: true)')
            ->optional()
            ->integer('limit')
            ->description('Maximum number of documents to show per category (default: 20)')
            ->optional();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function handle(array $arguments): ToolResult|Generator
    {
        try {
            $action = strtolower(trim($arguments['action']));
            $category = $arguments['category'] ?? null;
            $includeContent = $arguments['include_content'] ?? true;
            $limit = min($arguments['limit'] ?? 20, 50);

            // Initialize indexer
            $this->initializeIndexer();

            return match ($action) {
                'overview' => $this->generateOverview($includeContent),
                'list-categories', 'categories' => $this->listCategories(),
                'category' => $this->browseCategory($category, $includeContent, $limit),
                default => ToolResult::error('Invalid action. Use "overview", "list-categories", or "category"'),
            };
        } catch (\Throwable $e) {
            return ToolResult::error('Navigation failed: '.$e->getMessage());
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

    protected function generateOverview(bool $includeContent): ToolResult
    {
        $categories = $this->indexer->getCategories();

        if (empty($categories)) {
            return ToolResult::text("No Laravel Restify documentation found.\n\nPlease ensure the Laravel Restify package is installed and documentation is available.");
        }

        $output = "# Laravel Restify Documentation Overview\n\n";

        $totalDocs = array_sum(array_column($categories, 'count'));
        $output .= "**Total documentation files:** {$totalDocs}\n";
        $output .= '**Categories available:** '.count($categories)."\n\n";

        foreach ($categories as $categoryKey => $categoryInfo) {
            $output .= "## {$categoryInfo['name']} ({$categoryInfo['count']} document(s))\n\n";

            if ($includeContent && ! empty($categoryInfo['documents'])) {
                $previewCount = min(3, count($categoryInfo['documents']));
                for ($i = 0; $i < $previewCount; $i++) {
                    $doc = $categoryInfo['documents'][$i];
                    $output .= "- **{$doc['title']}**";
                    if (! empty($doc['summary'])) {
                        $output .= ': '.substr($doc['summary'], 0, 100).'...';
                    }
                    $output .= "\n";
                }

                if (count($categoryInfo['documents']) > $previewCount) {
                    $remaining = count($categoryInfo['documents']) - $previewCount;
                    $output .= "- *... and {$remaining} more document(s)*\n";
                }
            }

            $output .= "\n";
        }

        $output .= "---\n\n";
        $output .= "**Next steps:**\n";
        $output .= "- Use `navigate-docs` with action \"category\" to explore a specific category\n";
        $output .= "- Use `search-restify-docs` to find specific topics\n";
        $output .= "- Use `get-code-examples` for implementation examples\n";

        return ToolResult::text($output);
    }

    protected function listCategories(): ToolResult
    {
        $categories = $this->indexer->getCategories();

        if (empty($categories)) {
            return ToolResult::text('No documentation categories found.');
        }

        $output = "# Laravel Restify Documentation Categories\n\n";

        foreach ($categories as $categoryKey => $categoryInfo) {
            $output .= "## {$categoryKey}\n";
            $output .= "**Name:** {$categoryInfo['name']}\n";
            $output .= "**Documents:** {$categoryInfo['count']}\n";

            // Show sample topics
            if (! empty($categoryInfo['documents'])) {
                $sampleTitles = array_slice(array_column($categoryInfo['documents'], 'title'), 0, 3);
                $output .= '**Sample topics:** '.implode(', ', $sampleTitles);
                if (count($categoryInfo['documents']) > 3) {
                    $output .= ' and more...';
                }
            }
            $output .= "\n\n";
        }

        $output .= "**Usage:** Use `navigate-docs` with action \"category\" and specify one of the category keys above.\n";

        return ToolResult::text($output);
    }

    protected function browseCategory(?string $category, bool $includeContent, int $limit): ToolResult
    {
        if (! $category) {
            return ToolResult::error('Category is required when action is "category". Use "list-categories" to see available categories.');
        }

        $documents = $this->indexer->getDocumentsByCategory($category);

        if (empty($documents)) {
            $availableCategories = array_keys($this->indexer->getCategories());

            return ToolResult::text(
                "No documents found in category: **{$category}**\n\n".
                '**Available categories:** '.implode(', ', $availableCategories)
            );
        }

        $categoryName = $this->getCategoryName($category);
        $output = "# {$categoryName} Documentation\n\n";
        $output .= "**Category:** {$category}\n";
        $output .= '**Total documents:** '.count($documents)."\n\n";

        $limitedDocs = array_slice($documents, 0, $limit);

        foreach ($limitedDocs as $index => $doc) {
            $output .= '## '.($index + 1).". {$doc['title']}\n";

            if ($includeContent) {
                if (! empty($doc['summary'])) {
                    $output .= "**Summary:** {$doc['summary']}\n\n";
                }

                // Show main headings
                if (! empty($doc['headings'])) {
                    $mainHeadings = array_filter(
                        $doc['headings'],
                        fn ($h) => $h['level'] <= 3
                    );

                    if (! empty($mainHeadings)) {
                        $output .= "**Main sections:**\n";
                        foreach (array_slice($mainHeadings, 0, 5) as $heading) {
                            $indent = str_repeat('  ', $heading['level'] - 1);
                            $output .= "{$indent}- {$heading['text']}\n";
                        }
                        $output .= "\n";
                    }
                }

                // Show code examples count
                if (! empty($doc['code_examples'])) {
                    $examplesByLang = [];
                    foreach ($doc['code_examples'] as $example) {
                        $lang = $example['language'];
                        $examplesByLang[$lang] = ($examplesByLang[$lang] ?? 0) + 1;
                    }

                    $langSummary = [];
                    foreach ($examplesByLang as $lang => $count) {
                        $langSummary[] = "{$lang} ({$count})";
                    }

                    $output .= '**Code examples:** '.implode(', ', $langSummary)."\n\n";
                }
            }

            $output .= "---\n\n";
        }

        if (count($documents) > $limit) {
            $remaining = count($documents) - $limit;
            $output .= "*Showing first {$limit} of ".count($documents)." documents. {$remaining} more available.*\n\n";
        }

        $output .= "**Tools to explore further:**\n";
        $output .= "- `search-restify-docs` with category=\"{$category}\" for specific topics\n";
        $output .= "- `get-code-examples` with category=\"{$category}\" for implementation examples\n";

        return ToolResult::text($output);
    }

    protected function getCategoryName(string $category): string
    {
        $categories = config('restify-mcp.categories', []);

        return $categories[$category]['name'] ?? ucfirst(str_replace(['-', '_'], ' ', $category));
    }
}
