<?php

declare(strict_types=1);

namespace Citadel\Wiki\Repository;

use Citadel\Wiki\Entity\Wiki;
use Citadel\Wiki\Entity\WikiPage;
use Citadel\Wiki\Entity\WikiRevision;
use Forumify\Core\Repository\AbstractRepository;

/**
 * @extends AbstractRepository<WikiRevision>
 */
class WikiRevisionRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return WikiRevision::class;
    }

    /**
     * @return array<WikiRevision>
     */
    public function findForPage(WikiPage $page, int $limit, int $offset): array
    {
        return $this->findBy(['page' => $page], ['id' => 'DESC'], $limit, $offset);
    }

    public function countForPage(WikiPage $page): int
    {
        return $this->count(['page' => $page]);
    }

    public function findOnPage(WikiPage $page, int $id): ?WikiRevision
    {
        return $this->findOneBy(['page' => $page, 'id' => $id]);
    }

    /**
     * @return array<WikiRevision>
     */
    public function findRecent(Wiki $wiki, int $limit, int $offset = 0): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('p')
            ->innerJoin('r.page', 'p')
            ->where('p.wiki = :wiki')
            ->setParameter('wiki', $wiki)
            ->orderBy('r.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }
}
