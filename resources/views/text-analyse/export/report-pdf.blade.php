<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Text analyzer report') }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.5pt;
            color: #1e293b;
            line-height: 1.45;
            margin: 0;
            padding: 0;
        }

        .cover-band {
            background-color: #1e3f9e;
            color: #ffffff;
            padding: 16px 18px 14px 18px;
            margin: 0 0 16px 0;
            border-radius: 4px;
        }

        .cover-top {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .cover-top td {
            vertical-align: middle;
            padding: 0;
            border: none;
        }

        .cover-logo img {
            height: 40px;
            width: auto;
            display: block;
        }

        .cover-brand {
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 0.02em;
            margin: 0;
        }

        .cover-tagline {
            font-size: 8.5pt;
            color: #c7ddff;
            margin: 2px 0 0 0;
        }

        .cover-report-title {
            font-size: 17pt;
            font-weight: bold;
            margin: 0 0 4px 0;
            line-height: 1.2;
        }

        .cover-report-lead {
            font-size: 9pt;
            color: #dbeafe;
            margin: 0;
            line-height: 1.4;
        }

        .meta-card {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            border: 1px solid #dbeafe;
            background: #f8fafc;
        }

        .meta-card td {
            padding: 7px 10px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
            font-size: 9pt;
        }

        .meta-card tr:last-child td {
            border-bottom: none;
        }

        .meta-card .label {
            width: 28%;
            color: #64748b;
            font-weight: bold;
        }

        .kpi-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin: 0 0 14px 0;
        }

        .kpi-grid td {
            width: 25%;
            background: #ffffff;
            border: 1px solid #bfdbfe;
            border-top: 4px solid #2f5de0;
            padding: 11px 8px;
            text-align: center;
            vertical-align: top;
            border-radius: 3px;
        }

        .kpi-value {
            display: block;
            font-size: 15pt;
            font-weight: bold;
            color: #1e3f9e;
            margin-bottom: 3px;
            line-height: 1.1;
        }

        .kpi-label {
            display: block;
            font-size: 7.5pt;
            color: #64748b;
            line-height: 1.25;
        }

        .section {
            margin: 0 0 12px 0;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 11pt;
            font-weight: bold;
            color: #1e3f9e;
            margin: 0 0 8px 0;
            padding: 6px 10px;
            background: #eff6ff;
            border-left: 4px solid #2f5de0;
        }

        .section-lead {
            font-size: 8.5pt;
            color: #64748b;
            margin: -4px 0 8px 0;
        }

        .settings-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .settings-grid td {
            width: 50%;
            padding: 5px 8px 5px 0;
            font-size: 8.5pt;
            vertical-align: top;
        }

        .settings-grid .flag {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 8pt;
            font-weight: bold;
        }

        .flag-yes {
            background: #dcfce7;
            color: #166534;
        }

        .flag-no {
            background: #f1f5f9;
            color: #64748b;
        }

        .cloud-zone {
            margin-bottom: 10px;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            background: #fafbfc;
            border-radius: 3px;
        }

        .cloud-zone-title {
            font-size: 9pt;
            font-weight: bold;
            color: #334155;
            margin: 0 0 6px 0;
        }

        .cloud-word {
            display: inline-block;
            margin: 2px 4px 2px 0;
            padding: 2px 6px;
            border-radius: 3px;
            background: #e0f2fe;
            color: #0c4a6e;
            vertical-align: middle;
        }

        .cloud-word--t5 { font-size: 11pt; font-weight: bold; background: #2f5de0; color: #fff; }
        .cloud-word--t4 { font-size: 10pt; font-weight: bold; }
        .cloud-word--t3 { font-size: 9.5pt; }
        .cloud-word--t2 { font-size: 9pt; color: #475569; background: #f1f5f9; }
        .cloud-word--t1 { font-size: 8.5pt; color: #64748b; background: #f8fafc; }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .data-table th {
            background: #1e3f9e;
            color: #ffffff;
            font-size: 8.5pt;
            font-weight: bold;
            padding: 6px 5px;
            border: 1px solid #1e3f9e;
            text-align: left;
        }

        .data-table th.num {
            text-align: right;
        }

        .data-table td {
            padding: 5px;
            border: 1px solid #e2e8f0;
            font-size: 8.5pt;
        }

        .data-table td.num {
            text-align: right;
            font-family: DejaVu Sans Mono, monospace;
        }

        .data-table tr:nth-child(even) td {
            background: #f8fafc;
        }

        .delta-pos { color: #b45309; }
        .delta-neg { color: #15803d; }
        .delta-zero { color: #64748b; }

        .report-footer {
            margin-top: 16px;
            padding: 10px 12px;
            border-top: 2px solid #2f5de0;
            background: #f8fafc;
        }

        .report-footer table td {
            font-size: 8pt;
            color: #64748b;
            vertical-align: middle;
        }
    </style>
</head>
<body>
@php
    $general = $response['general'] ?? [];
    $words = array_slice($response['totalWords'] ?? [], 0, 60);
    $phrases = array_slice($response['phrases'] ?? [], 0, 40);
    $yes = __('Yes');
    $no = __('No');
@endphp

<div class="cover-band">
    <table class="cover-top">
        <tr>
            <td width="42%" class="cover-logo">
                <img src="{{ $logoFullPath }}" alt="{{ $brandName }}">
            </td>
            <td width="58%" align="right">
                <p class="cover-brand">{{ $brandName }}</p>
                <p class="cover-tagline">{{ $brandTagline }}</p>
            </td>
        </tr>
    </table>
    <p class="cover-report-title">{{ __('Text analyzer report') }}</p>
    <p class="cover-report-lead">{{ __('Word statistics, Zipf distribution, phrase analysis and word clouds for page text or URL.') }}</p>
</div>

<table class="meta-card">
    <tr>
        <td class="label">{{ __('Generated at') }}</td>
        <td>{{ $meta['generated_at'] ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">{{ __('Source') }}</td>
        <td>{{ $meta['source_label'] ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">{{ __('Text Analyse') }}</td>
        <td>{{ $brandName }} · v{{ $meta['version'] ?? '1.0' }} · {{ $brandSiteHost }}</td>
    </tr>
</table>

<table class="kpi-grid">
    <tr>
        <td>
            <span class="kpi-value">{{ number_format($general['countWords'] ?? 0, 0, ',', ' ') }}</span>
            <span class="kpi-label">{{ __('Number of words') }}</span>
        </td>
        <td>
            <span class="kpi-value">{{ number_format($general['textLength'] ?? 0, 0, ',', ' ') }}</span>
            <span class="kpi-label">{{ __('Number of characters') }}</span>
        </td>
        <td>
            <span class="kpi-value">{{ number_format($general['countSpaces'] ?? 0, 0, ',', ' ') }}</span>
            <span class="kpi-label">{{ __('Number of spaces') }}</span>
        </td>
        <td>
            <span class="kpi-value">{{ number_format($general['lengthWithOutSpaces'] ?? 0, 0, ',', ' ') }}</span>
            <span class="kpi-label">{{ __('Number of characters without spaces') }}</span>
        </td>
    </tr>
</table>

<div class="section">
    <div class="section-title">{{ __('Analysis settings') }}</div>
    <table class="settings-grid">
        <tr>
            <td>
                {{ __('Track the text in the noindex tag') }}:
                <span class="flag {{ !empty($request['noIndex']) ? 'flag-yes' : 'flag-no' }}">{{ !empty($request['noIndex']) ? $yes : $no }}</span>
            </td>
            <td>
                {{ __('Track words in the alt, title, and data-text attributes') }}:
                <span class="flag {{ !empty($request['hiddenText']) ? 'flag-yes' : 'flag-no' }}">{{ !empty($request['hiddenText']) ? $yes : $no }}</span>
            </td>
        </tr>
        <tr>
            <td>
                {{ __('Exclude conjunctions, prepositions, pronouns') }}:
                <span class="flag {{ \App\TextAnalyzer::shouldExcludeConjunctionsPrepositionsPronouns($request ?? []) ? 'flag-yes' : 'flag-no' }}">
                    {{ \App\TextAnalyzer::shouldExcludeConjunctionsPrepositionsPronouns($request ?? []) ? $yes : $no }}
                </span>
            </td>
            <td>
                {{ __('Exclude') }}:
                @if(!empty($request['removeWords']) && !empty($request['listWords']))
                    <span class="flag flag-yes">{{ $request['listWords'] }}</span>
                @else
                    <span class="flag flag-no">{{ $no }}</span>
                @endif
            </td>
        </tr>
    </table>
</div>

@if(!empty($zipfRows))
<div class="section">
    <div class="section-title">{{ __('Text analysis according to Zipfs law') }}</div>
    <p class="section-lead">{{ __('Word density') }} — {{ __('Actual values') }} / {{ __('Ideal values') }} ({{ count($zipfRows) }})</p>
    <table class="data-table">
        <thead>
        <tr>
            <th class="num">#</th>
            <th>{{ __('Word') }}</th>
            <th class="num">{{ __('Actual values') }}</th>
            <th class="num">{{ __('Ideal values') }}</th>
            <th class="num">Δ</th>
        </tr>
        </thead>
        <tbody>
        @foreach($zipfRows as $row)
            @php
                $delta = (int) $row['delta'];
                $deltaClass = $delta > 0 ? 'delta-pos' : ($delta < 0 ? 'delta-neg' : 'delta-zero');
            @endphp
            <tr>
                <td class="num">{{ $row['rank'] }}</td>
                <td>{{ $row['word'] }}</td>
                <td class="num">{{ $row['actual'] }}</td>
                <td class="num">{{ $row['ideal'] }}</td>
                <td class="num {{ $deltaClass }}">{{ $delta > 0 ? '+' . $delta : $delta }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

@if(!empty($cloudText) || !empty($cloudLinks) || !empty($cloudBoth))
<div class="section">
    <div class="section-title">{{ __('The clouds') }}</div>
    <p class="section-lead">{{ __('Word cloud display limit hint') }}</p>

    @if(!empty($cloudText))
        <div class="cloud-zone">
            <p class="cloud-zone-title">{{ __('Text Area') }}</p>
            @foreach($cloudText as $cw)
                <span class="cloud-word cloud-word--t{{ $cw['tier'] }}">{{ $cw['text'] }} <small>({{ $cw['weight'] }})</small></span>
            @endforeach
        </div>
    @endif

    @if(!empty($cloudLinks))
        <div class="cloud-zone">
            <p class="cloud-zone-title">{{ __('Link Zone') }}</p>
            @foreach($cloudLinks as $cw)
                <span class="cloud-word cloud-word--t{{ $cw['tier'] }}">{{ $cw['text'] }} <small>({{ $cw['weight'] }})</small></span>
            @endforeach
        </div>
    @endif

    @if(!empty($cloudBoth))
        <div class="cloud-zone">
            <p class="cloud-zone-title">{{ __('Text and Link zone') }}</p>
            @foreach($cloudBoth as $cw)
                <span class="cloud-word cloud-word--t{{ $cw['tier'] }}">{{ $cw['text'] }} <small>({{ $cw['weight'] }})</small></span>
            @endforeach
        </div>
    @endif
</div>
@endif

<div class="section">
    <div class="section-title">{{ __('General word analysis') }} ({{ count($words) }})</div>
    <table class="data-table">
        <thead>
        <tr>
            <th>{{ __('Word') }}</th>
            <th class="num">{{ __('Density') }}</th>
            <th class="num">{{ __('Common area') }}</th>
            <th class="num">{{ __('Text Area') }}</th>
            <th class="num">{{ __('Link Zone') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($words as $word)
            <tr>
                <td>{{ $word['text'] ?? '' }}</td>
                <td class="num">{{ $word['density'] ?? '' }}</td>
                <td class="num">{{ $word['total'] ?? '' }}</td>
                <td class="num">{{ $word['inText'] ?? '' }}</td>
                <td class="num">{{ $word['inLink'] ?? '' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<div class="section">
    <div class="section-title">{{ __('Phrases of 2 words') }} ({{ count($phrases) }})</div>
    <table class="data-table">
        <thead>
        <tr>
            <th>{{ __('Phrase') }}</th>
            <th class="num">{{ __('Repetitions') }}</th>
            <th class="num">{{ __('Density') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($phrases as $phrase)
            <tr>
                <td>{{ trim($phrase['phrase'] ?? '') }}</td>
                <td class="num">{{ $phrase['count'] ?? '' }}</td>
                <td class="num">{{ $phrase['density'] ?? '' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<div class="report-footer">
    <table width="100%">
        <tr>
            <td width="20%">
                <img src="{{ $logoIconPath }}" height="22" alt="{{ $brandName }}">
            </td>
            <td width="55%">
                <strong>{{ $brandName }}</strong> — {{ __('Text analyzer report') }}<br>
                {{ $brandSite }} · {{ $meta['generated_at'] ?? '' }}
            </td>
            <td width="25%" align="right">
                v{{ $meta['version'] ?? '1.0' }}
            </td>
        </tr>
    </table>
</div>
</body>
</html>
