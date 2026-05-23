<?php

namespace App\Exports\TextAnalyzer;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class TextAnalyzerWordsSheet implements FromCollection, ShouldAutoSize, WithTitle
{
    protected $response;

    public function __construct(array $response)
    {
        $this->response = $response;
    }

    public function title(): string
    {
        return __('General word analysis');
    }

    public function collection(): Collection
    {
        $rows = [[
            __('Word'),
            __('Density'),
            __('Common area'),
            __('Text Area'),
            __('Link Zone'),
        ]];

        foreach ($this->response['totalWords'] ?? [] as $word) {
            $rows[] = [
                $word['text'] ?? '',
                $word['density'] ?? '',
                $word['total'] ?? '',
                $word['inText'] ?? '',
                $word['inLink'] ?? '',
            ];
        }

        return collect($rows);
    }
}
