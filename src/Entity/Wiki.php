<?php

declare(strict_types=1);

namespace Citadel\Wiki\Entity;

use Citadel\Wiki\Repository\WikiRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\AccessControlledEntityInterface;
use Forumify\Core\Entity\ACLParameters;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\SluggableEntityTrait;
use Forumify\Core\Entity\TimestampableEntityTrait;

#[ORM\Entity(repositoryClass: WikiRepository::class)]
#[ORM\Table('wiki')]
class Wiki implements AccessControlledEntityInterface
{
    use IdentifiableEntityTrait;
    use SluggableEntityTrait;
    use TimestampableEntityTrait;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    public function __toString(): string
    {
        return $this->title;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getACLPermissions(): array
    {
        return ['view', 'edit', 'moderate'];
    }

    public function getACLParameters(): ACLParameters
    {
        return new ACLParameters(self::class, (string)$this->getId(), 'wiki_admin_wikis_list');
    }
}
