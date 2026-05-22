@php
    use App\SupportTicket;
    $total = $counts['all'] ?? 0;
@endphp
@if($total > 0)
    <div class="row g-3 mb-3 cabinet-support-stats">
        @include('support.partials.stat-box', [
            'statusParam' => null,
            'label' => __('All tickets'),
            'count' => $counts['all'] ?? 0,
            'icon' => 'bi-inbox',
            'iconClass' => 'text-bg-primary',
        ])
        @include('support.partials.stat-box', [
            'statusParam' => SupportTicket::STATUS_OPEN,
            'label' => $isStaff ? __('Needs reply') : __('Awaiting reply'),
            'count' => $counts[SupportTicket::STATUS_OPEN] ?? 0,
            'icon' => 'bi-hourglass-split',
            'iconClass' => 'text-bg-warning',
        ])
        @include('support.partials.stat-box', [
            'statusParam' => SupportTicket::STATUS_ANSWERED,
            'label' => __('Answered'),
            'count' => $counts[SupportTicket::STATUS_ANSWERED] ?? 0,
            'icon' => 'bi-check-circle',
            'iconClass' => 'text-bg-success',
        ])
        @include('support.partials.stat-box', [
            'statusParam' => SupportTicket::STATUS_CLOSED,
            'label' => __('Closed'),
            'count' => $counts[SupportTicket::STATUS_CLOSED] ?? 0,
            'icon' => 'bi-archive',
            'iconClass' => 'text-bg-secondary',
        ])
    </div>
@endif
