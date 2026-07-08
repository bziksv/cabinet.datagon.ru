<thead class="phrases-thead">
<tr class="phrases-thead__filters">
    <th class="phrases-ranges-label font-weight-normal text-muted">{{ __('Ranges for filtering the table') }}</th>
    <th class="phrases-filter-cell">
        <div class="phrases-filter-box">
            <input class="w-100" type="number" id="phrasesMinTfidfTop" placeholder="min">
            <input class="w-100" type="number" id="phrasesMaxTfidfTop" placeholder="max">
        </div>
    </th>
    <th class="phrases-filter-cell">
        <div class="phrases-filter-box">
            <input class="w-100" type="number" id="phrasesMinTfidfSite" placeholder="min">
            <input class="w-100" type="number" id="phrasesMaxTfidfSite" placeholder="max">
        </div>
    </th>
    <th class="phrases-filter-cell">
        <div class="phrases-filter-box">
            <input class="w-100" type="number" id="phrasesMinBm25Top" placeholder="min">
            <input class="w-100" type="number" id="phrasesMaxBm25Top" placeholder="max">
        </div>
    </th>
    <th class="phrases-filter-cell">
        <div class="phrases-filter-box">
            <input class="w-100" type="number" id="phrasesMinBm25Site" placeholder="min">
            <input class="w-100" type="number" id="phrasesMaxBm25Site" placeholder="max">
        </div>
    </th>
    <th class="phrases-filter-cell">
        <div class="phrases-filter-box">
            <input class="w-100" type="number" id="phrasesMinSites" placeholder="min">
            <input class="w-100" type="number" id="phrasesMaxSites" placeholder="max">
        </div>
    </th>
    <th class="phrases-filter-cell">
        <div class="phrases-filter-box">
            <input class="w-100" type="number" id="phrasesMinMedian" placeholder="min">
            <input class="w-100" type="number" id="phrasesMaxMedian" placeholder="max">
        </div>
    </th>
    <th class="phrases-filter-cell">
        <div class="phrases-filter-box">
            <input class="w-100" type="number" id="phrasesMinAvg" placeholder="min">
            <input class="w-100" type="number" id="phrasesMaxAvg" placeholder="max">
        </div>
    </th>
    <th class="phrases-filter-cell">
        <div class="phrases-filter-box">
            <input class="w-100" type="number" id="phrasesMinOurSite" placeholder="min">
            <input class="w-100" type="number" id="phrasesMaxOurSite" placeholder="max">
        </div>
    </th>
</tr>
<tr class="phrases-thead__divider" aria-hidden="true">
    <th colspan="9"></th>
</tr>
<tr class="phrases-thead__titles">
    <th class="phrases-th-words">
        {{ __('Phrase') }}
        <span class="__helper-link ui_tooltip_w">
            <i class="fa fa-question-circle"></i>
            <span class="ui_tooltip __left">
                <span class="ui_tooltip_content">{{ __('Phrases (2-4 words) that occur on competitors websites, including all word forms.') }}</span>
            </span>
        </span>
    </th>
    <th class="phrases-th-metric">
        {{ __('TF-IDF TOP') }}
        <span class="__helper-link ui_tooltip_w">
            <i class="fa fa-question-circle"></i>
            <span class="ui_tooltip __left">
                <span class="ui_tooltip_content">{{ __('Hybrid TF-IDF in the competitor corpus: frequency × corpus IDF × coverage across TOP sites.') }}</span>
            </span>
        </span>
    </th>
    <th class="phrases-th-metric">
        {{ __('TF-IDF your site') }}
        <span class="__helper-link ui_tooltip_w">
            <i class="fa fa-question-circle"></i>
            <span class="ui_tooltip __left">
                <span class="ui_tooltip_content">{{ __('Hybrid TF-IDF on your landing page using the same competitor IDF and coverage.') }}</span>
            </span>
        </span>
    </th>
    <th class="phrases-th-metric">
        {{ __('BM25 TOP') }}
        <span class="__helper-link ui_tooltip_w">
            <i class="fa fa-question-circle"></i>
            <span class="ui_tooltip __left">
                <span class="ui_tooltip_content">{{ __('BM25 weight in the aggregated competitor corpus.') }}</span>
            </span>
        </span>
    </th>
    <th class="phrases-th-metric">
        {{ __('BM25 your site') }}
        <span class="__helper-link ui_tooltip_w">
            <i class="fa fa-question-circle"></i>
            <span class="ui_tooltip __left">
                <span class="ui_tooltip_content">{{ __('BM25 weight on your landing page.') }}</span>
            </span>
        </span>
    </th>
    <th class="phrases-th-narrow">
        {{ __('Number of sites') }}
        <span class="__helper-link ui_tooltip_w">
            <i class="fa fa-question-circle"></i>
            <span class="ui_tooltip __left">
                <span class="ui_tooltip_content">{{ __('The number of sites in which the phrase is present.') }}</span>
            </span>
        </span>
    </th>
    <th class="phrases-th-narrow">
        {{ __('Median occurrence') }}
        <span class="__helper-link ui_tooltip_w">
            <i class="fa fa-question-circle"></i>
            <span class="ui_tooltip __left">
                <span class="ui_tooltip_content">{{ __('The median number of phrase repetitions across competitors.') }}</span>
            </span>
        </span>
    </th>
    <th class="phrases-th-narrow">
        {{ __('Average') }}
        <span class="__helper-link ui_tooltip_w">
            <i class="fa fa-question-circle"></i>
            <span class="ui_tooltip __left">
                <span class="ui_tooltip_content">{{ __('The average number of phrase repetitions across competitors.') }}</span>
            </span>
        </span>
    </th>
    <th class="phrases-th-narrow">
        {{ __('On our site') }}
        <span class="__helper-link ui_tooltip_w">
            <i class="fa fa-question-circle"></i>
            <span class="ui_tooltip __left">
                <span class="ui_tooltip_content">{{ __('The number of phrase repetitions on your page.') }}</span>
            </span>
        </span>
    </th>
</tr>
</thead>
