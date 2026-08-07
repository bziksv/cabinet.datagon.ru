/**
 * Interactive tour for Site Audit (same pattern as metplus basket-tour).
 */
(function () {
	'use strict';

	var STORAGE_KEY = 'cabinet_sa_tour_seen_v1';
	var PAD = 8;
	var active = false;
	var stepIndex = 0;
	var overlay = null;
	var dim = null;
	var spot = null;
	var card = null;
	var openedSchedule = null;

	function qs(sel, root) {
		return (root || document).querySelector(sel);
	}

	function firstVisible(selectors) {
		for (var i = 0; i < selectors.length; i++) {
			var el = qs(selectors[i]);
			if (!el) {
				continue;
			}
			var r = el.getBoundingClientRect();
			if (r.width > 0 || r.height > 0) {
				return el;
			}
		}
		return null;
	}

	function openScheduleForTour() {
		var form = qs('.cabinet-sa-project__schedule');
		if (!form) {
			return;
		}
		var cb = form.querySelector('input[name="enabled"]');
		if (!cb || cb.checked) {
			return;
		}
		openedSchedule = cb;
		cb.checked = true;
		cb.dispatchEvent(new Event('change', { bubbles: true }));
	}

	function restoreSchedule() {
		if (!openedSchedule) {
			return;
		}
		openedSchedule.checked = false;
		openedSchedule.dispatchEvent(new Event('change', { bubbles: true }));
		openedSchedule = null;
	}

	var STEPS = [
		{
			id: 'welcome',
			title: 'Как пользоваться аудитом',
			text: 'Короткий тур по форме запуска, проектам, авторасписанию и истории. Можно выйти в любой момент (Esc).',
			center: true
		},
		{
			id: 'domains',
			title: 'Новый краул',
			text: 'Домены — по одному на строку; каждый сайт = свой проект. «Страницы / доп. URL» — точечные URL. Галочка «только эти страницы» — без sitemap и без дообхода по ссылкам (разные домены всё равно разъедутся по проектам).',
			find: function () {
				return firstVisible(['[data-sa-tour="domains"]', '#sa-domain']);
			},
			placement: 'right'
		},
		{
			id: 'speed',
			title: 'Скорость, потоки и лимит',
			text: 'Скорость — лимит запросов в секунду на один поток. Потоки — сколько запросов идёт параллельно. Итоговая нагрузка ≈ потоки × скорость. Не ставьте сразу много потоков и «турбо»: хостинги и антибот режут такие обходы (403/429, капча, бан IP). Для чужих сайтов начинайте с 1 потока и обычной/медленной скорости. Лимит URL — сколько страниц снять в этом крауле (не больше тарифа).',
			find: function () {
				return firstVisible(['[data-sa-tour="speed"]', '#sa-speed']);
			},
			placement: 'right'
		},
		{
			id: 'projects',
			title: 'Проекты',
			text: 'Список сайтов после первого краула: последний прогон, отчёт, удаление. Сверху — сколько проектов и слотов авторасписания осталось по тарифу.',
			find: function () {
				return firstVisible(['[data-sa-tour="projects"]', '.cabinet-sa-project']);
			},
			placement: 'left',
			fallbackTitle: 'Проекты появятся после первого краула',
			fallbackText: 'Запустите краул слева — домен попадёт в список проектов. Тогда можно включить авторасписание.'
		},
		{
			id: 'schedule',
			title: 'Авторасписание',
			text: 'Галочка открывает настройки: частота, день недели, час (МСК) и те же скорость/потоки/лимит, что у ручного краула. Часы 11–14 недоступны (пик нагрузки). Free — 0 слотов; Optimal 2 / Ultimate 5 / Maximum 10. Каждый автозапуск списывает краул из месячного лимита.',
			find: function () {
				openScheduleForTour();
				return firstVisible([
					'[data-sa-tour="schedule"]',
					'.cabinet-sa-project__schedule',
					'.cabinet-sa-project'
				]);
			},
			placement: 'left',
			fallbackTitle: 'Авторасписание',
			fallbackText: 'После появления проекта здесь будет галочка «Авторасписание»: день недели, час МСК и параметры краула.'
		},
		{
			id: 'history',
			title: 'История краулов',
			text: 'Статусы и прогресс, поиск по домену. В колонке настроек — потоки, скорость и лимит URL (с пробелами: 100 000). После окончания платного тарифа история хранится ещё 14 дней, затем удаляется.',
			find: function () {
				return firstVisible(['[data-sa-tour="history"]', '#sa-history']);
			},
			placement: 'top'
		},
		{
			id: 'done',
			title: 'Готово!',
			text: 'Тур можно запустить снова кнопкой «Как пользоваться…» вверху страницы.',
			center: true
		}
	];

	function buildOverlay() {
		if (overlay) {
			return;
		}

		overlay = document.createElement('div');
		overlay.className = 'sa-tour-overlay is-interactive';
		overlay.setAttribute('aria-live', 'polite');

		dim = document.createElement('div');
		dim.className = 'sa-tour-dim';

		spot = document.createElement('div');
		spot.className = 'sa-tour-spot';
		spot.hidden = true;

		card = document.createElement('div');
		card.className = 'sa-tour-card';
		card.setAttribute('role', 'dialog');
		card.setAttribute('aria-modal', 'true');
		card.innerHTML =
			'<div class="sa-tour-card__top">' +
				'<div class="sa-tour-card__step"></div>' +
				'<button type="button" class="sa-tour-card__close" aria-label="Закрыть тур">&times;</button>' +
			'</div>' +
			'<h3 class="sa-tour-card__title"></h3>' +
			'<p class="sa-tour-card__text"></p>' +
			'<div class="sa-tour-card__actions">' +
				'<button type="button" class="sa-tour-btn" data-tour-prev>Назад</button>' +
				'<button type="button" class="sa-tour-btn sa-tour-btn--primary" data-tour-next>Далее</button>' +
				'<button type="button" class="sa-tour-btn sa-tour-btn--ghost" data-tour-skip>Выйти</button>' +
			'</div>';

		overlay.appendChild(dim);
		overlay.appendChild(spot);
		overlay.appendChild(card);
		document.body.appendChild(overlay);

		card.addEventListener('click', function (e) {
			var t = e.target;
			if (!t) {
				return;
			}
			if (t.closest && t.closest('[data-tour-next]')) {
				e.preventDefault();
				go(1);
			} else if (t.closest && t.closest('[data-tour-prev]')) {
				e.preventDefault();
				go(-1);
			} else if (t.closest && (t.closest('[data-tour-skip]') || t.closest('.sa-tour-card__close'))) {
				e.preventDefault();
				stopTour();
			}
		});
	}

	function placeCard(rect, placement) {
		var cw = card.offsetWidth || 360;
		var ch = card.offsetHeight || 180;
		var vw = window.innerWidth;
		var vh = window.innerHeight;
		var left;
		var top;

		if (!rect || placement === 'center') {
			card.classList.add('sa-tour-card--center');
			card.style.left = '';
			card.style.top = '';
			card.style.right = '';
			card.style.bottom = '';
			return;
		}

		card.classList.remove('sa-tour-card--center');

		if (placement === 'bottom') {
			top = rect.bottom + 14;
			left = rect.left + rect.width / 2 - cw / 2;
		} else if (placement === 'top') {
			top = rect.top - ch - 14;
			left = rect.left + rect.width / 2 - cw / 2;
		} else if (placement === 'left') {
			top = rect.top + rect.height / 2 - ch / 2;
			left = rect.left - cw - 14;
		} else {
			top = rect.top + rect.height / 2 - ch / 2;
			left = rect.right + 14;
		}

		left = Math.max(12, Math.min(left, vw - cw - 12));
		top = Math.max(12, Math.min(top, vh - ch - 12));
		card.style.left = left + 'px';
		card.style.top = top + 'px';
		card.style.right = 'auto';
		card.style.bottom = 'auto';
	}

	function highlight(el, placement) {
		if (!el) {
			spot.hidden = true;
			spot.removeAttribute('data-arrow');
			dim.style.clipPath = 'none';
			placeCard(null, 'center');
			return;
		}

		var rect = el.getBoundingClientRect();
		var x = Math.max(0, rect.left - PAD);
		var y = Math.max(0, rect.top - PAD);
		var w = Math.min(window.innerWidth - x, rect.width + PAD * 2);
		var h = Math.min(window.innerHeight - y, rect.height + PAD * 2);

		spot.hidden = false;
		spot.setAttribute('data-arrow', placement === 'center' ? '' : (placement || 'bottom'));
		spot.style.left = x + 'px';
		spot.style.top = y + 'px';
		spot.style.width = w + 'px';
		spot.style.height = h + 'px';

		dim.style.clipPath = 'polygon(0% 0%, 100% 0%, 100% 100%, 0% 100%, 0% 0%, '
			+ x + 'px ' + y + 'px, '
			+ x + 'px ' + (y + h) + 'px, '
			+ (x + w) + 'px ' + (y + h) + 'px, '
			+ (x + w) + 'px ' + y + 'px, '
			+ x + 'px ' + y + 'px)';

		placeCard(rect, placement || 'bottom');
	}

	function renderStep() {
		var step = STEPS[stepIndex];
		if (!step) {
			stopTour();
			return;
		}

		var el = null;
		var title = step.title;
		var text = step.text;

		if (typeof step.find === 'function') {
			el = step.find();
			if (!el && step.fallbackTitle) {
				title = step.fallbackTitle;
				text = step.fallbackText || text;
			}
		}

		card.querySelector('.sa-tour-card__step').textContent = 'Шаг ' + (stepIndex + 1) + ' из ' + STEPS.length;
		card.querySelector('.sa-tour-card__title').textContent = title;
		card.querySelector('.sa-tour-card__text').textContent = text;

		var prevBtn = card.querySelector('[data-tour-prev]');
		var nextBtn = card.querySelector('[data-tour-next]');
		prevBtn.style.display = stepIndex > 0 ? '' : 'none';
		nextBtn.textContent = stepIndex >= STEPS.length - 1 ? 'Готово' : 'Далее';

		if (el && !step.center) {
			try {
				el.scrollIntoView({ block: 'center', behavior: 'smooth', inline: 'nearest' });
			} catch (err) {}
			window.setTimeout(function () {
				highlight(el, step.placement || 'bottom');
			}, 220);
		} else {
			highlight(null, 'center');
		}
	}

	function go(delta) {
		if (delta > 0 && stepIndex >= STEPS.length - 1) {
			stopTour();
			return;
		}
		stepIndex = Math.max(0, Math.min(STEPS.length - 1, stepIndex + delta));
		renderStep();
	}

	function onKey(e) {
		if (!active) {
			return;
		}
		if (e.key === 'Escape') {
			e.preventDefault();
			stopTour();
		} else if (e.key === 'ArrowRight' || e.key === 'Enter') {
			e.preventDefault();
			go(1);
		} else if (e.key === 'ArrowLeft') {
			e.preventDefault();
			go(-1);
		}
	}

	function onResize() {
		if (active) {
			renderStep();
		}
	}

	function markSeen() {
		try {
			window.localStorage.setItem(STORAGE_KEY, '1');
		} catch (e) {}
	}

	function startTour() {
		buildOverlay();
		active = true;
		stepIndex = 0;
		document.documentElement.classList.add('sa-tour-active');
		overlay.style.display = '';
		document.addEventListener('keydown', onKey);
		window.addEventListener('resize', onResize);
		window.addEventListener('scroll', onResize, true);
		renderStep();
	}

	function stopTour(completed) {
		active = false;
		document.documentElement.classList.remove('sa-tour-active');
		document.removeEventListener('keydown', onKey);
		window.removeEventListener('resize', onResize);
		window.removeEventListener('scroll', onResize, true);
		restoreSchedule();
		if (overlay) {
			overlay.style.display = 'none';
			spot.hidden = true;
			dim.style.clipPath = 'none';
		}
		markSeen();
	}

	function bind() {
		var btn = document.getElementById('sa-tour-start');
		if (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				startTour();
			});
		}

		var seen = false;
		try {
			seen = window.localStorage.getItem(STORAGE_KEY) === '1';
		} catch (e) {}

		if (!seen && window.location.hash !== '#sa-history') {
			window.setTimeout(function () {
				if (!active) {
					startTour();
				}
			}, 600);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bind);
	} else {
		bind();
	}
})();
