{{--
  Баннер бета-тестирования модуля.
  @var string $moduleName — название модуля для текста
--}}
@php
    $moduleName = $moduleName ?? __('Module');
@endphp
<div class="alert alert-warning border cabinet-module-beta-banner mb-3" role="status">
    <div class="d-flex gap-2 align-items-start">
        <span class="cabinet-module-beta-banner__badge">{{ __('Beta') }}</span>
        <div class="small mb-0">
            <strong class="d-block mb-1">
                {{ __('Beta testing banner title', ['module' => $moduleName]) }}
            </strong>
            {{ __('Beta testing banner body before support') }}
            <a href="{{ route('support.create') }}">{{ __('Beta testing support link') }}</a>.
            {{ __('Beta testing banner body after support') }}
        </div>
    </div>
</div>
