<?php

declare(strict_types=1);

namespace Citadel\Wiki\Markdown;

class RenderedMarkdown
{
    /**
     * @param array<int, array{level: int, id: string, text: string}> $toc
     * @param array<string> $links
     * @param array<string, string> $missing
     */
    public function __construct(
        public readonly string $html,
        public readonly array $toc,
        public readonly array $links,
        public readonly array $missing,
    ) {
    }
}
