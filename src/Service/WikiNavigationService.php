<?php

declare(strict_types=1);

namespace Citadel\Wiki\Service;

use Citadel\Wiki\Entity\Wiki;
use Citadel\Wiki\Repository\WikiLinkRepository;

class WikiNavigationService
{
    public function __construct(private readonly WikiLinkRepository $linkRepository)
    {
    }

    /**
     * @return array{tree: array<int, array<string, mixed>>}
     */
    public function build(Wiki $wiki, ?string $currentSlug): array
    {
        $members = [];
        $hasParent = [];
        foreach ($this->linkRepository->findCategoryMemberships($wiki) as $row) {
            if ($row['slug'] === $row['category']) {
                continue;
            }

            $members[$row['category']][$row['slug']] = $row['title'];
            if (!PageSlugger::isCategory($row['slug'])) {
                continue;
            }

            $hasParent[$row['slug']] = true;
            $members[$row['slug']] ??= [];
        }

        $tree = [];
        foreach (array_keys($members) as $category) {
            if (!isset($hasParent[$category])) {
                $tree[] = $this->node($category, $currentSlug, [], $members);
            }
        }

        usort($tree, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return ['tree' => $this->keepSinglePath($tree)];
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @return array<int, array<string, mixed>>
     */
    private function keepSinglePath(array $nodes): array
    {
        $selected = null;
        $deepest = 0;
        foreach ($nodes as $index => $node) {
            $depth = $this->openDepth($node);
            if ($depth <= $deepest) {
                continue;
            }

            $selected = $index;
            $deepest = $depth;
        }

        foreach ($nodes as $index => $node) {
            if ($index === $selected) {
                $nodes[$index]['children'] = $this->keepSinglePath($node['children']);
            } else {
                $nodes[$index] = $this->close($node);
            }
        }

        return $nodes;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function openDepth(array $node): int
    {
        if (!$node['open']) {
            return 0;
        }

        return 1 + max([0, ...array_map($this->openDepth(...), $node['children'])]);
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private function close(array $node): array
    {
        $node['open'] = false;
        $node['children'] = array_map($this->close(...), $node['children']);

        return $node;
    }

    /**
     * @param array<string, true> $ancestors
     * @param array<string, array<string, string>> $members
     * @return array<string, mixed>
     */
    private function node(string $category, ?string $currentSlug, array $ancestors, array $members): array
    {
        $ancestors[$category] = true;
        $children = [];
        $pages = [];
        $inChildren = [];
        foreach ($members[$category] ?? [] as $slug => $title) {
            if (!PageSlugger::isCategory($slug) || isset($ancestors[$slug])) {
                continue;
            }

            $child = $this->node($slug, $currentSlug, $ancestors, $members);
            $children[] = $child;
            $inChildren += $child['descendants'];
        }

        foreach ($members[$category] ?? [] as $slug => $title) {
            if (!PageSlugger::isCategory($slug) && !isset($inChildren[$slug])) {
                $pages[] = ['slug' => $slug, 'title' => $title, 'active' => $slug === $currentSlug];
            }
        }

        usort($children, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));
        usort($pages, static fn (array $a, array $b): int => strcasecmp($a['title'], $b['title']));

        $active = $category === $currentSlug;
        $open = $active
            || array_filter($pages, static fn (array $page): bool => $page['active']) !== []
            || array_filter($children, static fn (array $child): bool => $child['open']) !== [];

        return [
            'slug' => $category,
            'label' => PageSlugger::categoryLabel($category),
            'active' => $active,
            'open' => $open,
            'children' => $children,
            'pages' => $pages,
            'descendants' => $inChildren + array_fill_keys(array_column($pages, 'slug'), true),
        ];
    }
}
