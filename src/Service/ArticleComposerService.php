<?php

declare(strict_types=1);

namespace Citadel\Wiki\Service;

class ArticleComposerService
{
    private const MIN_CONTENTS_ENTRIES = 3;

    /**
     * @param array<int, array{level: int, id: string, text: string}> $toc
     * @return array{infobox: ?string, lead: string, body: string, contents: array<int, array{number: string, depth: int, id: string, text: string}>, showContents: bool}
     */
    public function compose(string $html, array $toc): array
    {
        $infobox = null;
        if (preg_match('#<aside class="card wiki-infobox">.*?</aside>#s', $html, $match) === 1) {
            $infobox = $match[0];
            $html = (string)preg_replace('#<aside class="card wiki-infobox">.*?</aside>#s', '', $html, 1);
        }

        $marker = strpos($html, ContentTags::CONTENTS_MARKER);
        $html = str_replace(ContentTags::CONTENTS_MARKER, '', $html);
        $split = $marker !== false ? $marker : $this->firstHeading($html);

        return [
            'infobox' => $infobox,
            'lead' => substr($html, 0, $split),
            'body' => substr($html, $split),
            'contents' => $this->number($toc),
            'showContents' => count($toc) >= ($marker !== false ? 1 : self::MIN_CONTENTS_ENTRIES),
        ];
    }

    private function firstHeading(string $html): int
    {
        return preg_match('#<h[1-6][\s>]#', $html, $heading, PREG_OFFSET_CAPTURE) === 1 ? $heading[0][1] : strlen($html);
    }

    /**
     * @param array<int, array{level: int, id: string, text: string}> $toc
     * @return array<int, array{number: string, depth: int, id: string, text: string}>
     */
    private function number(array $toc): array
    {
        $stack = [];
        $entries = [];
        foreach ($toc as $entry) {
            while ($stack !== [] && end($stack)['level'] > $entry['level']) {
                array_pop($stack);
            }

            if ($stack !== [] && end($stack)['level'] === $entry['level']) {
                $stack[array_key_last($stack)]['count']++;
            } else {
                $stack[] = ['level' => $entry['level'], 'count' => 1];
            }

            $entries[] = [
                'number' => implode('.', array_column($stack, 'count')),
                'depth' => count($stack) - 1,
                'id' => $entry['id'],
                'text' => $entry['text'],
            ];
        }

        return $entries;
    }
}
