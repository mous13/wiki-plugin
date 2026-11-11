<?php

declare(strict_types=1);

namespace Citadel\Wiki\MenuBuilder\MenuType;

use Citadel\Wiki\Entity\Wiki;
use Citadel\Wiki\MenuBuilder\Form\WikiPayloadType;
use Citadel\Wiki\Repository\WikiRepository;
use Citadel\Wiki\Service\WikiAccessService;
use Forumify\Core\Entity\MenuItem;
use Forumify\Core\MenuBuilder\MenuType\UrlMenuType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class WikiMenuType extends UrlMenuType
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly WikiRepository $wikiRepository,
        private readonly WikiAccessService $accessService,
    ) {
    }

    public function getType(): string
    {
        return 'wiki';
    }

    public function getPayloadFormType(): ?string
    {
        return WikiPayloadType::class;
    }

    protected function canView(MenuItem $item): bool
    {
        $wiki = $this->findWiki($item);

        return $wiki !== null && parent::canView($item) && $this->accessService->canView($wiki);
    }

    protected function getUrl(MenuItem $item): string
    {
        $wiki = $this->findWiki($item);

        return $wiki !== null ? $this->urlGenerator->generate('wiki_home', ['slug' => $wiki->getSlug()]) : '';
    }

    private function findWiki(MenuItem $item): ?Wiki
    {
        $id = $item->getPayloadValue('wiki');

        return is_numeric($id) ? $this->wikiRepository->find((int)$id) : null;
    }
}
