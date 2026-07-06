<?php

return [
    'version' => '1.3.0s',

    /** По умолчанию не учитывать в KPI, графике и топе операции пользователей с этими ролями. */
    'exclude_admin_stats_default' => true,

    'exclude_admin_roles' => ['admin', 'Super Admin'],

    'exclude_admin_stats_session_key' => 'finance_admin_exclude_admins',
];
