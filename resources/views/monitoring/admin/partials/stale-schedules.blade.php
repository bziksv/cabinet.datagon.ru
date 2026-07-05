@include('partials.monitoring-stale-schedules', [
    'staleMonitoring' => $staleMonitoring,
    'staleIdPrefix' => 'cabinet-mon-admin-stale',
    'staleExpanded' => true,
    'staleShowLogic' => true,
    'staleTitle' => __('Monitoring admin stale schedules title'),
    'staleFilterOnUsersPage' => false,
])
