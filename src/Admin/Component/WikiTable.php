<?php

declare(strict_types=1);

namespace Citadel\Wiki\Admin\Component;

use Citadel\Wiki\Entity\Wiki;
use Forumify\Core\Component\Table\AbstractDoctrineTable;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;

#[AsLiveComponent('Wiki\\WikiTable', '@Forumify/components/table/table.html.twig')]
#[IsGranted('wiki.admin.wikis.view')]
class WikiTable extends AbstractDoctrineTable
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $router,
    ) {
    }

    protected function getEntityClass(): string
    {
        return Wiki::class;
    }

    protected function buildTable(): void
    {
        $this
            ->addColumn('title', [
                'field' => 'title',
                'label' => 'wiki.admin.table.title',
                'renderer' => static fn (string $title) => htmlspecialchars($title),
            ])
            ->addColumn('slug', [
                'field' => 'slug',
                'label' => 'wiki.admin.table.slug',
                'renderer' => static fn (string $slug) => '<code>' . htmlspecialchars($slug) . '</code>',
            ])
            ->addColumn('actions', [
                'field' => 'id',
                'label' => '',
                'renderer' => $this->renderActions(...),
                'searchable' => false,
                'sortable' => false,
            ]);
    }

    private function renderActions(int $id, Wiki $wiki): string
    {
        if (!$this->security->isGranted('wiki.admin.wikis.manage')) {
            return '';
        }

        $url = htmlspecialchars($this->router->generate('forumify_admin_acl', (array)$wiki->getACLParameters()));
        $label = htmlspecialchars($this->translator->trans('wiki.admin.permissions'));

        return "<a class='btn-link btn-icon btn-small' href='$url' title='$label'><i class='ph ph-lock'></i></a>"
            . $this->renderAction('wiki_admin_wikis_edit', ['identifier' => $id], 'pencil-simple-line')
            . $this->renderAction('wiki_admin_wikis_delete', ['identifier' => $id], 'x');
    }
}
