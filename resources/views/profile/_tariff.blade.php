@php
    $embedded = $embedded ?? false;
@endphp
@if(!$embedded)
<div class="card">
@endif
    <div class="{{ $embedded ? '' : 'card-header' }}">
        @if(!$embedded)
            <h3 class="card-title mb-0">{{ __('Tariff settings for user') }}</h3>
        @else
            <p class="text-secondary small mb-3">{{ __('Override tariff limits for this account.') }}</p>
        @endif
    </div>
    <div class="{{ $embedded ? '' : 'card-body p-0' }}">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 3rem">#</th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Limits') }}</th>
                        <th class="text-center" style="width: 4rem">{{ __('Delete') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tariffProperties as $tariff)
                        <tr>
                            <td>{{ $tariff['setting']['id'] }}</td>
                            <td>{{ $tariff['setting']['name'] }}</td>
                            <td><code class="small">{{ $tariff['setting']['code'] }}</code></td>
                            <td class="small">
                                @foreach($tariff['fields'] as $limit)
                                    <div class="mb-1">
                                        {{ $limit->field['tariff'] }}:
                                        <span class="badge text-bg-primary">{{ $limit['value'] }}</span>
                                    </div>
                                @endforeach
                            </td>
                            <td class="text-center">
                                <form action="{{ route('user-tariff.destroy', implode(',', $tariff['ids'])) }}" method="post"
                                      onsubmit='return confirm(@json(__('Delete this limit?')))'>
                                    @csrf
                                    @method('delete')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-secondary py-4">{{ __('No records') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="{{ $embedded ? 'mt-3 text-end' : 'card-footer text-end' }}">
        <a href="{{ route('user-tariff.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil-square me-1"></i>{{ __('Edit or add') }}
        </a>
    </div>
@if(!$embedded)
</div>
@endif
