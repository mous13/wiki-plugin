<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit;

use Citadel\Wiki\Service\DiffService;
use PHPUnit\Framework\TestCase;

class DiffServiceTest extends TestCase
{
    public function testMarksChangedLinesWithLineNumbers(): void
    {
        $rows = (new DiffService())->build("a\nb\nc\n", "a\nx\nc\n");

        self::assertSame(
            [['same', 1, 1, 'a'], ['removed', 2, null, 'b'], ['added', null, 2, 'x'], ['same', 3, 3, 'c']],
            array_map(static fn (array $row) => [$row['type'], $row['old'], $row['new'], $row['text']], $rows),
        );
    }

    public function testCollapsesUnchangedLinesOutsideContext(): void
    {
        $from = implode("\n", range(1, 20)) . "\n";
        $to = str_replace("\n10\n", "\nten\n", $from);

        $rows = (new DiffService())->build($from, $to, 1);

        self::assertSame(
            ['gap', 'same', 'removed', 'added', 'same', 'gap'],
            array_column($rows, 'type'),
        );
    }

    public function testIdenticalContentHasNoVisibleRows(): void
    {
        self::assertSame(['gap'], array_column((new DiffService())->build("a\n", "a\n"), 'type'));
    }
}
