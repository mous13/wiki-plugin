<?php

declare(strict_types=1);

namespace Citadel\Wiki\Markdown;

use League\CommonMark\Node\Inline\AbstractInline;

class WikiLinkNode extends AbstractInline
{
    public bool $exists = false;

    public function __construct(
        public readonly string $slug,
        public readonly string $target,
        public readonly string $label,
        public readonly bool $hidden,
    ) {
        parent::__construct();
    }
}
