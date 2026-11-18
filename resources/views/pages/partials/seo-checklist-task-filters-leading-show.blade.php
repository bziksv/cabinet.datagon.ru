<label class="cabinet-sc-plan-filters__my-role">
    <span class="visually-hidden">{{ __('Filter by task role') }}</span>
    <select class="form-select form-select-sm" data-sc-show-task-role>
        <option value="">{{ __('All roles') }}</option>
        @if(count($myRoles ?? []) > 0)
            <option value="mine">{{ __('My tasks') }}</option>
        @endif
        @foreach(($roleLabels ?? []) as $roleKey => $roleLabel)
            @php $rs = ($roleStats[$roleKey] ?? ['done' => 0, 'total' => 0]); @endphp
            @if(((int) ($rs['total'] ?? 0)) > 0)
                <option value="role:{{ $roleKey }}">
                    {{ $roleLabel }}
                    ({{ number_format((int) $rs['done'], 0, '', ' ') }}/{{ number_format((int) $rs['total'], 0, '', ' ') }})
                </option>
            @endif
        @endforeach
    </select>
</label>
