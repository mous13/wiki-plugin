<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit;

use Citadel\Wiki\Entity\Wiki;
use Citadel\Wiki\Repository\WikiLinkRepository;
use Citadel\Wiki\Service\WikiNavigationService;
use PHPUnit\Framework\TestCase;

class WikiNavigationServiceTest extends TestCase
{
    public function testNothingOpensWithoutACurrentPage(): void
    {
        self::assertSame([], $this->openPaths([['page', 'Page', 'category:alpha']], null));
    }

    public function testUnrelatedCategoriesStayClosed(): void
    {
        self::assertSame(['alpha'], $this->openPaths([
            ['page', 'Page', 'category:alpha'],
            ['other', 'Other', 'category:beta'],
        ], 'page'));
    }

    public function testOnlyOneCategoryOpensForPageInSeveral(): void
    {
        self::assertSame(['alpha'], $this->openPaths([
            ['page', 'Page', 'category:alpha'],
            ['page', 'Page', 'category:beta'],
        ], 'page'));
    }

    public function testMostSpecificPathWins(): void
    {
        self::assertSame(['beta', 'sub'], $this->openPaths([
            ['page', 'Page', 'category:alpha'],
            ['page', 'Page', 'category:sub'],
            ['category:sub', 'Sub', 'category:beta'],
            ['sibling', 'Sibling', 'category:other-sub'],
            ['category:other-sub', 'Other', 'category:beta'],
        ], 'page'));
    }

    /**
     * @param array<array{string, string, string}> $rows
     * @return array<string>
     */
    private function openPaths(array $rows, ?string $current): array
    {
        $repository = $this->createMock(WikiLinkRepository::class);
        $repository->method('findCategoryMemberships')->willReturn(array_map(
            static fn (array $row): array => ['slug' => $row[0], 'title' => $row[1], 'category' => $row[2]],
            $rows,
        ));

        return $this->collectOpen((new WikiNavigationService($repository))->build(new Wiki(), $current)['tree']);
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @return array<string>
     */
    private function collectOpen(array $nodes): array
    {
        $open = [];
        foreach ($nodes as $node) {
            if ($node['open']) {
                $open = [...$open, strtolower($node['label']), ...$this->collectOpen($node['children'])];
            }
        }

        return $open;
    }
}
