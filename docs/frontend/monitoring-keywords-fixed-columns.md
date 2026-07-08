# Таблица ключевых слов мониторинга: FixedColumns (эталон fc21)

Страница: `/monitoring/{id}#keywords` (пример: `/monitoring/77#keywords`).

**Это проверенный «идеальный» вариант.** Не переписывать на CSS `position: sticky` и не «упрощать» без чтения этого документа — иначе снова получите наложение колонок, щель между «Запрос» и «URL», гигантские строки и отставание при скролле.

---

## Симптомы, если сломать

| Симптом | Типичная причина |
|--------|-------------------|
| URL/группы наезжают на «Запрос» при горизонтальном скролле | Вернули CSS sticky вместо FixedColumns |
| Щель 50–200px между FC-блоком и первой видимой колонкой | Скрытые колонки в scroll-body занимают ширину; нет `applyScrollTableFcInset`; зум 80% без `visualViewport` relayout |
| Строки 200–280px высотой | Скрытые ячейки `width:0` **без** `display:none` на дочерних элементах — текст переносится вертикально |
| Строки «разъезжаются» по вертикали при hover/скролле | Не вызывается `syncMonTableRowHeights` или FC `heightMatch` конфликтует с inline height |
| Левая часть «догоняет» правую при быстром скролле | Нет rAF-sync; не отключён `scroll.DTFC` у плагина; wheel без немедленного sync |

---

## Архитектура (кратко)

```
┌─────────────────────────────────────────────────────────────┐
│  DTFC_LeftWrapper (FC-клон: чекбокс, кнопки, «Запрос»)      │
│  z-index 12, фиксированная ширина ~488px                    │
├─────────────────────────────────────────────────────────────┤
│  .dataTables_scrollBody                                       │
│    table margin-left = ширина FC ( --mon-scroll-edge-nudge )  │
│    скрытые col 0..2: width 0, children display:none         │
│    edge col (первая видимая, URL): cabinet-mon-scroll-edge  │
│    + горизонтальный scrollX                                  │
└─────────────────────────────────────────────────────────────┘
```

**Два слоя DOM:** FixedColumns рисует левый блок; основная таблица в scrollX содержит те же колонки, но первые N скрыты (placeholder для colgroup), контент сдвинут `margin-left`.

---

## Файлы (не трогать разрозненно)

| Файл | Роль |
|------|------|
| `resources/views/monitoring/show.blade.php` | DataTables init: `scrollX`, `fixedColumns: { leftColumns, heightMatch: 'auto' }`, assets FC, `finalizeMonTableLayout` в `initComplete` / unveil |
| `public/js/cabinet-monitoring-show-chrome.js` | Вся логика layout, inset, row heights, scroll sync |
| `public/css/cabinet-monitoring-show.css` | Блок `.DTFC_*`, hidden columns, edge col, hover `is-row-hover` (не Bootstrap `table-hover` на FC) |
| `public/plugins/datatables-fixedcolumns/` | Плагин FixedColumns (не удалять) |

Cache-bust: суффикс `-fc21` на CSS/JS в `show.blade.php` (при правках увеличивать: `-fc22`…).

---

## Порядок layout (обязательный)

Вызывается из `finalizeMonTableLayout()`:

1. `destroyFixedColumns` → `enforceMonColumnWidths` → `ensureFixedColumns` → `fixedColumns().relayout()`
2. `syncFixedLeftBlock(api)` — ширины FC, классы hidden/edge, inset, row heights
3. `wireMonTableRowHover` — класс `is-row-hover` на обеих половинах
4. Двойной `requestAnimationFrame`: `remeasureMainTableLeftHiddenWidths` + `relayoutMonTableFcLayout`

### `syncFixedLeftBlock` внутри

1. Colgroup/ширины FC-клона (`buildFixedLeftColgroup`, `monFixedLeftCount` = индекс колонки `query` + 1, обычно 3)
2. `hideMainTableLeftColumns` — классы `cabinet-mon-scrollhead-left-hidden`, `cabinet-mon-scrollbody-left-hidden`, `cabinet-mon-scroll-edge-col` на первой **видимой** колонке после leftCount
3. `syncMainTableLeftHiddenWidths` — colgroup с `0px` для скрытых; inline zero width
4. `applyScrollTableFcInset` — `margin-left` scroll-таблиц = `getBoundingClientRect().width` FC ± коррекция gap
5. `clearMonTableRowInlineHeights` → `syncMonTableRowHeights`
6. `syncMonTableScrollPositions` + `muteFcVerticalScrollHandlers` если scroll уже wired

### `applyScrollTableFcInset`

- Измеряет gap: `edgeCol.left - fcWrapper.right`
- `margin-left` на `.dataTables_scrollHeadInner table` и `.dataTables_scrollBody table`
- CSS fallback: `margin-left: var(--mon-scroll-edge-nudge, 0)`
- **Положительный** inset (~488px), не отрицательный «nudge» от старых итераций

