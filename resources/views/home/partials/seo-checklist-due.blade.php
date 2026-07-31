@if(!empty($seoChecklistDue) && ($seoChecklistDue['count'] ?? 0) > 0)
    @php
        $overdueN = (int) ($seoChecklistDue['overdue'] ?? 0);
        $soonN = (int) ($seoChecklistDue['soon'] ?? 0);
        $items = $seoChecklistDue['items'] ?? collect();
    @endphp
    <div class="cabinet-home-sc-due alert @if($overdueN > 0) alert-warning @else alert-info @endif mb-3" role="status">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
            <div>
                <strong>{{ __('SEO checklist deadlines') }}</strong>
                <div class="small mb-0">
                    @if($overdueN > 0)
                        {{ trans_choice(':count overdue task|:count overdue tasks', $overdueN, ['count' => $overdueN]) }}
                        @if($soonN > 0) · @endif
                    @endif
                    @if($soonN > 0)
                        <span title="{{ __('Due soon filter hint') }}">{{ trans_choice(':count due soon|:count due soon', $soonN, ['count' => $soonN]) }}</span>
                    @endif
                </div>
            </div>
            <a href="{{ route('pages.seo-checklist.my-tasks') }}" class="btn btn-sm @if($overdueN > 0) btn-warning @else btn-outline-primary @endif">
                {{ __('Open SEO checklist') }}
            </a>
        </div>
        <ul class="cabinet-home-sc-due__list mb-0">
            @foreach($items->take(6) as $item)
                @php
                    $project = $item->project;
                    $isOver = $item->isOverdue();
                @endphp
                <li>
                    @if($project)
                        <a href="{{ route('pages.seo-checklist.show', ['id' => $project->id]) }}">{{ $project->domain }}</a>
                    @endif
                    <span class="cabinet-home-sc-due__task">{{ \Illuminate\Support\Str::limit($item->title, 72) }}</span>
                    <span class="cabinet-home-sc-due__when @if($isOver) is-overdue @endif">
                        @if($isOver)
                            {{ __('Overdue') }}
                        @elseif($item->due_at)
                            {{ __('Due') }} {{ $item->due_at->format('d.m') }}
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
