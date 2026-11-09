<?php

declare(strict_types=1);

namespace Citadel\Wiki\Service;

use Symfony\Component\String\Slugger\AsciiSlugger;

class PageSlugger
{
    public const CATEGORY_PREFIX = 'category:';
    public const ROUTE_REQUIREMENT = '[a-z0-9]+(?:[:-][a-z0-9]+)*';
    public const MAX_LENGTH = 200;

    public static function slug(string $title): string
    {
        $title = trim($title);
        $prefix = '';
        if (preg_match('/^category\s*:\s*(.+)$/iu', $title, $matches) === 1) {
            $prefix = self::CATEGORY_PREFIX;
            $title = $matches[1];
        }

        $slug = (new AsciiSlugger())->slug($title)->lower()->truncate(self::MAX_LENGTH)->trimEnd('-')->toString();

        return $slug === '' ? '' : $prefix . $slug;
    }

    public static function isCategory(string $slug): bool
    {
        return str_starts_with($slug, self::CATEGORY_PREFIX);
    }

    public static function humanize(string $slug): string
    {
        $isCategory = self::isCategory($slug);
        $name = $isCategory ? substr($slug, strlen(self::CATEGORY_PREFIX)) : $slug;
        $name = ucfirst(str_replace('-', ' ', $name));

        return $isCategory ? 'Category:' . $name : $name;
    }

    public static function displayTitle(string $title): string
    {
        return (string)preg_replace('/^category\s*:\s*/iu', '', $title);
    }

    public static function categoryLabel(string $slug): string
    {
        return ucfirst(str_replace('-', ' ', substr($slug, strlen(self::CATEGORY_PREFIX))));
    }
}
