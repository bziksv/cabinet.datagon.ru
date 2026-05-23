<?php

namespace App\Exports\TextAnalyzer;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TextAnalyzerWorkbookExport implements WithMultipleSheets
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

    public function sheets(): array
    {
        return [
            new TextAnalyzerSummarySheet($this->response, $this->request, $this->meta),
            new TextAnalyzerWordsSheet($this->response),
            new TextAnalyzerPhrasesSheet($this->response),
        ];
    }
}
