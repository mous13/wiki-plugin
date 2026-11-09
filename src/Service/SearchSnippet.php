<?php

declare(strict_types=1);

namespace Citadel\Wiki\Service;

class SearchSnippet
{
    public static function fromMarkdown(string $markdown): string
    {
        $replacements = [
            '/\{\{\s*(?:resource\([^)]*\)|snippet\([^)]*\)|contents(?:\(\s*\))?)\s*\}\}/' => ' ',
            '/^```.*?^```[ \t]*$/ms' => ' ',
            '/^\|.*$/m' => ' ',
            '/!\[[^\]]*\]\([^)]*\)/' => ' ',
            '/\[\[Category:[^\]]*\]\]/i' => ' ',
            '/\[\[:?[^\]|]*\|([^\]]*)\]\]/' => '$1',
            '/\[\[:?([^\]]*)\]\]/' => '$1',
            '/\[([^\]]*)\]\([^)]*\)/' => '$1',
            '/^#{1,6}[ \t]*/m' => '',
            '/[*_`>]/' => '',
            '/\s+/' => ' ',
        ];

        return trim((string)preg_replace(array_keys($replacements), array_values($replacements), $markdown));
    }
}
