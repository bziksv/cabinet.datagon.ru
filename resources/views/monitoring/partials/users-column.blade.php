<ul class="list-inline user-list">
    @php
        static $cabinetMonUserStatusById = null;
        if ($cabinetMonUserStatusById === null) {
            $cabinetMonUserStatusById = \App\MonitoringUserStatus::all()->keyBy('id');
        }
    @endphp

    @foreach ($project->users as $user)
        @php
            $statusId = (int) ($user->pivot->status ?? 0);
            $status = $cabinetMonUserStatusById->get($statusId);
            $statusCode = $status ? (string) $status->code : '';
            $statusName = $status ? (string) $status->name : __('Without status');
            if ($statusCode === '' || $statusCode === 'EMPTY') {
                $statusName = __('Without status');
            }
            $tip = trim($user->name . ' ' . $user->last_name) . ' — ' . $statusName;
            $badgeText = (!$statusCode || $statusCode === 'EMPTY')
                ? '—'
                : ($statusCode === 'OWNER' ? 'OWN' : $statusCode);
        @endphp
        <li class="list-inline-item position-relative @can('change_user_status_project_monitoring') change-user-status @endcan"
            user-id="{{ $user->id }}" project-id="{{ $project->id }}"
            data-bs-toggle="tooltip" title="{{ $tip }}">

            @if ($user->hasRole('admin_monitoring') || (int) ($user->pivot->admin ?? 0) >= 1)
                <img class="table-avatar img-circle img-bordered-sm admin-monitoring" src="{{ $user->image }}" alt="">
            @else
                <img class="table-avatar img-circle img-bordered-sm" src="{{ $user->image }}" alt="">
            @endif

            <span class="cabinet-mon-user-status-badge cabinet-mon-user-status-badge--{{ strtolower($statusCode ?: 'empty') }}">{{ $badgeText }}</span>

            @if(auth()->user() && auth()->user()->can('delete_user_from_project_monitoring') && $user->id !== auth()->id())
                <span class="badge badge-secondary navbar-badge detach-user" data-id="{{ $user->id }}" data-project="{{ $project->id }}" style="cursor: pointer; top: -5px; right: 0px; font-size: x-small;">
                    <i class="fas fa-times"></i>
                </span>
            @endif
        </li>
    @endforeach

</ul>
