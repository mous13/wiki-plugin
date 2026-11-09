<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit;

use Citadel\Wiki\Service\SearchSnippet;
use PHPUnit\Framework\TestCase;

class SearchSnippetTest extends TestCase
{
    public function testStripsMarkupAndBlocks(): void
    {
        $markdown = "```infobox\ntitle: Rifle\n```\n\n## Heading\n\nA **bold** [[Page|label]] and [[Other]].\n\n| a | b |\n|---|---|\n\n![x](/a.png)\n\n[[Category:Weapons]]";

        self::assertSame('Heading A bold label and Other.', SearchSnippet::fromMarkdown($markdown));
    }
}
