<?php

namespace App\Exports\TextAnalyzer;

use App\Support\TextAnalyzerPdfBranding;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class TextAnalyzerReportPdfExport implements FromView
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

    public function view(): View
    {
        $graph = $this->response['graph'] ?? [];
        $clouds = $this->response['clouds'] ?? ['text' => [], 'links' => [], 'both' => []];

        return view('text-analyse.export.report-pdf', array_merge([
            'response' => $this->response,
            'request' => $this->request,
            'meta' => $this->meta,
            'zipfRows' => TextAnalyzerPdfBranding::zipfTableRows($graph),
            'cloudText' => TextAnalyzerPdfBranding::cloudRowsForPdf($clouds['text'] ?? []),
            'cloudLinks' => TextAnalyzerPdfBranding::cloudRowsForPdf($clouds['links'] ?? []),
            'cloudBoth' => TextAnalyzerPdfBranding::cloudRowsForPdf($clouds['both'] ?? []),
        ], TextAnalyzerPdfBranding::viewData()));
    }
}
