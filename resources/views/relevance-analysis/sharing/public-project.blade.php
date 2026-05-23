@component('component.card', ['title' => $project->name])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/common/css/datatable.css') }}"/>
        <style>
            .public-share-banner {
                border-left: 4px solid #2563eb;
            }
        </style>
    @endslot

    <div class="alert alert-info public-share-banner mb-4">
        <div class="fw-semibold mb-1">{{ __('Public project access') }}</div>
        <div class="mb-0 small">{{ __('View-only access without registration. Link expires on') }}
            <strong>{{ $share->expires_at->format('d.m.Y H:i') }}</strong>.
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="small text-muted">{{ __('Number of analyzed pages') }}</div>
            <div class="fs-5 fw-semibold">{{ number_format($project->count_sites ?? 0, 0, ',', ' ') }}</div>
        </div>
        <div class="col-md-3">
            <div class="small text-muted">{{ __('Number of saved scans') }}</div>
            <div class="fs-5 fw-semibold">{{ number_format($project->count_checks ?? 0, 0, ',', ' ') }}</div>
        </div>
        <div class="col-md-3">
            <div class="small text-muted">{{ __('Total score') }}</div>
            <div class="fs-5 fw-semibold">{{ number_format($project->total_points ?? 0, 2, ',', ' ') }}</div>
        </div>
        <div class="col-md-3">
            <div class="small text-muted">{{ __('Avg position') }}</div>
            <div class="fs-5 fw-semibold">{{ number_format($project->avg_position ?? 0, 2, ',', ' ') }}</div>
        </div>
    </div>

    <h5 class="mb-3">{{ __('Saved scans') }}</h5>
    <div class="table-responsive">
        <table class="table table-bordered table-hover mb-0" id="public-share-scans">
            <thead>
            <tr>
                <th>{{ __('Phrase') }}</th>
                <th>{{ __('Landing page') }}</th>
                <th>{{ __('Region') }}</th>
                <th>{{ __('Last check') }}</th>
                <th>{{ __('Total score') }}</th>
                <th>{{ __('Position') }}</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($stories as $story)
                <tr>
                    <td>{{ $story->phrase }}</td>
                    <td class="text-break">{{ $story->main_link }}</td>
                    <td>{{ $story->region }}</td>
                    <td>{{ $story->last_check }}</td>
                    <td>{{ $story->points }}</td>
                    <td>{{ $story->position }}</td>
                    <td>
                        <a href="{{ route('relevance.public.share.history', ['token' => $publicShareToken, 'id' => $story->id]) }}"
                           class="btn btn-sm btn-secondary" target="_blank" rel="noopener">
                            {{ __('Detailed information') }}
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">{{ __('No data') }}</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @slot('js')
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        <script>
            $(function () {
                $('#public-share-scans').DataTable({
                    pageLength: 25,
                    order: [[3, 'desc']],
                });
            });
        </script>
    @endslot
@endcomponent
