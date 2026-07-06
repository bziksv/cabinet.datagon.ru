@php
    $highlightPref = request()->query('pref');
@endphp

<p class="text-secondary mb-4">{{ __('Profile notify intro') }}</p>

<form method="POST" action="{{ route('profile.notifications.update') }}" id="cabinet-profile-notifications-form">
    @csrf
    @method('PATCH')

    @forelse($notificationGroups ?? [] as $group)
        <div class="cabinet-profile-notify-group mb-4">
            <h3 class="h6 fw-semibold mb-3">
                <i class="bi bi-envelope me-1 text-primary" aria-hidden="true"></i>{{ $group['title'] }}
            </h3>
            <div class="list-group list-group-flush border rounded">
                @foreach($group['items'] as $item)
                    @php
                        $rowId = 'profile-notify-' . preg_replace('/[^a-z0-9._-]+/i', '-', $item['key']);
                        $isHighlighted = $highlightPref !== null && $highlightPref === $item['key'];
                    @endphp
                    <div class="list-group-item cabinet-profile-notify-row {{ $isHighlighted ? 'cabinet-profile-notify-row-highlight' : '' }}"
                         id="{{ $rowId }}"
                         data-pref-key="{{ $item['key'] }}">
                        <div class="cabinet-profile-notify-row__inner">
                            <div class="cabinet-profile-notify-row__body">
                                <div class="fw-semibold">{{ $item['title'] }}</div>
                                @if(!empty($item['description']))
                                    <div class="small text-secondary mt-1">{{ $item['description'] }}</div>
                                @endif
                                @if(!empty($item['is_service']))
                                    <div class="small text-muted mt-1">
                                        <i class="bi bi-lock-fill me-1" aria-hidden="true"></i>{{ __('Profile notify service always on') }}
                                    </div>
                                @endif
                            </div>
                            <div class="cabinet-profile-notify-row__switch">
                                @if(!empty($item['can_toggle']))
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input cabinet-profile-notify-toggle"
                                               type="checkbox"
                                               role="switch"
                                               name="notifications[{{ $item['key'] }}]"
                                               value="1"
                                               id="{{ $rowId }}-switch"
                                               {{ !empty($item['enabled']) ? 'checked' : '' }}>
                                        <label class="form-check-label visually-hidden" for="{{ $rowId }}-switch">
                                            {{ $item['title'] }}
                                        </label>
                                    </div>
                                @else
                                    <span class="badge text-bg-secondary">{{ __('Profile notify always on badge') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <p class="text-secondary mb-0">{{ __('Profile notify empty') }}</p>
    @endforelse

    @if(!empty($notificationGroups))
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check2 me-1"></i>{{ __('Save') }}
        </button>
    @endif
</form>
