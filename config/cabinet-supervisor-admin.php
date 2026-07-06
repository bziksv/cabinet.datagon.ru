<?php

/**
 * Админ-страница /admin/supervisor — статус воркеров supervisord.
 *
 * @see App\Services\Supervisor\SupervisorAdminService
 */
return [
    'version' => '1.2.0s',

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

    /**
     * Модуль кабинета для программы supervisord (label — ключ __(), route — имя route()).
     *
     * @see App\Services\Supervisor\SupervisorAdminService::moduleForProgram()
     */
    'program_modules' => [
        'cabinet-titlo-default' => ['label' => 'Queue management', 'route' => 'admin.queue.index'],
        'cabinet-titlo-cluster-child' => ['label' => 'Cluster', 'route' => 'cluster'],
        'cabinet-titlo-cluster-main' => ['label' => 'Cluster', 'route' => 'cluster'],
        'cabinet-titlo-cluster-wait' => ['label' => 'Cluster', 'route' => 'cluster'],
        'cabinet-titlo-position' => ['label' => 'Position monitoring', 'route' => 'monitoring.v2'],
        'cabinet-titlo-relevance' => ['label' => 'Relevance', 'route' => 'relevance-analysis'],
        'cabinet-titlo-monitoring-helper' => ['label' => 'Position monitoring', 'route' => 'monitoring.v2'],
        'cabinet-titlo-monitoring-change-dates' => ['label' => 'Position monitoring', 'route' => 'monitoring.v2'],
        'cabinet-titlo-monitoring-wait' => ['label' => 'Position monitoring', 'route' => 'monitoring.v2'],
        'cabinet-titlo-monitoring-competitors-stat' => ['label' => 'Position monitoring', 'route' => 'monitoring.v2'],
        'cabinet-titlo-competitor-analyse' => ['label' => 'Competitor analysis', 'route' => 'competitor.analysis'],
        'cabinet-titlo-ai-generation' => ['label' => 'Supervisor module ai generation', 'route' => 'ai.generation.story'],
        'cabinet-titlo-websockets' => ['label' => 'Supervisor module websockets', 'route' => null],
    ],

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
