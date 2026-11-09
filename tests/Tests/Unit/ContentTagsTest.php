<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit;

use Citadel\Wiki\Service\ContentTags;
use PHPUnit\Framework\TestCase;

class ContentTagsTest extends TestCase
{
    public function testConvertsAllowedTags(): void
    {
        self::assertSame('![a](wiki-resource:rifle.png)', ContentTags::tokenize("![a]({{ resource('rifle.png') }})"));
        self::assertStringContainsString('wiki-snippet:note', ContentTags::tokenize('{{snippet("note")}}'));
    }

    public function testTagsSplitFromNeighbouringText(): void
    {
        self::assertStringContainsString("\n\nwiki-contents-here\n\n", ContentTags::tokenize("above\n{{ contents }}\nbelow"));
        self::assertStringContainsString("\n\nwiki-snippet:x\n\n", ContentTags::tokenize("above\n{{ snippet('x') }}\nbelow"));
    }

    public function testTagsAfterAnInfoboxAndBeforeCodeStillWork(): void
    {
        $result = ContentTags::tokenize("```infobox\nimage: {{ resource('art') }}\n```\n\n{{contents}}\n\n```json\n{{ resource('kept') }}\n```");

        self::assertStringContainsString('image: wiki-resource:art', $result);
        self::assertStringContainsString('wiki-contents-here', $result);
        self::assertStringContainsString("{{ resource('kept') }}", $result);
    }

    public function testLeavesOtherTwigAndCodeAlone(): void
    {
        foreach (["{{ widget('x') }}", "{% include 'x' %}", "{{ resource(evil) }}", "`{{ resource('x') }}`", "```\n{{ resource('x') }}\n```", "text {{ snippet('x') }}", "text {{ contents }}", "`{{ contents }}`"] as $source) {
            self::assertSame($source, ContentTags::tokenize($source));
        }
    }
}
