<?php

namespace App\Exports\TextAnalyzer;

use App\TextAnalyzer;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class TextAnalyzerSummarySheet implements FromCollection, ShouldAutoSize, WithTitle
{
    protected $response;
    protected $request;
    protected $meta;

    public function __construct(array $response, array $request, array $meta)
    {
        $this->response = $response;
        $this->request = $request;
        $this->meta = $meta;
    }

    public function title(): string
    {
        return __('Summary');
    }

    public function collection(): Collection
    {
        $general = $this->response['general'] ?? [];
        $rows = [
            [__('Text analyzer report'), ''],
            [__('Generated at'), $this->meta['generated_at'] ?? ''],
            [__('Source'), $this->meta['source_label'] ?? ''],
            ['', ''],
            [__('Number of words'), $general['countWords'] ?? 0],
            [__('Number of characters'), $general['textLength'] ?? 0],
            [__('Number of spaces'), $general['countSpaces'] ?? 0],
            [__('Number of characters without spaces'), $general['lengthWithOutSpaces'] ?? 0],
            ['', ''],
            [__('Track the text in the noindex tag'), !empty($this->request['noIndex']) ? __('Yes') : __('No')],
            [__('Track words in the alt, title, and data-text attributes'), !empty($this->request['hiddenText']) ? __('Yes') : __('No')],
            [__('Exclude conjunctions, prepositions, pronouns'), TextAnalyzer::shouldExcludeConjunctionsPrepositionsPronouns($this->request) ? __('Yes') : __('No')],
            [__('Exclude'), !empty($this->request['removeWords']) ? ($this->request['listWords'] ?? __('Yes')) : __('No')],
        ];

        return collect($rows);
    }
}
