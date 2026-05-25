@php
    $voted = in_array((int) $idea->id, $votedIds ?? [], true);
    $canVote = \App\Support\FeatureIdeaAccess::canVote($idea);
    $isOwner = (int) $idea->user_id === (int) auth()->id();
    $showModeration = ($isStaff ?? false) && $idea->isPending();
@endphp

<article class="cabinet-ideas-card card shadow-sm mb-3" data-idea-id="{{ $idea->id }}">
    <div class="card-body p-0">
        <div class="d-flex">
            @if($idea->isApproved())
                <div class="cabinet-ideas-vote flex-shrink-0 p-3 border-end">
                    <button type="button"
                            class="cabinet-ideas-vote__btn {{ $voted ? 'is-voted' : '' }} {{ !$canVote ? 'is-disabled' : '' }}"
                            data-vote-url="{{ route('ideas.vote', $idea) }}"
                            data-idea-id="{{ $idea->id }}"
                            @if(!$canVote) disabled title="{{ $isOwner ? __('Your idea') : '' }}" @endif
                            aria-pressed="{{ $voted ? 'true' : 'false' }}"
                            aria-label="{{ __('Vote') }}">
                        <i class="bi bi-chevron-up" aria-hidden="true"></i>
                        <span class="cabinet-ideas-vote__count" data-votes-count>{{ $idea->votes_count }}</span>
                    </button>
                </div>
            @else
                <div class="cabinet-ideas-vote flex-shrink-0 p-3 border-end cabinet-ideas-vote--muted">
                    <div class="cabinet-ideas-vote__btn is-disabled" aria-hidden="true">
                        <i class="bi bi-hourglass-split"></i>
                        <span class="cabinet-ideas-vote__count">—</span>
                    </div>
                </div>
            @endif

            <div class="flex-grow-1 p-3 p-md-4 min-w-0">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
                    <h2 class="h6 mb-0 cabinet-ideas-card__title">{{ $idea->title }}</h2>
                    <span class="badge {{ $idea->statusBadgeClass() }}">{{ $idea->statusLabel() }}</span>
                </div>

                <div class="text-secondary small mb-3 cabinet-ideas-card__body">
                    {!! \App\Support\TextAutoLinker::format($idea->body, 480) !!}
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2 small text-secondary">
                    @include('support.partials.avatar', [
                        'src' => optional($idea->user)->image,
                        'name' => $idea->authorDisplayName(),
                        'size' => 28,
                    ])
                    <span>{{ $idea->authorDisplayName() }}</span>
                    <span aria-hidden="true">·</span>
                    <time datetime="{{ $idea->created_at->toIso8601String() }}">
                        {{ $idea->created_at->format('d.m.Y') }}
                    </time>
                    @if($idea->isApproved() && $idea->approved_at)
                        <span aria-hidden="true">·</span>
                        <span><i class="bi bi-check2-circle me-1"></i>{{ __('Published on') }} {{ $idea->approved_at->format('d.m.Y') }}</span>
                    @endif
                </div>

                @if($idea->isRejected() && $idea->moderator_note)
                    <div class="alert alert-light border small mt-3 mb-0 py-2">
                        <i class="bi bi-info-circle me-1"></i>{{ $idea->moderator_note }}
                    </div>
                @endif

                @if($showModeration)
                    <div class="cabinet-ideas-moderation mt-3 pt-3 border-top">
                        <p class="small fw-semibold mb-2">{{ __('Moderation') }}</p>
                        <div class="row g-2">
                            <div class="col-md-6">
                                {!! Form::open(['route' => ['ideas.approve', $idea], 'method' => 'POST', 'class' => 'cabinet-ideas-moderation__form']) !!}
                                <input type="hidden" name="moderator_note" value="">
                                <button type="submit" class="btn btn-success btn-sm w-100">
                                    <i class="bi bi-check-lg me-1"></i>{{ __('Publish for voting') }}
                                </button>
                                {!! Form::close() !!}
                            </div>
                            <div class="col-md-6">
                                {!! Form::open(['route' => ['ideas.reject', $idea], 'method' => 'POST']) !!}
                                <div class="input-group input-group-sm mb-2">
                                    <input type="text"
                                           name="moderator_note"
                                           class="form-control"
                                           maxlength="500"
                                           placeholder="{{ __('Reason (optional)') }}">
                                </div>
                                <button type="submit" class="btn btn-outline-secondary btn-sm w-100">
                                    <i class="bi bi-x-lg me-1"></i>{{ __('Decline') }}
                                </button>
                                {!! Form::close() !!}
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</article>
