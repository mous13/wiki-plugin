<?php

declare(strict_types=1);

namespace Citadel\Wiki\Entity;

use Citadel\Wiki\Repository\WikiLinkRepository;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;

#[ORM\Entity(repositoryClass: WikiLinkRepository::class)]
#[ORM\Table('wiki_link')]
#[ORM\UniqueConstraint(name: 'uniq_wiki_link', columns: ['from_page_id', 'to_slug'])]
#[ORM\Index(name: 'idx_wiki_link_to_slug', columns: ['to_slug'])]
class WikiLink
{
    use IdentifiableEntityTrait;

    #[ORM\ManyToOne(targetEntity: WikiPage::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private WikiPage $fromPage;

    #[ORM\Column(length: 255)]
    private string $toSlug;

    public function __construct(WikiPage $fromPage, string $toSlug)
    {
        $this->fromPage = $fromPage;
        $this->toSlug = $toSlug;
    }

    public function getFromPage(): WikiPage
    {
        return $this->fromPage;
    }

    public function getToSlug(): string
    {
        return $this->toSlug;
    }
}
