<?php

declare(strict_types=1);

namespace Citadel\Wiki\Twig;

use Citadel\Wiki\Entity\Wiki;
use Citadel\Wiki\Service\WikiAccessService;
use Citadel\Wiki\Service\WikiNavigationService;
use Twig\Extension\RuntimeExtensionInterface;

class WikiRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly WikiNavigationService $navigationService,
        private readonly WikiAccessService $accessService,
    ) {
    }

    /**
     * @return array{tree: array<int, array<string, mixed>>}
     */
    public function navigation(Wiki $wiki, ?string $currentSlug): array
    {
        return $this->navigationService->build($wiki, $currentSlug);
    }

    public function canEdit(Wiki $wiki): bool
    {
        return $this->accessService->canEdit($wiki);
    }

    public function cssVersion(): int
    {
        return (int)filemtime(dirname(__DIR__, 2) . '/public/wiki.css');
    }
}
