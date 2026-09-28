<?php

return [
    /** Версия модуля для бейджа в UI (как у аудита сайта). */
    'version' => '0.9.1',

    /** Вложения к задачам и комментариям чеклиста. */
    'attachments' => [
        'max_kb' => (int) env('SEO_CHECKLIST_ATTACHMENT_MAX_KB', 10240),
        'max_files' => (int) env('SEO_CHECKLIST_ATTACHMENT_MAX_FILES', 5),
        'extensions' => [
            'jpg', 'jpeg', 'png', 'gif', 'webp',
            'txt', 'doc', 'docx', 'rtf', 'odt',
            'pdf',
            'xls', 'xlsx', 'csv', 'ods',
        ],
    ],
];
