@if($description)
    <div class="card mb-4 cabinet-module-description">
        <div class="card-header d-flex align-items-start gap-3">
            <img class="rounded-circle flex-shrink-0 cabinet-module-description__avatar"
                 src="{{ $description->user->image }}"
                 alt="{{ $description->user->name }}">
            <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold">
                    {{ $description->user->name }} {{ $description->user->last_name }}
                </div>
                <div class="text-secondary small">{{ __('Publicly') }} — {{ $description->updated_at->diffForHumans() }}</div>
            </div>
            <div class="card-tools flex-shrink-0 ms-auto">
                <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
                    <i class="fas fa-minus"></i>
                </button>
                <button type="button" class="btn btn-tool" data-lte-toggle="card-remove">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="card-body">{!! $description->description !!}</div>
    </div>
@endif
