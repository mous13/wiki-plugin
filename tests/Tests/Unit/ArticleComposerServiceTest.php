<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit;

use Citadel\Wiki\Service\ArticleComposerService;
use PHPUnit\Framework\TestCase;

class ArticleComposerServiceTest extends TestCase
{
    public function testSeparatesInfoboxLeadAndBody(): void
    {
        $article = (new ArticleComposerService())->compose('<aside class="card wiki-infobox">x</aside><p>Lead</p><h2 id="a">A</h2><p>Body</p>', []);

        self::assertSame('<aside class="card wiki-infobox">x</aside>', $article['infobox']);
        self::assertSame('<p>Lead</p>', $article['lead']);
        self::assertSame('<h2 id="a">A</h2><p>Body</p>', $article['body']);
    }

    public function testNumbersNestedHeadingsAndSkippedLevels(): void
    {
        $toc = [];
        foreach ([2, 3, 3, 2, 4, 2] as $i => $level) {
            $toc[] = ['level' => $level, 'id' => "h$i", 'text' => "T$i"];
        }

        $article = (new ArticleComposerService())->compose('', $toc);

        self::assertSame(['1', '1.1', '1.2', '2', '2.1', '3'], array_column($article['contents'], 'number'));
        self::assertTrue($article['showContents']);
    }

    public function testMarkerChoosesPositionAndForcesContents(): void
    {
        $article = (new ArticleComposerService())->compose('<h1>Title</h1><p>Intro</p><p>wiki-contents-here</p><h2>Next</h2>', [['level' => 2, 'id' => 'a', 'text' => 'A']]);

        self::assertSame('<h1>Title</h1><p>Intro</p>', $article['lead']);
        self::assertSame('<h2>Next</h2>', $article['body']);
        self::assertTrue($article['showContents']);
    }

    public function testShortPagesHideContents(): void
    {
        self::assertFalse((new ArticleComposerService())->compose('', [['level' => 2, 'id' => 'a', 'text' => 'A']])['showContents']);
    }
}
