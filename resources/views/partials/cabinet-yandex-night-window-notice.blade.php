{{--
  Временное уведомление: Яндекс только 00:00–06:00 МСК.
  Снять partial с страниц, когда ограничение снимут и выйдет новость.
  @var string|null $context — relevance|monitoring|both (по умолчанию both)
  @var string|null $extraClass
--}}
@php
    $context = $context ?? 'both';
    $extraClass = $extraClass ?? 'mb-3';
@endphp
<div class="alert alert-warning border cabinet-yandex-night-window-notice {{ $extraClass }}" role="status">
    <p class="mb-1 fw-semibold">
        <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
        Ограничение Яндекса: запросы только с 00:00 до 06:00 (МСК)
    </p>
    <p class="small mb-0">
        Из‑за ограничений со стороны Яндекса сейчас все запросы к нему возможны только ночью, с 00:00 до 06:00 по Москве.
        @if($context === 'relevance')
            Для анализа релевантности рекомендуем использовать <strong>Google</strong>.
        @elseif($context === 'monitoring')
            Мониторинг позиций — переведите съём на <strong>ночное время</strong>.
        @else
            Анализ релевантности — лучше через <strong>Google</strong>; мониторинг позиций — на <strong>ночное снятие</strong>.
        @endif
        Когда Яндекс снимет ограничение, дополнительно напишем в новостях.
    </p>
</div>
