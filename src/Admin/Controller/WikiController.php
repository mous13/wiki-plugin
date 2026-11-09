<?php

declare(strict_types=1);

namespace Citadel\Wiki\Admin\Controller;

use Citadel\Wiki\Admin\Form\WikiType;
use Citadel\Wiki\Entity\Wiki;
use Forumify\Admin\Crud\AbstractCrudController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractCrudController<Wiki>
 */
#[Route('/wikis', 'wikis')]
#[IsGranted('wiki.admin.wikis.view')]
class WikiController extends AbstractCrudController
{
    protected ?string $permissionView = 'wiki.admin.wikis.view';
    protected ?string $permissionCreate = 'wiki.admin.wikis.manage';
    protected ?string $permissionEdit = 'wiki.admin.wikis.manage';
    protected ?string $permissionDelete = 'wiki.admin.wikis.manage';

    protected function getTranslationPrefix(): string
    {
        return 'wiki.' . parent::getTranslationPrefix();
    }

    protected function getEntityClass(): string
    {
        return Wiki::class;
    }

    protected function getTableName(): string
    {
        return 'Wiki\\WikiTable';
    }

    protected function getForm(?object $data): FormInterface
    {
        return $this->createForm(WikiType::class, $data);
    }
}
