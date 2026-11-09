<?php

declare(strict_types=1);

namespace Citadel\Wiki\Component;

use Citadel\Wiki\Entity\Wiki;
use Citadel\Wiki\Form\DTO\WikiPageData;
use Citadel\Wiki\Form\WikiPageType;
use Citadel\Wiki\Markdown\RenderedMarkdown;
use Citadel\Wiki\Markdown\WikiMarkdownRenderer;
use Citadel\Wiki\Service\ContentExpanderService;
use Citadel\Wiki\Service\ContentTags;
use Citadel\Wiki\Service\WikiPageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('Wiki\\Editor', '@CitadelWikiPlugin/frontend/components/editor.html.twig')]
#[IsGranted('ROLE_USER')]
class Editor extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public Wiki $wiki;

    #[LiveProp]
    public string $slug = '';

    #[LiveProp]
    public ?int $baseRevisionId = null;

    private ?RenderedMarkdown $rendered = null;

    public function __construct(
        private readonly WikiMarkdownRenderer $renderer,
        private readonly ContentExpanderService $expanderService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getMaxContentBytes(): int
    {
        return WikiPageService::MAX_CONTENT_BYTES;
    }

    public function getPreview(): ?RenderedMarkdown
    {
        $content = $this->formValues['content'] ?? '';
        if (!is_string($content) || strlen($content) > WikiPageService::MAX_CONTENT_BYTES) {
            return null;
        }

        return $this->rendered ??= $this->renderer->render($this->wiki, $content);
    }

    public function getPreviewHtml(): string
    {
        $notice = '<div class="alert-info text-small mb-2">' . htmlspecialchars($this->translator->trans('wiki.edit.contents_here')) . '</div>';

        return str_replace(ContentTags::CONTENTS_MARKER, $notice, $this->expanderService->expand($this->getPreview()->html ?? ''));
    }

    /**
     * @return array<string>
     */
    public function getUnknownTags(): array
    {
        return $this->expanderService->findUnknown($this->getPreview()->html ?? '');
    }

    /**
     * @return FormInterface<WikiPageData|null>
     */
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(WikiPageType::class);
    }
}
