<?php

declare(strict_types=1);

namespace Citadel\Wiki\Markdown;

use Citadel\Wiki\Service\PageSlugger;
use InvalidArgumentException;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;
use Stringable;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class WikiLinkRenderer implements NodeRendererInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $wikiSlug,
    ) {
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): Stringable|string|null
    {
        if (!$node instanceof WikiLinkNode) {
            throw new InvalidArgumentException('Incompatible node type: ' . $node::class);
        }

        if ($node->hidden) {
            return '';
        }

        $resolved = $node->exists || PageSlugger::isCategory($node->slug);
        $url = $resolved
            ? $this->urlGenerator->generate('wiki_page', ['slug' => $this->wikiSlug, 'page' => $node->slug])
            : $this->urlGenerator->generate('wiki_page_edit', [
                'slug' => $this->wikiSlug,
                'page' => $node->slug,
                'title' => $node->target,
            ]);

        return new HtmlElement('a', [
            'href' => $url,
            'class' => $resolved ? 'wiki-link' : 'wiki-link wiki-link-missing',
        ], Xml::escape($node->label));
    }
}
