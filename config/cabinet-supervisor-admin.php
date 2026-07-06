<?php

/**
 * Админ-страница /admin/supervisor — статус воркеров supervisord.
 *
 * @see App\Services\Supervisor\SupervisorAdminService
 */
return [
    'version' => '1.0.0s',

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
        'cabinet-titlo-queue-default' => 'storage/logs/supervisor-queue-default.log',
        'cabinet-titlo-queue-cluster' => 'storage/logs/supervisor-queue-cluster.log',
        'cabinet-titlo-queue-monitoring' => 'storage/logs/supervisor-queue-monitoring.log',
    ],
];
