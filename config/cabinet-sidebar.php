<?php

return [
    /*
    | Скрыть legacy «Мониторинг позиций» (/monitoring, main_projects.id 32).
    | Остальные пункты меню не трогаем.
    */
    'hide_legacy_monitoring' => env('CABINET_SIDEBAR_HIDE_LEGACY_MONITORING', true),

    'hidden_project_ids' => array_map('intval', array_filter(explode(',', env(
        'CABINET_SIDEBAR_HIDDEN_PROJECT_IDS',
        '32'
    )))),
];
