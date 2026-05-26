@component('component.card', ['title' => __('Partners (admins)')])
    @slot('css')
        @include('partners.partials.styles')
    @endslot

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <div class="modal fade" id="removeGroupModal" tabindex="-1" aria-labelledby="removeGroupModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="removeGroupModalLabel">{{ __('Deleting a group') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body small">
                    {{ __('If you delete a group, then all related partners will also be deleted') }}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ __('Close') }}</button>
                    <button id="removeGroupButton" type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">
                        {{ __('Remove') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="removeItemModal" tabindex="-1" aria-labelledby="removeItemModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="removeItemModalLabel">{{ __('Delete partner') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body small">
                    {{ __('Confirm the action.') }}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ __('Close') }}</button>
                    <button id="removeItemButton" type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">
                        {{ __('Remove') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="cabinet-partners-page">
        @include('partners.partials.admin-nav', ['active' => 'admin', 'admin' => true])

        <div class="cabinet-partners-admin-lead px-3 py-2 small text-secondary">
            {{ __('Partners admin lead') }}
        </div>

        @php $groupsList = collect($groups); @endphp

        @if($groupsList->isEmpty())
            <div class="cabinet-partners-empty">
                <p class="mb-2">{{ __('Partners admin empty') }}</p>
                <a href="{{ route('partners.add.group') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-folder-plus me-1" aria-hidden="true"></i>{{ __('Add group') }}
                </a>
            </div>
        @else
            @foreach($groupsList as $elem)
                @php $items = $elem['items'] ?? []; @endphp
                <section class="cabinet-partners-admin-group" data-group-id="{{ $elem['id'] }}">
                    <div class="cabinet-partners-admin-group__head">
                        <div>
                            <p class="cabinet-partners-admin-group__title mb-1">
                                {{ $elem['name_ru'] }}
                                <span class="text-secondary fw-normal">/</span>
                                {{ $elem['name_en'] }}
                            </p>
                            <p class="cabinet-partners-admin-group__meta mb-0">
                                {{ __('Group position') }}: <strong>{{ $elem['position'] }}</strong>
                                · {{ __('Partners') }}: <strong>{{ count($items) }}</strong>
                            </p>
                        </div>
                        <div class="cabinet-partners-admin-actions"
                             data-id="{{ $elem['id'] }}"
                             data-position="{{ $elem['position'] }}">
                            <a href="{{ route('partners.edit.group', $elem['id']) }}"
                               class="btn btn-outline-secondary btn-sm"
                               title="{{ __('Edit') }}">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </a>
                            <button type="button"
                                    class="btn btn-outline-danger btn-sm remove-group"
                                    data-bs-toggle="modal"
                                    data-bs-target="#removeGroupModal"
                                    title="{{ __('Remove') }}">
                                <i class="bi bi-trash" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    @if(count($items) === 0)
                        <div class="p-3 small text-secondary">{{ __('Partners group empty') }}</div>
                    @else
                        <div class="cabinet-partners-admin-table-wrap">
                            <table class="table table-sm table-hover align-middle cabinet-partners-admin-table mb-0">
                                <thead>
                                <tr>
                                    <th>{{ __('Image') }}</th>
                                    <th>{{ __('Partner name') }}</th>
                                    <th>{{ __('Partner description') }}</th>
                                    <th>{{ __('Link') }}</th>
                                    <th class="text-center">{{ __('Position') }}</th>
                                    <th class="text-center">RU</th>
                                    <th class="text-center">EN</th>
                                    <th class="text-end">{{ __('Actions') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($items as $item)
                                    <tr data-item-id="{{ $item['id'] }}">
                                        <td>
                                            <img class="cabinet-partners-admin-table__thumb"
                                                 src="{{ cabinet_storage_url($item['image']) }}"
                                                 alt="{{ $item['name_ru'] ?? '' }}">
                                        </td>
                                        <td>
                                            <div class="cabinet-partners-admin-table__name">{{ $item['name_ru'] ?: '—' }}</div>
                                            <div class="cabinet-partners-admin-table__sub">{{ $item['name_en'] ?: '—' }}</div>
                                        </td>
                                        <td>
                                            <div class="cabinet-partners-admin-table__desc">{{ $item['description_ru'] ?: '—' }}</div>
                                            @if(!empty($item['description_en']))
                                                <div class="cabinet-partners-admin-table__desc mt-1">{{ $item['description_en'] }}</div>
                                            @endif
                                        </td>
                                        <td class="small">
                                            @if(!empty($item['link_ru']))
                                                @php $hostRu = parse_url($item['link_ru'], PHP_URL_HOST); @endphp
                                                <a href="{{ $item['link_ru'] }}" target="_blank" rel="noopener" class="d-block text-break">
                                                    {{ $hostRu ?: $item['link_ru'] }} <span class="text-secondary">(ru)</span>
                                                </a>
                                            @endif
                                            @if(!empty($item['link_en']))
                                                @php $hostEn = parse_url($item['link_en'], PHP_URL_HOST); @endphp
                                                <a href="{{ $item['link_en'] }}" target="_blank" rel="noopener" class="d-block text-break">
                                                    {{ $hostEn ?: $item['link_en'] }} <span class="text-secondary">(en)</span>
                                                </a>
                                            @endif
                                        </td>
                                        <td class="text-center text-num">{{ $item['position'] }}</td>
                                        <td class="text-center">
                                            @if($item['auditorium_ru'])
                                                <span class="badge text-bg-success">{{ __('On') }}</span>
                                            @else
                                                <span class="badge text-bg-secondary">{{ __('Off') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($item['auditorium_en'])
                                                <span class="badge text-bg-success">{{ __('On') }}</span>
                                            @else
                                                <span class="badge text-bg-secondary">{{ __('Off') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <a href="{{ route('partners.edit.item', $item['id']) }}"
                                               class="btn btn-outline-secondary btn-sm"
                                               title="{{ __('Edit') }}">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                            </a>
                                            <button type="button"
                                                    class="btn btn-outline-danger btn-sm remove-item"
                                                    data-id="{{ $item['id'] }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#removeItemModal"
                                                    title="{{ __('Remove') }}">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endforeach
        @endif
    </div>

    @slot('js')
        <script>
            (function ($) {
                var groupId;
                var itemId;
                var $groupTrigger;
                var $itemTrigger;

                $('.remove-group').on('click', function () {
                    $groupTrigger = $(this);
                    groupId = $groupTrigger.closest('[data-id]').attr('data-id');
                });

                $('#removeGroupButton').on('click', function () {
                    $.ajax({
                        type: 'POST',
                        dataType: 'json',
                        url: '{{ route('partners.remove.group') }}',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                            id: groupId
                        },
                        success: function () {
                            $groupTrigger.closest('.cabinet-partners-admin-group').remove();
                        }
                    });
                });

                $('.remove-item').on('click', function () {
                    $itemTrigger = $(this);
                    itemId = $itemTrigger.attr('data-id');
                });

                $('#removeItemButton').on('click', function () {
                    $.ajax({
                        type: 'POST',
                        dataType: 'json',
                        url: '{{ route('partners.remove.item') }}',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                            id: itemId
                        },
                        success: function () {
                            $itemTrigger.closest('tr').remove();
                        }
                    });
                });
            })(jQuery);
        </script>
    @endslot
@endcomponent
