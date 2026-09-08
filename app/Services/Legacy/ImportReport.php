<?php

namespace App\Services\Legacy;

/**
 * Running tally for one import run. Kept separate from the command so the
 * numbers can be asserted in tests and rendered by any caller.
 */
class ImportReport
{
    /** @var array<string, array{read:int, imported:int, updated:int, skipped:int, notes:list<string>}> */
    private array $steps = [];

    public function start(string $step): void
    {
        $this->steps[$step] ??= ['read' => 0, 'imported' => 0, 'updated' => 0, 'skipped' => 0, 'notes' => []];
    }

    public function read(string $step, int $n = 1): void
    {
        $this->start($step);
        $this->steps[$step]['read'] += $n;
    }

    public function imported(string $step, int $n = 1): void
    {
        $this->start($step);
        $this->steps[$step]['imported'] += $n;
    }

    public function updated(string $step, int $n = 1): void
    {
        $this->start($step);
        $this->steps[$step]['updated'] += $n;
    }

    public function skipped(string $step, string $reason, int $n = 1): void
    {
        $this->start($step);
        $this->steps[$step]['skipped'] += $n;

        // Keep the note list bounded; a broken column would otherwise produce
        // one line per row across a table with hundreds of thousands of rows.
        if (count($this->steps[$step]['notes']) < 20 && ! in_array($reason, $this->steps[$step]['notes'], true)) {
            $this->steps[$step]['notes'][] = $reason;
        }
    }

    /** @return array<string, array{read:int, imported:int, updated:int, skipped:int, notes:list<string>}> */
    public function all(): array
    {
        return $this->steps;
    }

    /** @return list<array{0:string,1:string,2:string,3:string,4:string}> */
    public function rows(): array
    {
        $rows = [];

        foreach ($this->steps as $name => $s) {
            $rows[] = [
                $name,
                number_format($s['read']),
                number_format($s['imported']),
                number_format($s['updated']),
                $s['skipped'] > 0 ? number_format($s['skipped']) : '—',
            ];
        }

        return $rows;
    }

    public function totalSkipped(): int
    {
        return array_sum(array_column($this->steps, 'skipped'));
    }

    /** @return array<string, list<string>> */
    public function notes(): array
    {
        $out = [];

        foreach ($this->steps as $name => $s) {
            if ($s['notes']) {
                $out[$name] = $s['notes'];
            }
        }

        return $out;
    }
}
