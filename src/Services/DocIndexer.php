<?php

declare(strict_types=1);

namespace BinarCode\LaravelRestifyMcp\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DocIndexer
{
    protected array $documents = [];

    protected array $index = [];

    public function __construct(
        protected DocParser $parser,
        protected DocCache $cache
    ) {}

    public function indexDocuments(array $filePaths): void
    {
        $cacheKey = 'indexed_docs_'.md5(serialize($filePaths));

        $cachedData = $this->cache->get($cacheKey);
        if ($cachedData !== null && $this->isIndexValid($cachedData, $filePaths)) {
            $this->documents = $cachedData['documents'];
            $this->index = $cachedData['index'];

            return;
        }

        $this->documents = [];
        $this->index = [];

        foreach ($filePaths as $filePath) {
            if (file_exists($filePath)) {
                $doc = $this->parser->parse($filePath);
                if ($doc !== null) {
                    $docId = $this->generateDocumentId($filePath);
                    $this->documents[$docId] = $doc;
                    $this->buildIndex($docId, $doc);
                }
            }
        }

        $this->cache->put($cacheKey, [
            'documents' => $this->documents,
            'index' => $this->index,
            'timestamps' => $this->getFileTimestamps($filePaths),
            'created_at' => time(),
        ]);
    }

    public function search(string $query, ?string $category = null, int $limit = 10): array
    {
        if (empty($this->index)) {
            return [];
        }

        $terms = $this->tokenizeQuery($query);
        $scores = [];

        foreach ($this->documents as $docId => $doc) {
            // Category filter
            if ($category !== null && $doc['category'] !== $category) {
                continue;
            }

            $score = $this->calculateRelevanceScore($doc, $terms);
            if ($score > 0) {
                $scores[$docId] = $score;
            }
        }

        // Sort by relevance score (descending)
        arsort($scores);

        // Limit results
        $limitedScores = array_slice($scores, 0, $limit, true);

        // Build result set with document data and snippets
        $results = [];
        foreach ($limitedScores as $docId => $score) {
            $doc = $this->documents[$docId];
            $results[] = [
                'document' => $doc,
                'relevance_score' => round($score, 2),
                'snippet' => $this->generateSnippet($doc, $terms),
                'matched_headings' => $this->findMatchingHeadings($doc, $terms),
                'matched_code_examples' => $this->findMatchingCodeExamples($doc, $terms),
            ];
        }

        return $results;
    }

    public function getCategories(): array
    {
        $categories = [];
        foreach ($this->documents as $doc) {
            $category = $doc['category'];
            if (! isset($categories[$category])) {
                $categories[$category] = [
                    'name' => $this->getCategoryName($category),
                    'count' => 0,
                    'documents' => [],
                ];
            }
            $categories[$category]['count']++;
            $categories[$category]['documents'][] = [
                'title' => $doc['title'],
                'file_path' => $doc['file_path'],
                'summary' => $doc['summary'],
            ];
        }

        return $categories;
    }

    public function getDocumentsByCategory(string $category): array
    {
        return Collection::make($this->documents)
            ->filter(fn ($doc) => $doc['category'] === $category)
            ->values()
            ->toArray();
    }

    protected function isIndexValid(array $cachedData, array $filePaths): bool
    {
        $timestamps = $cachedData['timestamps'] ?? [];
        $currentTimestamps = $this->getFileTimestamps($filePaths);

        return $timestamps === $currentTimestamps;
    }

    protected function getFileTimestamps(array $filePaths): array
    {
        $timestamps = [];
        foreach ($filePaths as $filePath) {
            if (file_exists($filePath)) {
                $timestamps[$filePath] = filemtime($filePath);
            }
        }

        return $timestamps;
    }

    protected function generateDocumentId(string $filePath): string
    {
        return md5($filePath);
    }

    protected function buildIndex(string $docId, array $doc): void
    {
        $content = $doc['content'];
        $title = $doc['title'];

        // Index title with higher weight
        $titleTerms = $this->tokenize($title);
        foreach ($titleTerms as $term) {
            $this->index[$term][$docId]['title'] = ($this->index[$term][$docId]['title'] ?? 0) + 1;
        }

        // Index headings with medium weight
        foreach ($doc['headings'] as $heading) {
            $headingTerms = $this->tokenize($heading['text']);
            foreach ($headingTerms as $term) {
                $this->index[$term][$docId]['heading'] = ($this->index[$term][$docId]['heading'] ?? 0) + 1;
            }
        }

        // Index content with normal weight
        $contentTerms = $this->tokenize($content);
        foreach ($contentTerms as $term) {
            $this->index[$term][$docId]['content'] = ($this->index[$term][$docId]['content'] ?? 0) + 1;
        }

        // Index code examples
        foreach ($doc['code_examples'] as $codeExample) {
            $codeTerms = $this->tokenize($codeExample['code']);
            foreach ($codeTerms as $term) {
                $this->index[$term][$docId]['code'] = ($this->index[$term][$docId]['code'] ?? 0) + 1;
            }
        }
    }

