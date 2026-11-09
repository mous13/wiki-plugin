<?php

declare(strict_types=1);

namespace Citadel\Wiki\Markdown;

use Citadel\Wiki\Service\PageSlugger;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

class WikiLinkParser implements InlineParserInterface
{
    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('\[\[([^\[\]|\n]+)(?:\|([^\[\]\n]+))?\]\]');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        [$target, $label] = $inlineContext->getSubMatches() + [null, null];
        $target = trim((string)$target);
        $forceLink = str_starts_with($target, ':');
        $target = ltrim($target, ':');

        $slug = PageSlugger::slug($target);
        if ($slug === '') {
            return false;
        }

        $label = trim((string)$label);
        if ($label === '') {
            $label = PageSlugger::isCategory($slug) ? PageSlugger::categoryLabel($slug) : $target;
        }

        $inlineContext->getCursor()->advanceBy($inlineContext->getFullMatchLength());
        $inlineContext->getContainer()->appendChild(new WikiLinkNode(
            $slug,
            $target,
            $label,
            PageSlugger::isCategory($slug) && !$forceLink,
        ));

        return true;
    }
}
