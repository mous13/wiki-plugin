<?php

declare(strict_types=1);

namespace Citadel\Wiki\Markdown;

use Citadel\Wiki\Entity\Wiki;
use Citadel\Wiki\Repository\WikiPageRepository;
use Citadel\Wiki\Service\ContentTags;
use Citadel\Wiki\Service\PageSlugger;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalink;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\StringContainerHelper;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class WikiMarkdownRenderer
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Packages $packages,
        private readonly WikiPageRepository $pageRepository,
    ) {
    }

    public function render(Wiki $wiki, string $markdown): RenderedMarkdown
    {
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 50,
            'heading_permalink' => [
                'html_class' => 'wiki-anchor',
                'id_prefix' => 'wiki',
                'fragment_prefix' => 'wiki',
                'apply_id_to_heading' => true,
                'insert' => 'after',
                'symbol' => '#',
                'title' => '',
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new HeadingPermalinkExtension());
        $environment->addInlineParser(new WikiLinkParser(), 100);
        $environment->addRenderer(WikiLinkNode::class, new WikiLinkRenderer($this->urlGenerator, $wiki->getSlug()));
        $environment->addRenderer(FencedCode::class, new InfoboxRenderer($this->packages), 10);

        $document = (new MarkdownParser($environment))->parse(ContentTags::tokenize($markdown));
        [$links, $missing] = $this->resolveLinks($wiki, $document);
        $html = (string)(new HtmlRenderer($environment))->renderDocument($document);

        return new RenderedMarkdown($html, $this->buildToc($document), $links, $missing);
    }

    /**
     * @return array{0: array<string>, 1: array<string, string>}
     */
    private function resolveLinks(Wiki $wiki, Document $document): array
    {
        $nodes = [];
        foreach ($document->iterator() as $node) {
            if ($node instanceof WikiLinkNode) {
                $nodes[] = $node;
            }
        }

        $slugs = array_values(array_unique(array_map(static fn (WikiLinkNode $node) => $node->slug, $nodes)));
        $existing = array_flip($this->pageRepository->findExistingSlugs($wiki, $slugs));

        $missing = [];
        foreach ($nodes as $node) {
            $node->exists = isset($existing[$node->slug]);
            if (!$node->exists && !PageSlugger::isCategory($node->slug)) {
                $missing[$node->slug] = $node->target;
            }
        }

        $links = [];
        foreach ($nodes as $node) {
            if ($node->hidden || !PageSlugger::isCategory($node->slug)) {
                $links[$node->slug] = $node->slug;
            }
        }

        return [array_values($links), $missing];
    }

    /**
     * @return array<int, array{level: int, id: string, text: string}>
     */
    private function buildToc(Document $document): array
    {
        $toc = [];
        foreach ($document->iterator() as $node) {
            if (!$node instanceof Heading) {
                continue;
            }

            foreach ($node->children() as $child) {
                if ($child instanceof HeadingPermalink) {
                    $toc[] = [
                        'level' => $node->getLevel(),
                        'id' => 'wiki-' . $child->getSlug(),
                        'text' => StringContainerHelper::getChildText($node),
                    ];
                    break;
                }
            }
        }

        return $toc;
    }
}