    protected function tokenizeQuery(string $query): array
    {
        return $this->tokenize($query);
    }

    protected function tokenize(string $text): array
    {
        // Convert to lowercase
        $text = strtolower($text);

        // Remove special characters but keep spaces and hyphens
        $text = preg_replace('/[^\w\s\-]/', ' ', $text);

        // Split by whitespace and filter empty terms
        $terms = array_filter(preg_split('/\s+/', $text));

        // Remove very short terms
        $terms = array_filter($terms, fn ($term) => strlen($term) >= config('restify-mcp.search.min_query_length', 2));

        return array_unique($terms);
    }

    protected function calculateRelevanceScore(array $doc, array $queryTerms): float
    {
        $score = 0;
        $boostScores = config('restify-mcp.search.boost_scores', [
            'title' => 3.0,
            'heading' => 2.0,
            'content' => 1.0,
            'code_example' => 1.5,
        ]);

        $docId = $this->generateDocumentId($doc['file_path']);

        foreach ($queryTerms as $term) {
            if (! isset($this->index[$term][$docId])) {
                continue;
            }

            $termOccurrences = $this->index[$term][$docId];

            foreach ($termOccurrences as $field => $count) {
                $fieldBoost = match ($field) {
                    'title' => $boostScores['title'],
                    'heading' => $boostScores['heading'],
                    'code' => $boostScores['code_example'],
                    default => $boostScores['content'],
                };

                $score += $count * $fieldBoost;
            }
        }

        // Normalize by document length
        $normalizedScore = $score / sqrt($doc['word_count'] + 1);

        return $normalizedScore;
    }

    protected function generateSnippet(array $doc, array $queryTerms): string
    {
        $content = $doc['content'];
        $sentences = preg_split('/[.!?]+/', $content);

        $relevantSentences = [];
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (empty($sentence)) {
                continue;
            }

            $matchCount = 0;
            foreach ($queryTerms as $term) {
                if (stripos($sentence, $term) !== false) {
                    $matchCount++;
                }
            }

            if ($matchCount > 0) {
                $relevantSentences[] = [
                    'sentence' => $sentence,
                    'matches' => $matchCount,
                ];
            }
        }

        if (empty($relevantSentences)) {
            return Str::limit($content, 200);
        }

        // Sort by match count
        usort($relevantSentences, fn ($a, $b) => $b['matches'] <=> $a['matches']);

        // Take top sentences that fit within length limit
        $snippet = '';
        foreach ($relevantSentences as $item) {
            if (strlen($snippet.$item['sentence']) > 200) {
                break;
            }
            $snippet .= $item['sentence'].'. ';
        }

        return trim($snippet) ?: Str::limit($content, 200);
    }

    protected function findMatchingHeadings(array $doc, array $queryTerms): array
    {
        $matchingHeadings = [];

        foreach ($doc['headings'] as $heading) {
            $matches = 0;
            foreach ($queryTerms as $term) {
                if (stripos($heading['text'], $term) !== false) {
                    $matches++;
                }
            }

            if ($matches > 0) {
                $matchingHeadings[] = $heading + ['matches' => $matches];
            }
        }

        return $matchingHeadings;
    }

    protected function findMatchingCodeExamples(array $doc, array $queryTerms): array
    {
        $matchingExamples = [];

        foreach ($doc['code_examples'] as $example) {
            $matches = 0;
            foreach ($queryTerms as $term) {
                if (stripos($example['code'], $term) !== false) {
                    $matches++;
                }
            }

            if ($matches > 0) {
                $matchingExamples[] = $example + ['matches' => $matches];
            }
        }

        return array_slice($matchingExamples, 0, 3); // Limit to 3 examples
    }

    protected function getCategoryName(string $category): string
    {
        $categories = config('restify-mcp.categories', []);

        return $categories[$category]['name'] ?? ucfirst(str_replace(['-', '_'], ' ', $category));
    }
}
