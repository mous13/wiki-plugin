<?php

declare(strict_types=1);

namespace Citadel\Wiki\Service;

use Forumify\Cms\Twig\Extension\CMSExtensionRuntime;

class ContentExpanderService
{
    private const RESOURCE_PATTERN = '/' . ContentTags::RESOURCE_PREFIX . '([\w.-]+)/';
    private const SNIPPET_PATTERN = '#<p>' . ContentTags::SNIPPET_PREFIX . '([\w.-]+)</p>#';

    public function __construct(private readonly CMSExtensionRuntime $cms)
    {
    }

    public function expand(string $html): string
    {
        $html = (string)preg_replace_callback(
            self::RESOURCE_PATTERN,
            fn (array $match): string => htmlspecialchars($this->cms->resource($match[1]), ENT_QUOTES),
            $html,
        );

        return (string)preg_replace_callback(
            self::SNIPPET_PATTERN,
            fn (array $match): string => $this->cms->snippet($match[1]),
            $html,
        );
    }

    /**
     * @return array<string>
     */
    public function findUnknown(string $html): array
    {
        preg_match_all(self::RESOURCE_PATTERN, $html, $resources);
        preg_match_all(self::SNIPPET_PATTERN, $html, $snippets);

        return array_values(array_unique([
            ...array_filter($resources[1], fn (string $slug): bool => $this->cms->resource($slug) === ''),
            ...array_filter($snippets[1], fn (string $slug): bool => $this->cms->snippet($slug) === ''),
        ]));
    }
}
