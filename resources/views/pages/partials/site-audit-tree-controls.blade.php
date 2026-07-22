{{-- Поиск отчёта по названию + пресеты приоритета. Ожидает родителя .cabinet-sa-tree[data-sa-tree]. --}}
<div class="cabinet-sa-tree-controls">
    <input type="search"
           class="form-control form-control-sm cabinet-sa-tree-search"
           placeholder="Найти отчёт по названию… например «4xx» или «title»"
           title="Печатайте — слева останутся только подходящие пункты меню"
           autocomplete="off"
           aria-label="Поиск отчёта по названию">
    <div class="cabinet-sa-tree-presets" role="group" aria-label="Пресеты отчётов">
        <button type="button" class="cabinet-sa-tree-preset is-active" data-preset="all"
                title="Показать все пункты меню слева">Все</button>
        <button type="button" class="cabinet-sa-tree-preset" data-preset="hot"
                title="Только где число больше нуля — где реально есть проблемы">С находками</button>
        <button type="button" class="cabinet-sa-tree-preset" data-preset="critical"
                title="Только самые срочные ошибки (красные)">Грубые</button>
        <button type="button" class="cabinet-sa-tree-preset" data-preset="other"
                title="Средняя срочность">Прочие</button>
        <button type="button" class="cabinet-sa-tree-preset" data-preset="warning"
                title="Предупреждения — желательно починить">Замечания</button>
        <button type="button" class="cabinet-sa-tree-preset" data-preset="info"
                title="Информация — полезно знать, не всегда срочно">Инфо</button>
    </div>
    <div class="cabinet-sa-tree-controls__hint text-muted small mt-1">
        Слева — список проверок. Цифра справа — сколько проблем. Зелёный ноль = всё ок.
    </div>
</div>
