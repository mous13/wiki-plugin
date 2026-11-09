<?php

declare(strict_types=1);

namespace Citadel\Wiki\Form\DTO;

use Citadel\Wiki\Entity\WikiPage;
use Citadel\Wiki\Service\WikiPageService;
use Symfony\Component\Validator\Constraints as Assert;

class WikiPageData
{
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 255)]
    public string $title = '';

    public ?string $parent = null;

    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: WikiPageService::MAX_CONTENT_BYTES, charset: '8bit')]
    public string $content = '';

    #[Assert\Length(max: 255)]
    public ?string $summary = null;

    public bool $minor = false;

    public ?string $base = null;

    public static function fromPage(?WikiPage $page, string $newTitle): self
    {
        $data = new self();
        $data->title = $page?->getTitle() ?? $newTitle;
        $data->parent = $page?->getParent()?->getSlug();
        $data->content = $page?->getContent() ?? '';
        $data->base = self::baseOf($page);

        return $data;
    }

    public static function baseOf(?WikiPage $page): ?string
    {
        $revision = $page?->getCurrentRevision();

        return $revision !== null ? (string)$revision->getId() : null;
    }

    public function getBaseRevisionId(): ?int
    {
        return $this->base !== null && ctype_digit($this->base) ? (int)$this->base : null;
    }
}
