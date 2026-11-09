<?php

declare(strict_types=1);

namespace Citadel\Wiki\Widget;

use Citadel\Wiki\Repository\WikiRepository;

class WikiChoices
{
    /**
     * @return array<string, int>
     */
    public static function from(WikiRepository $repository): array
    {
        $choices = [];
        foreach ($repository->findBy([], ['title' => 'ASC']) as $wiki) {
            $choices[$wiki->getTitle()] = $wiki->getId();
        }

        return $choices;
    }
}
