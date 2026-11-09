<?php

declare(strict_types=1);

namespace Citadel\Wiki\Admin\MenuBuilder;

use Forumify\Admin\MenuBuilder\AdminMenuBuilderInterface;
use Forumify\Core\MenuBuilder\Menu;
use Forumify\Core\MenuBuilder\MenuItem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AdminMenuBuilder implements AdminMenuBuilderInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function build(Menu $menu): void
    {
        $menu->addItem(new MenuItem('Wikis', $this->urlGenerator->generate('wiki_admin_wikis_list'), [
            'icon' => 'ph ph-books',
            'permission' => 'wiki.admin.wikis.view',
        ]));
    }
}
