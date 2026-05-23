<?php

namespace App\Exports\TextAnalyzer;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class TextAnalyzerPhrasesSheet implements FromCollection, ShouldAutoSize, WithTitle
{
    protected $response;

    public function __construct(array $response)
    {
        $this->response = $response;
    }

    public function title(): string
    {
        return __('Phrases of 2 words');
    }

    public function collection(): Collection
    {
        $rows = [[
            __('Phrase'),
            __('Repetitions'),
            __('Density'),
        ]];

        foreach ($this->response['phrases'] ?? [] as $phrase) {
            $rows[] = [
                trim($phrase['phrase'] ?? ''),
                $phrase['count'] ?? '',
                $phrase['density'] ?? '',
            ];
        }

        return collect($rows);
    }
}
