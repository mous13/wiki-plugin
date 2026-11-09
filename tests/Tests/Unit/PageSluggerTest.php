<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit;

use Citadel\Wiki\Service\PageSlugger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PageSluggerTest extends TestCase
{
    #[DataProvider('titles')]
    public function testSlug(string $title, string $expected): void
    {
        self::assertSame($expected, PageSlugger::slug($title));
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function titles(): iterable
    {
        yield ['Plasma Rifle', 'plasma-rifle'];
        yield ['  Home ', 'home'];
        yield ['Category:Weapons', 'category:weapons'];
        yield ['category : Heavy Weapons', 'category:heavy-weapons'];
        yield ['!!!', ''];
        yield ['Déjà Vu', 'deja-vu'];
    }

    public function testSlugsMatchRouteRequirement(): void
    {
        self::assertMatchesRegularExpression('#^' . PageSlugger::ROUTE_REQUIREMENT . '$#', PageSlugger::slug('Category:A b c'));
    }

    public function testDisplayTitleHidesCategoryPrefixOnly(): void
    {
        self::assertSame('Milhq', PageSlugger::displayTitle('Category:Milhq'));
        self::assertSame('Weapons', PageSlugger::displayTitle('category : Weapons'));
        self::assertSame('Plasma Rifle', PageSlugger::displayTitle('Plasma Rifle'));
    }

    public function testHumanize(): void
    {
        self::assertSame('Plasma rifle', PageSlugger::humanize('plasma-rifle'));
        self::assertSame('Category:Heavy weapons', PageSlugger::humanize('category:heavy-weapons'));
    }
}
