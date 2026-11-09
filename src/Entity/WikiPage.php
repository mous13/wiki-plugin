<?php

declare(strict_types=1);

namespace Citadel\Wiki\Entity;

use Citadel\Wiki\Repository\WikiPageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;

#[ORM\Entity(repositoryClass: WikiPageRepository::class)]
#[ORM\Table('wiki_page')]
#[ORM\UniqueConstraint(name: 'uniq_wiki_page_slug', columns: ['wiki_id', 'slug'])]
#[ORM\Index(name: 'ft_wiki_page_search', columns: ['title', 'content'], flags: ['fulltext'])]
class WikiPage
{
    use IdentifiableEntityTrait;

    #[ORM\ManyToOne(targetEntity: Wiki::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Wiki $wiki;

    #[ORM\Column(length: 255)]
    private string $slug;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?WikiPage $parent = null;

    #[ORM\ManyToOne(targetEntity: WikiRevision::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?WikiRevision $currentRevision = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $content = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $html = '';

    /** @var array<int, array{level: int, id: string, text: string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $toc = [];

    #[ORM\Column(name: 'is_protected', options: ['default' => false])]
    private bool $protected = false;

    public function __construct(Wiki $wiki, string $slug)
    {
        $this->wiki = $wiki;
        $this->slug = $slug;
    }

    public function getWiki(): Wiki
    {
        return $this->wiki;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getParent(): ?WikiPage
    {
        return $this->parent;
    }

    public function setParent(?WikiPage $parent): void
    {
        $this->parent = $parent;
    }

    public function getCurrentRevision(): ?WikiRevision
    {
        return $this->currentRevision;
    }

    public function setCurrentRevision(?WikiRevision $currentRevision): void
    {
        $this->currentRevision = $currentRevision;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getHtml(): string
    {
        return $this->html;
    }

    public function setHtml(string $html): void
    {
        $this->html = $html;
    }

    /**
     * @return array<int, array{level: int, id: string, text: string}>
     */
    public function getToc(): array
    {
        return $this->toc;
    }

    /**
     * @param array<int, array{level: int, id: string, text: string}> $toc
     */
    public function setToc(array $toc): void
    {
        $this->toc = $toc;
    }

    public function isProtected(): bool
    {
        return $this->protected;
    }

    public function setProtected(bool $protected): void
    {
        $this->protected = $protected;
    }
}
