<?php

declare(strict_types=1);

namespace Citadel\Wiki\Entity;

use Citadel\Wiki\Repository\WikiRevisionRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\User;

#[ORM\Entity(repositoryClass: WikiRevisionRepository::class)]
#[ORM\Table('wiki_revision')]
#[ORM\Index(name: 'idx_wiki_revision_created', columns: ['created_at'])]
class WikiRevision
{
    use IdentifiableEntityTrait;

    #[ORM\ManyToOne(targetEntity: WikiPage::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private WikiPage $page;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $author;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $summary;

    #[ORM\Column]
    private bool $minor;

    #[ORM\Column]
    private int $size;

    #[ORM\Column]
    private int $sizeDelta;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(WikiPage $page, ?User $author, string $content, ?string $summary, bool $minor, int $size, int $sizeDelta)
    {
        $this->page = $page;
        $this->author = $author;
        $this->content = $content;
        $this->summary = $summary;
        $this->minor = $minor;
        $this->size = $size;
        $this->sizeDelta = $sizeDelta;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getPage(): WikiPage
    {
        return $this->page;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function isMinor(): bool
    {
        return $this->minor;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getSizeDelta(): int
    {
        return $this->sizeDelta;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
