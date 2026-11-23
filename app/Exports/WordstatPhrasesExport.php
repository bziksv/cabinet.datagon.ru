<?php

namespace App\Exports;

use App\Wordstat\WordstatGroup;
use App\Wordstat\WordstatPhrase;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class WordstatPhrasesExport implements FromArray, WithHeadings
{
    private int $projectId;
    private ?int $groupId;

    public function __construct(int $projectId, ?int $groupId = null)
    {
        $this->projectId = $projectId;
        $this->groupId = $groupId;
    }

    public function headings(): array
    {
        return ['Группа', 'Фраза', 'Частотность', 'Точная частотность', 'Источник'];
    }

    public function array(): array
    {
        $groups = WordstatGroup::query()->where('project_id', $this->projectId)->pluck('name', 'id');
        $sources = ['left' => 'Левая колонка', 'right' => 'Правая колонка', 'manual' => 'Своя фраза'];
        $query = WordstatPhrase::query()->where('project_id', $this->projectId)->where('status', WordstatPhrase::STATUS_ACTIVE);
        if ($this->groupId) {
            $query->where('group_id', $this->groupId);
        }

        return $query->orderBy('group_id')->orderByDesc('freq')->get()->map(fn (WordstatPhrase $p) => [
            (string) ($groups[$p->group_id] ?? ''),
            $p->phrase,
            $p->freq,
            $p->freq_exact,
            $sources[$p->source] ?? $p->source,
        ])->all();
    }
}
