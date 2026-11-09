<?php

declare(strict_types=1);

namespace Citadel\Wiki;

use Forumify\Plugin\AbstractForumifyPlugin;
use Forumify\Plugin\PluginMetadata;

class CitadelWikiPlugin extends AbstractForumifyPlugin
{
    public function getPluginMetadata(): PluginMetadata
    {
        return new PluginMetadata(
            'Wiki',
            'Citadel',
            'A lightweight Markdown wiki with revision history, wiki links and search.',
            null,
            'wiki_admin_wikis_list',
        );
    }

    public function getPermissions(): array
    {
        return [
            'admin' => [
                'wikis' => [
                    'view',
                    'manage',
                ],
            ],
        ];
    }
}
