<?php

declare(strict_types=1);

namespace Citadel\Wiki\Repository;

use Citadel\Wiki\Entity\Wiki;
use Citadel\Wiki\Entity\WikiLink;
use Citadel\Wiki\Entity\WikiPage;
use Forumify\Core\Repository\AbstractRepository;

/**
 * @extends AbstractRepository<WikiPage>
 */
class WikiPageRepository extends AbstractRepository
{
    private const SEARCH_LIMIT = 50;

    public static function getEntityClass(): string
    {
        return WikiPage::class;
    }

    public function findBySlug(Wiki $wiki, string $slug): ?WikiPage
    {
        return $this->findOneBy(['wiki' => $wiki, 'slug' => $slug]);
    }

    /**
     * @param array<string> $slugs
     * @return array<string>
     */
    public function findExistingSlugs(Wiki $wiki, array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->select('p.slug')
            ->where('p.wiki = :wiki')
            ->andWhere('p.slug IN (:slugs)')
            ->setParameter('wiki', $wiki)
            ->setParameter('slugs', $slugs)
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * @return array<WikiPage>
     */
    public function findList(Wiki $wiki, int $limit, int $offset, bool $protectedOnly = false): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.wiki = :wiki')
            ->setParameter('wiki', $wiki)
            ->orderBy('p.title', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($protectedOnly) {
            $qb->andWhere('p.protected = true');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<WikiPage>
     */
    public function findLinkingTo(Wiki $wiki, string $slug, ?int $limit = null): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin(WikiLink::class, 'l', 'WITH', 'l.fromPage = p')
            ->where('p.wiki = :wiki')
            ->andWhere('l.toSlug = :slug')
            ->setParameter('wiki', $wiki)
            ->setParameter('slug', $slug)
            ->orderBy('p.title', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<WikiPage>
     */
    public function search(Wiki $wiki, string $query): array
    {
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            'SELECT id FROM wiki_page
             WHERE wiki_id = ? AND MATCH(title, content) AGAINST(? IN NATURAL LANGUAGE MODE)
             ORDER BY MATCH(title, content) AGAINST(? IN NATURAL LANGUAGE MODE) DESC, id ASC
             LIMIT ' . self::SEARCH_LIMIT,
            [$wiki->getId(), $query, $query],
        );

        if ($ids === []) {
            return $this->createQueryBuilder('p')
                ->where('p.wiki = :wiki')
                ->andWhere('p.title LIKE :title')
                ->setParameter('wiki', $wiki)
                ->setParameter('title', '%' . addcslashes($query, '%_\\') . '%')
                ->orderBy('p.title', 'ASC')
                ->setMaxResults(self::SEARCH_LIMIT)
                ->getQuery()
                ->getResult();
        }

        $pages = [];
        foreach ($this->findBy(['id' => $ids]) as $page) {
            $pages[$page->getId()] = $page;
        }

        return array_values(array_filter(array_map(
            static fn (mixed $id): ?WikiPage => $pages[(int)$id] ?? null,
            $ids,
        )));
    }
}
