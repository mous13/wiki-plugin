<?php

declare(strict_types=1);

namespace Citadel\Wiki\Service;

use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\DiffOnlyOutputBuilder;

class DiffService
{
    /**
     * @return array<int, array{type: string, old: ?int, new: ?int, text: string}>
     */
    public function build(string $from, string $to, int $context = 3): array
    {
        $differ = new Differ(new DiffOnlyOutputBuilder(''));
        $old = 0;
        $new = 0;
        $rows = [];
        foreach ($differ->diffToArray($from, $to) as [$text, $type]) {
            $text = rtrim((string)$text, "\n");
            $row = match ($type) {
                Differ::OLD => ['type' => 'same', 'old' => ++$old, 'new' => ++$new, 'text' => $text],
                Differ::REMOVED => ['type' => 'removed', 'old' => ++$old, 'new' => null, 'text' => $text],
                Differ::ADDED => ['type' => 'added', 'old' => null, 'new' => ++$new, 'text' => $text],
                default => null,
            };

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $this->collapse($rows, $context);
    }

    /**
     * @param array<int, array{type: string, old: ?int, new: ?int, text: string}> $rows
     * @return array<int, array{type: string, old: ?int, new: ?int, text: string}>
     */
    private function collapse(array $rows, int $context): array
    {
        $keep = [];
        foreach ($rows as $index => $row) {
            if ($row['type'] === 'same') {
                continue;
            }

            $end = min(count($rows) - 1, $index + $context);
            for ($i = max(0, $index - $context); $i <= $end; $i++) {
                $keep[$i] = true;
            }
        }

        $collapsed = [];
        $skipping = false;
        foreach ($rows as $index => $row) {
            if (isset($keep[$index])) {
                $collapsed[] = $row;
                $skipping = false;
                continue;
            }

            if ($skipping) {
                continue;
            }

            $collapsed[] = ['type' => 'gap', 'old' => null, 'new' => null, 'text' => ''];
            $skipping = true;
        }

        return $collapsed;
    }
}