### `syncMainTableLeftHiddenWidths`

- Скрытые колонки в **scroll-body**: ширина **0**
- CSS **обязательно** скрывает содержимое ячеек:

```css
.cabinet-mon-scrollbody-left-hidden > * { display: none !important; }
```

Без этого — гигантские строки.

### `syncMonTableRowHeights`

- Попарно main row ↔ FC row, `height = max(…)`, двухпроходная подстройка
- Если высота > 120px — берётся только FC (защита от битого main)

---

## Вертикальный скролл (fc21)

Плагин FC вешает `scroll.DTFC` и дублирует `scrollTop` → микролаг.

**Наш sync (`wireMonTableScrollSync`):**

1. `muteFcVerticalScrollHandlers` — `off('scroll.DTFC')` на main scroller и left liner (после каждого FC relayout!)
2. Capture `scroll` → `kickMonTableScrollSync` → rAF-цикл (480ms tail)
3. Bubble `scroll` → sync + rAF sync
4. `wheel` (passive: **false**) на scroll-body: `scrollTop += deltaY` + sync в том же тике
5. `touchmove`, `scrollend` — финальная подтяжка
6. `force` sync: всегда пишет `leftLiner.scrollTop = st` + `void leftLiner.scrollHeight`

**Не использовать:** `transform` на `<table>` / `<tbody>` для sync — в Chrome не двигает строки; ломает выравнвание.

---

## Зум браузера (80% и т.д.)

`resize` **не всегда** срабатывает при zoom. Есть:

- `visualViewport` `resize` / `scroll` → `scheduleMonTableViewportRelayout` → `relayoutMonTableFcLayout`

Без этого на 80% появляется щель между «Запрос» и «URL».

---

## Hover строк

- Bootstrap `table-hover` на FC **отключён** в CSS
- Только класс `is-row-hover` на **обеих** половинах по index (`wireMonTableRowHover`)

---

## Что НЕ делать (чёрный список)

1. ❌ CSS `position: sticky` для левых колонок внутри `scrollX`
2. ❌ Скрытые колонки `width:0` без `display:none` на `> *`
3. ❌ Отрицательный `margin-left` как единственный способ закрыть щель
4. ❌ `transform: translateY` на `<table>` для scroll sync
5. ❌ Оставлять `scroll.DTFC` плагина + свой sync без `muteFcVerticalScrollHandlers`
6. ❌ Задавать ширину скрытых колонок = номинал (46+62+380) в scroll-body — будет gap ~84px vs FC wrapper 488px
7. ❌ Менять только CSS или только JS — layout всегда парой
8. ❌ `enforceMonColumnWidths` без skip left cols при `_oFixedColumns` (см. `applyPlanColumnWidths`)

---

## Чеклист при баге

1. Hard refresh (Cmd+Shift+R), версия assets `…-fc21`
2. DevTools: gap `edgeCol.left - fcWrapper.right` ≈ 0; row height ≈ 42px
3. `--mon-scroll-edge-nudge` / `table.style.marginLeft` ≈ ширине FC (~488px)
4. После zoom 80%: вызвать relayout или перезагрузить — gap должен ≈ 0
5. `leftLiner.scrollTop === scrollBody.scrollTop` при скролле
6. Нет ли регрессии: вернули sticky / убрали `display:none` на hidden children

---

## Как повторить для другой таблицы

1. DataTables + `scrollX` + FixedColumns `leftColumns: N`, `heightMatch: 'auto'`
2. Скопировать паттерн из `cabinet-monitoring-show-chrome.js` (функции `ensureFixedColumns`, `syncFixedLeftBlock`, `applyScrollTableFcInset`, `wireMonTableScrollSync`, `finalizeMonTableLayout`)
3. Скопировать CSS-блок FixedColumns из `cabinet-monitoring-show.css` (секция «FixedColumns»)
4. Маркировать hidden/edge колонки классами в JS
5. Отключить нативный hover на FC; sync hover по index
6. После FC `relayout()` — снова `muteFcVerticalScrollHandlers`
7. Проверить: 100% zoom, 80% zoom, fast scroll, horizontal scroll + hover

---

## История (почему fc21)

| Итерация | Проблема |
|----------|----------|
| CSS sticky | Overlap при scrollX + hover |
| FC + scrollTop only | Lag при fast scroll |
| FC + transform on table | Transform не работает на table; misalign |
| Hidden cols full width | Gap 84px (572 vs 488) |
| Hidden width 0 без display:none | Rows 280px |
| Negative margin nudge only | Ломается на zoom 80% |
| **fc21** | width 0 + display:none + positive inset + mute FC scroll + rAF/wheel sync + visualViewport |

**Baseline tag: `fc21`** — зафиксирован 2026-07-07 как production-ready для kawe.su / monitoring keywords.
