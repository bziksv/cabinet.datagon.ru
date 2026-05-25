@extends('ideas.layout')

@section('ideas-content')
    @include('ideas.partials.hero')

    @include('ideas.partials.tabs')

    @if($ideas->isEmpty())
        <div class="card shadow-sm">
            <div class="card-body text-center py-5 px-4">
                <i class="bi bi-lightbulb display-5 text-warning opacity-50 d-block mb-3"></i>
                @if(($search ?? '') !== '')
                    <p class="mb-2">{{ __('No ideas match your search.') }}</p>
                    <a href="{{ route('ideas.index', array_filter(['tab' => ($filter ?? 'popular') !== 'popular' ? $filter : null])) }}"
                       class="btn btn-outline-secondary btn-sm">{{ __('Clear search') }}</a>
                @elseif(($filter ?? '') === 'moderation')
                    <p class="mb-0 text-secondary">{{ __('No ideas awaiting moderation.') }}</p>
                @elseif(($filter ?? '') === 'mine')
                    <p class="mb-3 text-secondary">{{ __('You have not suggested any ideas yet.') }}</p>
                    <a href="{{ route('ideas.create') }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i>{{ __('Suggest an idea') }}
                    </a>
                @else
                    <p class="mb-3 text-secondary">{{ __('Be the first to suggest an improvement.') }}</p>
                    <a href="{{ route('ideas.create') }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i>{{ __('Suggest an idea') }}
                    </a>
                @endif
            </div>
        </div>
    @else
        <div class="cabinet-ideas-list">
            @foreach($ideas as $idea)
                @include('ideas.partials.idea-card', ['idea' => $idea])
            @endforeach
        </div>
        <div class="d-flex justify-content-center mt-2">
            {{ $ideas->links() }}
        </div>
    @endif
@endsection

@section('ideas-js')
    <script src="{{ asset('js/cabinet-ideas.js') }}"></script>
@endsection
