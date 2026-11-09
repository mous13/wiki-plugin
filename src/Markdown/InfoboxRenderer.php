<?php

declare(strict_types=1);

namespace Citadel\Wiki\Markdown;

use Citadel\Wiki\Service\ContentTags;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\Xml;
use Stringable;
use Symfony\Component\Asset\Packages;

class InfoboxRenderer implements NodeRendererInterface
{
    public function __construct(private readonly Packages $packages)
    {
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): Stringable|string|null
    {
        if (!$node instanceof FencedCode || ($node->getInfoWords()[0] ?? '') !== 'infobox') {
            return null;
        }

        $fields = $this->parseFields($node->getLiteral());
        $title = $this->pull($fields, 'title');
        $image = $this->resolveImage($this->pull($fields, 'image'));

        $html = '<aside class="card wiki-infobox">';
        if ($title !== null) {
            $html .= '<div class="card-title text-center">' . Xml::escape($title) . '</div>';
        }

        if ($image !== null) {
            $html .= '<img class="responsive" src="' . Xml::escape($image) . '" alt="' . Xml::escape($title ?? '') . '">';
        }

        $html .= '<dl class="card-body wiki-infobox-fields">';
        foreach ($fields as $name => $value) {
            $html .= '<dt class="text-bold">' . Xml::escape($name) . '</dt><dd>' . Xml::escape($value) . '</dd>';
        }

        return $html . '</dl></aside>';
    }

    /**
     * @return array<string, string>
     */
    private function parseFields(string $source): array
    {
        $fields = [];
        foreach (preg_split('/\R/', $source) ?: [] as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) !== 2 || trim($parts[0]) === '') {
                continue;
            }

            $fields[trim($parts[0])] = trim(trim($parts[1]), " \t\"'");
        }

        return $fields;
    }

    /**
     * @param array<string, string> $fields
     */
    private function pull(array &$fields, string $key): ?string
    {
        $value = $fields[$key] ?? null;
        unset($fields[$key]);

        return $value === '' ? null : $value;
    }

    private function resolveImage(?string $image): ?string
    {
        if ($image === null) {
            return null;
        }

        if (str_starts_with($image, ContentTags::RESOURCE_PREFIX) || preg_match('#^https?://#i', $image) === 1) {
            return $image;
        }

        if (str_starts_with($image, '/') && !str_starts_with($image, '//')) {
            return $image;
        }

        if (preg_match('#^[\w.-]+(/[\w.-]+)*$#', $image) === 1 && !str_contains($image, '..')) {
            return $this->packages->getUrl($image, 'forumify.media');
        }

        return null;
    }
}
