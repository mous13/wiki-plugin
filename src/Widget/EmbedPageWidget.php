<?php

declare(strict_types=1);

namespace Citadel\Wiki\Widget;

use Citadel\Wiki\Repository\WikiPageRepository;
use Citadel\Wiki\Repository\WikiRepository;
use Citadel\Wiki\Service\ContentExpanderService;
use Citadel\Wiki\Service\ContentTags;
use Citadel\Wiki\Service\PageSlugger;
use Citadel\Wiki\Service\WikiAccessService;
use Forumify\Cms\Widget\AbstractWidget;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;

class EmbedPageWidget extends AbstractWidget
{
    public function __construct(
        private readonly WikiRepository $wikiRepository,
        private readonly WikiPageRepository $pageRepository,
        private readonly WikiAccessService $accessService,
        private readonly ContentExpanderService $contentExpanderService,
    ) {
    }

    public function getName(): string
    {
        return 'wiki.embed_page';
    }

    public function getCategory(): string
    {
        return 'wiki';
    }

    public function getPreview(): string
    {
        return '<div class="box"><h3>Plasma Rifle</h3><p class="mb-0">An embedded wiki article.</p></div>';
    }

    public function getTemplate(): string
    {
        return '@CitadelWikiPlugin/frontend/widgets/embed_page.html.twig';
    }

    public function getTemplateContext(array $settings): array
    {
        $wiki = isset($settings['wiki']) ? $this->wikiRepository->find($settings['wiki']) : null;
        if ($wiki === null || !$this->accessService->canView($wiki)) {
            return [];
        }

        $page = $this->pageRepository->findBySlug($wiki, PageSlugger::slug((string)($settings['page'] ?? '')));

        return ['page' => $page, 'html' => $page !== null ? str_replace(ContentTags::CONTENTS_MARKER, '', $this->contentExpanderService->expand($page->getHtml())) : ''];
    }

    public function getSettingsForm(array $data = []): ?FormInterface
    {
        return $this->createForm($data)
            ->add('wiki', ChoiceType::class, ['choices' => WikiChoices::from($this->wikiRepository)])
            ->add('page', TextType::class, ['help' => 'wiki.widget.embed_page_help'])
            ->getForm();
    }
}
