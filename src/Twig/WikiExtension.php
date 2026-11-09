<?php

declare(strict_types=1);

namespace Citadel\Wiki\Twig;

use Citadel\Wiki\Service\PageSlugger;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class WikiExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('wiki_nav', [WikiRuntime::class, 'navigation']),
            new TwigFunction('wiki_can_edit', [WikiRuntime::class, 'canEdit']),
            new TwigFunction('wiki_css_version', [WikiRuntime::class, 'cssVersion']),
            new TwigFunction('wiki_display_title', PageSlugger::displayTitle(...)),
        ];
    }
}
