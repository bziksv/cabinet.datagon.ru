<?php

/**
 * Админ-страница /admin/supervisor — статус воркеров supervisord.
 *
 * @see App\Services\Supervisor\SupervisorAdminService
 */
return [
    'version' => '1.1.0s',

    /** false — страница только для чтения с подсказкой по установке */
    'enabled' => filter_var(env('SUPERVISOR_ADMIN_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    /** Команда supervisorctl (при необходимости: "sudo /usr/bin/supervisorctl") */
    'supervisorctl' => env('SUPERVISORCTL_BIN', '/usr/bin/supervisorctl'),

    /**
     * Разрешённые имена процессов (glob). Только они — start/stop/restart.
     * Пример: cabinet-titlo-* — все программы из deploy/supervisor/cabinet-titlo.conf.example
     */
    'allowed_programs' => array_filter(array_map('trim', explode(',', env(
        'SUPERVISOR_ADMIN_ALLOWED',
        'cabinet-titlo-*'
    )))),

    /** Подсказка в UI — путь к нашему conf (не трогаем FastPanel/nginx) */
    'config_hint' => env(
        'SUPERVISOR_CONFIG_HINT',
        '/etc/supervisor/conf.d/cabinet-titlo.conf'
    ),

    /** Логи воркеров относительно корня проекта (storage/logs/...) */
    'log_files' => [
        'cabinet-titlo-default' => 'storage/logs/supervisor-default.log',
        'cabinet-titlo-cluster-child' => 'storage/logs/supervisor-cluster-child.log',
        'cabinet-titlo-cluster-main' => 'storage/logs/supervisor-cluster-main.log',
        'cabinet-titlo-cluster-wait' => 'storage/logs/supervisor-cluster-wait.log',
        'cabinet-titlo-position' => 'storage/logs/supervisor-position.log',
        'cabinet-titlo-relevance' => 'storage/logs/supervisor-relevance.log',
        'cabinet-titlo-monitoring-helper' => 'storage/logs/supervisor-monitoring-helper.log',
        'cabinet-titlo-monitoring-change-dates' => 'storage/logs/supervisor-monitoring-change-dates.log',
        'cabinet-titlo-monitoring-wait' => 'storage/logs/supervisor-monitoring-wait.log',
        'cabinet-titlo-monitoring-competitors-stat' => 'storage/logs/supervisor-monitoring-competitors-stat.log',
        'cabinet-titlo-competitor-analyse' => 'storage/logs/supervisor-competitor-analyse.log',
        'cabinet-titlo-ai-generation' => 'storage/logs/supervisor-ai-generation.log',
        'cabinet-titlo-websockets' => 'storage/logs/supervisor-websockets.log',
    ],
];
