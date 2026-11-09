<?php

declare(strict_types=1);

namespace Citadel\Wiki\Service;

use Citadel\Wiki\Entity\Wiki;
use Citadel\Wiki\Entity\WikiPage;
use Forumify\Core\Service\ACLService;

class WikiAccessService
{
    public function __construct(private readonly ACLService $aclService)
    {
    }

    public function canView(Wiki $wiki): bool
    {
        return $this->aclService->can('view', $wiki);
    }

    public function canModerate(Wiki $wiki): bool
    {
        return $this->aclService->can('moderate', $wiki);
    }

    public function canEdit(Wiki $wiki, ?WikiPage $page = null): bool
    {
        if ($page !== null && $page->isProtected()) {
            return $this->canModerate($wiki);
        }

        return $this->aclService->can('edit', $wiki) || $this->canModerate($wiki);
    }
}
