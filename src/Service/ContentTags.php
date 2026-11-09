<?php

declare(strict_types=1);

namespace Citadel\Wiki\Service;

class ContentTags
{
    public const RESOURCE_PREFIX = 'wiki-resource:';
    public const SNIPPET_PREFIX = 'wiki-snippet:';
    public const CONTENTS_MARKER = '<p>wiki-contents-here</p>';

    private const RESOURCE_TAG = '\{\{\s*resource\(\s*[\'"](?<resource>[\w.-]+)[\'"]\s*\)\s*\}\}';

    private const PATTERN = '/(?<infobox>^(?<infence>`{3,}|~{3,})[ \t]*infobox\b[^\n]*\n[\s\S]*?^\k<infence>[ \t]*$)'
        . '|(?<code>^(?<fence>`{3,}|~{3,})[^\n]*\n[\s\S]*?^\k<fence>[`~]*[ \t]*$|`[^`\n]+`)'
        . '|^[ \t]*\{\{\s*snippet\(\s*[\'"](?<snippet>[\w.-]+)[\'"]\s*\)\s*\}\}[ \t]*$'
        . '|^[ \t]*(?<contents>\{\{\s*contents(?:\(\s*\))?\s*\}\})[ \t]*$'
        . '|' . self::RESOURCE_TAG . '/m';

    public static function tokenize(string $markdown): string
    {
        return (string)preg_replace_callback(self::PATTERN, static function (array $match): string {
            if ($match['infobox'] !== '') {
                return (string)preg_replace('/' . self::RESOURCE_TAG . '/', self::RESOURCE_PREFIX . '$1', $match['infobox']);
            }

            if (($match['snippet'] ?? '') !== '') {
                return "\n\n" . self::SNIPPET_PREFIX . $match['snippet'] . "\n\n";
            }

            if (($match['contents'] ?? '') !== '') {
                return "\n\nwiki-contents-here\n\n";
            }

            if (($match['resource'] ?? '') !== '') {
                return self::RESOURCE_PREFIX . $match['resource'];
            }

            return $match[0];
        }, $markdown);
    }
}
