@php
    $configKey = $configKey ?? null;
    $fallback = $fallback ?? '1.0';
    $moduleVersion = $configKey ? config($configKey . '.version', $fallback) : $fallback;
@endphp
<span class="badge text-bg-secondary cabinet-module-version-badge ms-1 align-middle"
      title="{{ __('Module version') }}">v{{ $moduleVersion }}</span>
