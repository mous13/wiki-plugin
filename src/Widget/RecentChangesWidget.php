<?php

declare(strict_types=1);

namespace Citadel\Wiki\Widget;

use Citadel\Wiki\Repository\WikiRepository;
use Citadel\Wiki\Repository\WikiRevisionRepository;
use Citadel\Wiki\Service\WikiAccessService;
use Forumify\Cms\Widget\AbstractWidget;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints\Range;

class RecentChangesWidget extends AbstractWidget
{
    public function __construct(
        private readonly WikiRepository $wikiRepository,
        private readonly WikiRevisionRepository $revisionRepository,
        private readonly WikiAccessService $accessService,
    ) {
    }

    public function getName(): string
    {
        return 'wiki.recent_changes';
    }

    public function getCategory(): string
    {
        return 'wiki';
    }

    public function getPreview(): string
    {
        return '<div class="box"><p class="text-bold">Plasma Rifle</p><p class="text-small mb-0">Edited just now</p></div>';
    }

    public function getTemplate(): string
    {
        return '@CitadelWikiPlugin/frontend/widgets/recent_changes.html.twig';
    }

    public function getTemplateContext(array $settings): array
    {
        $wiki = isset($settings['wiki']) ? $this->wikiRepository->find($settings['wiki']) : null;
        if ($wiki === null || !$this->accessService->canView($wiki)) {
            return [];
        }

        return [
            'wiki' => $wiki,
            'revisions' => $this->revisionRepository->findRecent($wiki, max(1, min(50, (int)($settings['count'] ?? 5)))),
        ];
    }

    public function getSettingsForm(array $data = []): ?FormInterface
    {
        $data['count'] = (int)($data['count'] ?? 5);

        return $this->createForm($data)
            ->add('wiki', ChoiceType::class, ['choices' => WikiChoices::from($this->wikiRepository)])
            ->add('count', IntegerType::class, ['constraints' => [new Range(min: 1, max: 50)]])
            ->getForm();
    }
}
