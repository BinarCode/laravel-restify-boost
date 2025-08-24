<?php

declare(strict_types=1);

namespace BinarCode\RestifyBoost\Services;

use Symfony\Component\Yaml\Yaml;

class DocParser
{
    public function __construct(
        protected DocCache $cache
    ) {}

    public function parse(string $filePath): ?array
    {
        if (! file_exists($filePath)) {
            return null;
        }

        $cacheKey = 'parsed_doc_'.md5($filePath.'_'.filemtime($filePath));

        return $this->cache->remember($cacheKey, function () use ($filePath) {
            $content = file_get_contents($filePath);

            if ($content === false) {
                return null;
            }

            return $this->parseContent($content, $filePath);
        });
    }

    public function parseContent(string $content, string $filePath = ''): array
    {
        // Parse frontmatter
        $parts = preg_split('/^---$/m', $content, 3);
        $frontmatter = [];
        $markdown = $content;

        if (count($parts) >= 3) {
            try {
                $frontmatter = Yaml::parse($parts[1]) ?: [];
            } catch (\Exception $e) {
                // If YAML parsing fails, continue without frontmatter
                $frontmatter = [];
            }
            $markdown = trim($parts[2]);
        }

        // Extract code blocks
        $codeBlocks = $this->extractCodeBlocks($markdown);

        // Extract headings with hierarchy
        $headings = $this->extractHeadings($markdown);

        // Clean markdown for content extraction
        $cleanContent = $this->cleanMarkdown($markdown);

        // Extract summary
        $summary = $this->extractSummary($cleanContent);

        // Determine category from file path
        $category = $this->determineCategory($filePath);

        return [
            'file_path' => $filePath,
            'category' => $category,
            'frontmatter' => $frontmatter,
            'title' => $frontmatter['title'] ?? $this->extractTitle($headings, $filePath),
            'content' => $cleanContent,
            'raw_content' => $markdown,
            'code_examples' => $codeBlocks,
            'headings' => $headings,
            'summary' => $summary,
            'word_count' => str_word_count($cleanContent),
            'estimated_tokens' => $this->estimateTokens($cleanContent),
        ];
    }

    protected function extractCodeBlocks(string $markdown): array
    {
        $codeBlocks = [];

        // Match code blocks with language specification
        preg_match_all('/```(\w+)?\n(.*?)```/s', $markdown, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $language = $match[1] ?: 'text';
            $code = trim($match[2]);

            if (! empty($code)) {
                $codeBlocks[] = [
                    'language' => $language,
                    'code' => $code,
                    'lines' => substr_count($code, "\n") + 1,
                ];
            }
        }

        return $codeBlocks;
    }

    protected function extractHeadings(string $markdown): array
    {
        $headings = [];

        preg_match_all('/^(#{1,6})\s+(.+)$/m', $markdown, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $level = strlen($match[1]);
            $text = trim($match[2]);

            $headings[] = [
                'level' => $level,
                'text' => $text,
                'anchor' => $this->createAnchor($text),
            ];
        }

        return $headings;
    }

    protected function cleanMarkdown(string $markdown): string
    {
        // Remove code blocks
        $clean = preg_replace('/```[\s\S]*?```/', '', $markdown);

        // Remove inline code
        $clean = preg_replace('/`[^`]*`/', '', $clean);

        // Remove markdown links but keep the text
        $clean = preg_replace('/\[([^\]]*)\]\([^\)]*\)/', '$1', $clean);

        // Remove markdown formatting
        $clean = preg_replace('/[*_]{1,2}([^*_]*)[*_]{1,2}/', '$1', $clean);

        // Remove headings markers
        $clean = preg_replace('/^#{1,6}\s+/', '', $clean);

        // Clean up excessive whitespace
        $clean = preg_replace('/\n{3,}/', "\n\n", $clean);
        $clean = preg_replace('/[ \t]+/', ' ', $clean);

        return trim($clean);
    }

    protected function extractSummary(string $content): string
    {
        $summaryLength = config('restify-mcp.docs.processing.summary_length', 300);

        $sentences = preg_split('/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $summary = '';
        $length = 0;

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($length + strlen($sentence) > $summaryLength) {
                break;
            }
            $summary .= $sentence.'. ';
            $length += strlen($sentence);
        }

        return trim($summary);
    }

    protected function determineCategory(string $filePath): string
    {
        $categories = config('restify-mcp.categories', []);

        foreach ($categories as $categoryKey => $categoryConfig) {
            $paths = $categoryConfig['paths'] ?? [];
            foreach ($paths as $pattern) {
                if (fnmatch('*'.$pattern, $filePath)) {
                    return $categoryKey;
                }
            }
        }

        // Fallback: extract from path
        if (preg_match('/\/([^\/]+)\/[^\/]*\.md$/', $filePath, $matches)) {
            return $matches[1];
        }

        return 'general';
    }

    protected function extractTitle(array $headings, string $filePath): string
    {
        // Try to get the first H1 heading
        foreach ($headings as $heading) {
            if ($heading['level'] === 1) {
                return $heading['text'];
            }
        }

        // Fallback to filename
        return ucfirst(str_replace(['-', '_'], ' ', basename($filePath, '.md')));
    }

    protected function createAnchor(string $text): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $text));
    }

    protected function estimateTokens(string $content): int
    {
        // Rough estimation: 1 token ≈ 4 characters for English text
        return (int) ceil(strlen($content) / 4);
    }
}
