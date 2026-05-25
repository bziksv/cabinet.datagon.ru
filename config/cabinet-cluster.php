<?php

return [
  /**
   * Видимая версия модуля /cluster (badge в шапке карточки).
   * Журнал: datagon.ru/docs/cabinet-cluster-changelog.md
   */
  'version' => '2.16',

  /** Расширенный лог прогресса для admin / Super Admin (UI как competitor-analysis) */
  'debug_log' => env('CLUSTER_DEBUG_LOG', true),
  'debug_log_ttl_minutes' => (int) env('CLUSTER_DEBUG_LOG_TTL', 120),
  'debug_log_max_entries' => (int) env('CLUSTER_DEBUG_LOG_MAX', 250),

  /** Пресеты формы /cluster-v2 (фразы — resources/data/) */
  'presets' => [
    'kawe' => [
      'phrases_file' => resource_path('data/cluster-v2-preset-kawe.txt'),
      'domain' => 'kawe.su',
      'search_base' => true,
      'search_phrases' => true,
      'search_target' => true,
      'search_relevance' => true,
      'save' => '1',
      'send_message' => '1',
    ],
  ],
];
