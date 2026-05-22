@php
    $owner = $ticket->user;
    $ownerName = $owner->fullName ?: $owner->email;
@endphp
@component('component.card', ['title' => __('Ticket') . ' #' . $ticket->id])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-support.css') }}">
    @endslot

    @slot('tools')
        <a href="{{ route('support.index', array_filter(['status' => $filter !== 'all' ? $filter : null, 'q' => $search ?: null])) }}"
           class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>{{ __('Back to list') }}
        </a>
    @endslot

    <div class="cabinet-support-page">
        <div class="row g-3">
            @include('support.partials.sidebar')

            <div class="col-lg-9">
                <div class="card cabinet-support-thread">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h3 class="card-title mb-1">{{ $ticket->subject }}</h3>
                            <div class="small text-secondary">
                                @if($isStaff)
                                    <span class="me-2"><i class="bi bi-person me-1"></i>{{ $ownerName }}</span>
                                    <span class="me-2">{{ $owner->email }}</span>
                                @endif
                                <span class="badge {{ $ticket->statusBadgeClass() }}">{{ $ticket->statusLabel() }}</span>
                                <span class="ms-2">{{ __('Updated') }} {{ $ticket->updated_at->diffForHumans() }}</span>
                            </div>
                        </div>
                        @if(!$ticket->isClosed())
                            {!! Form::open(['route' => ['support.close', $ticket], 'method' => 'PATCH', 'class' => 'd-inline']) !!}
                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm(@json(__('Close this ticket?')))">
                                <i class="bi bi-archive me-1"></i>{{ __('Close ticket') }}
                            </button>
                            {!! Form::close() !!}
                        @endif
                    </div>
                    <div class="card-body">
                        @foreach($ticket->messages as $message)
                            @php
                                $author = $message->user;
                                $authorName = $author->fullName ?: __('User');
                                $initials = mb_strtoupper(mb_substr($authorName, 0, 1));
                            @endphp
                            <div class="d-flex gap-3 align-items-start mb-4">
                                @if(!empty($author->image))
                                    <img src="{{ $author->image }}"
                                         alt="{{ $authorName }}"
                                         class="rounded-circle flex-shrink-0"
                                         width="48"
                                         height="48">
                                @else
                                    <div class="flex-shrink-0 rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center"
                                         style="width: 48px; height: 48px"
                                         aria-hidden="true">{{ $initials }}</div>
                                @endif
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-1">
                                        <div>
                                            <span class="fw-semibold">{{ $authorName }}</span>
                                            @if($message->is_staff)
                                                <span class="badge text-bg-primary ms-1">{{ __('Support') }}</span>
                                            @endif
                                        </div>
                                        <small class="text-secondary">{{ $message->created_at->format('d.m.Y H:i') }}</small>
                                    </div>
                                    <div class="cabinet-support-bubble {{ $message->is_staff ? 'cabinet-support-bubble--staff' : 'cabinet-support-bubble--user' }}">
                                        {!! nl2br(e($message->body)) !!}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if($canStaffReply)
                        <div class="card-footer border-top">
                            <h6 class="fw-semibold mb-2">
                                <i class="bi bi-reply me-1"></i>{{ __('Reply as support') }}
                            </h6>
                            {!! Form::open(['route' => ['support.messages.store', $ticket], 'method' => 'POST']) !!}
                            <input type="hidden" name="as_staff" value="1">
                            <div class="mb-3">
                                {!! Form::textarea('body', old('body'), [
                                    'class' => 'form-control' . ($errors->has('body') ? ' is-invalid' : ''),
                                    'rows' => 5,
                                    'required' => true,
                                    'placeholder' => __('Your answer to the user'),
                                ]) !!}
                                @error('body')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send me-1"></i>{{ __('Send reply') }}
                            </button>
                            {!! Form::close() !!}
                        </div>
                    @elseif($ticket->isClosed())
                        <div class="card-footer text-secondary small">
                            <i class="bi bi-lock me-1"></i>{{ __('This ticket is closed.') }}
                        </div>
                    @else
                        <div class="card-footer text-secondary small">
                            <i class="bi bi-hourglass-split me-1"></i>{{ __('Waiting for a response from support.') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endcomponent
