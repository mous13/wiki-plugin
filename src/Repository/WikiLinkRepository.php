<?php

declare(strict_types=1);

namespace Citadel\Wiki\Repository;

use Citadel\Wiki\Entity\Wiki;
use Citadel\Wiki\Entity\WikiLink;
use Citadel\Wiki\Entity\WikiPage;
use Citadel\Wiki\Service\PageSlugger;
use Forumify\Core\Repository\AbstractRepository;

/**
 * @extends AbstractRepository<WikiLink>
 */
class WikiLinkRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return WikiLink::class;
    }

    public function deleteForPage(WikiPage $page): void
    {
        $this->createQueryBuilder('l')
            ->delete()
            ->where('l.fromPage = :page')
            ->setParameter('page', $page)
            ->getQuery()
            ->execute();
    }

    /**
     * @return array<string>
     */
    public function findCategorySlugs(WikiPage $page): array
    {
        return $this->createQueryBuilder('l')
            ->select('l.toSlug')
            ->where('l.fromPage = :page')
            ->andWhere('l.toSlug LIKE :prefix')
            ->setParameter('page', $page)
            ->setParameter('prefix', PageSlugger::CATEGORY_PREFIX . '%')
            ->orderBy('l.toSlug', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * @return array<array{slug: string, title: string, category: string}>
     */
    public function findCategoryMemberships(Wiki $wiki): array
    {
        return $this->createQueryBuilder('l')
            ->select('p.slug AS slug', 'p.title AS title', 'l.toSlug AS category')
            ->innerJoin('l.fromPage', 'p')
            ->where('p.wiki = :wiki')
            ->andWhere('l.toSlug LIKE :prefix')
            ->setParameter('wiki', $wiki)
            ->setParameter('prefix', PageSlugger::CATEGORY_PREFIX . '%')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * @return array<array{slug: string, links: int}>
     */
    public function findWanted(Wiki $wiki, int $limit, int $offset): array
    {
        $em = $this->getEntityManager();
        $existing = $em->createQueryBuilder()
            ->select('existing.slug')
            ->from(WikiPage::class, 'existing')
            ->where('existing.wiki = :wiki');

        $qb = $this->createQueryBuilder('l');

        return $qb
            ->select('l.toSlug AS slug', 'COUNT(l.id) AS links')
            ->innerJoin('l.fromPage', 'p')
            ->where('p.wiki = :wiki')
            ->andWhere('l.toSlug NOT LIKE :prefix')
            ->andWhere($qb->expr()->notIn('l.toSlug', $existing->getDQL()))
            ->setParameter('wiki', $wiki)
            ->setParameter('prefix', PageSlugger::CATEGORY_PREFIX . '%')
            ->groupBy('l.toSlug')
            ->orderBy('links', 'DESC')
            ->addOrderBy('l.toSlug', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getArrayResult();
    }
}
