@php
    $configKey = $configKey ?? null;
    $fallback = $fallback ?? '1.0';
    $moduleVersion = $configKey ? config($configKey . '.version', $fallback) : $fallback;
    $isStable = is_string($moduleVersion) && preg_match('/s$/', $moduleVersion);
    $versionTitle = $isStable
        ? __('Stable module version')
        : __('Module version');
@endphp
<span class="badge text-bg-secondary cabinet-module-version-badge ms-1 align-middle{{ $isStable ? ' cabinet-module-version-badge--stable' : '' }}"
      title="{{ $versionTitle }}">v{{ $moduleVersion }}</span>
