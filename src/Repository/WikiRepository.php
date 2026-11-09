<?php

declare(strict_types=1);

namespace Citadel\Wiki\Repository;

use Citadel\Wiki\Entity\Wiki;
use Forumify\Core\Repository\AbstractRepository;

/**
 * @extends AbstractRepository<Wiki>
 */
class WikiRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return Wiki::class;
    }

    /**
     * @return array<Wiki>
     */
    public function findVisible(): array
    {
        $qb = $this->createQueryBuilder('e')->orderBy('e.title', 'ASC');
        $this->addACLToQuery($qb, 'view');

        return $qb->getQuery()->getResult();
    }
}
