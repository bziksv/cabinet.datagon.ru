/**
 * Interactive tour for Site Audit.
 * Всегда сам открывает workspace (Расширенный), иначе шаги без подсветки.
 */
(function () {
	'use strict';

	var STORAGE_KEY = 'cabinet_sa_tour_seen_v3';
	var PAD = 10;
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

	function isShown(el) {
		if (!el) {
			return false;
		}
		if (el.hasAttribute && el.hasAttribute('hidden')) {
			return false;
		}
		if (el.closest && el.closest('[hidden]')) {
			return false;
		}
		var node = el;
		while (node && node.nodeType === 1) {
			var st = window.getComputedStyle(node);
			if (st.display === 'none' || st.visibility === 'hidden' || Number(st.opacity) === 0) {
				return false;
			}
			node = node.parentElement;
		}
		var r = el.getBoundingClientRect();
		return r.width > 2 && r.height > 2;
	}

	function firstVisible(selectors) {
		for (var i = 0; i < selectors.length; i++) {
			var list = document.querySelectorAll(selectors[i]);
			for (var j = 0; j < list.length; j++) {
				if (isShown(list[j])) {
					return list[j];
				}
			}
		}
		return null;
	}

	/** Жёстко открыть рабочий экран + pro — без надежды на click-хендлеры. */
	function ensureTourContext() {
		var page = document.querySelector('.cabinet-sa-page');
		var stepMode = document.getElementById('sa-step-mode');
		var stepWork = document.getElementById('sa-step-workspace');

		if (stepMode) {
			stepMode.hidden = true;
			stepMode.setAttribute('hidden', '');
		}
		if (stepWork) {
			stepWork.hidden = false;
			stepWork.removeAttribute('hidden');
			stepWork.style.display = '';
		}
		if (page) {
			page.classList.remove('cabinet-sa-page--lite', 'cabinet-sa-page--choosing');
			page.classList.add('cabinet-sa-page--pro');
			page.setAttribute('data-sa-forced-tour', '1');
		}

		document.querySelectorAll('[data-sa-switch-mode]').forEach(function (btn) {
			var on = btn.getAttribute('data-sa-switch-mode') === 'pro';
			btn.classList.toggle('is-active', on);
			btn.setAttribute('aria-pressed', on ? 'true' : 'false');
		});

		document.querySelectorAll('details[data-sa-tour], details.cabinet-sa-section, details.cabinet-sa-site__more').forEach(function (d) {
			d.open = true;
		});

		try {
			localStorage.setItem('cabinet-sa-ui-mode', 'pro');
			localStorage.setItem('cabinet-sa-ui-mode-picked', '1');
		} catch (e) {}

		// синхронизация с page JS, если есть
		var switchPro = qs('[data-sa-switch-mode="pro"]');
		if (switchPro) {
			try {
				switchPro.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
			} catch (e2) {
				switchPro.click();
			}
		}
	}

	function openScheduleForTour() {
		var form = firstVisible([
			'[data-sa-tour="schedule"]',
			'.cabinet-sa-site__schedule',
			'.cabinet-sa-project__schedule'
		]);
		if (!form) {
			return;
		}
		var details = form.closest('details');
		if (details) {
			details.open = true;
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
			text: 'Короткий тур: форма запуска, сайты, расписание, история. Esc — выйти.',
			center: true
		},
		{
			id: 'domains',
			title: 'Новый краул',
			text: 'Сюда вводите домен (или несколько — с новой строки). Это старт проверки.',
			find: function () {
				return firstVisible(['[data-sa-tour="domains"]', '#sa-domain', '[data-sa-tour="new-crawl"]']);
			},
			placement: 'right'
		},
		{
			id: 'speed',
			title: 'Скорость, потоки и лимит',
			text: 'Пресеты или ручная настройка. На чужих сайтах — 1 поток и обычная скорость. Лимит — сколько страниц снять.',
			find: function () {
				var speed = firstVisible(['[data-sa-tour="speed"]', '#sa-speed', '.cabinet-sa-presets']);
				if (speed && speed.tagName === 'DETAILS') {
					speed.open = true;
				}
				return speed || firstVisible(['[data-sa-tour="new-crawl"]']);
			},
			placement: 'right'
		},
		{
			id: 'projects',
			title: 'Ваши сайты',
			text: 'Список проектов после первого краула: отчёт, команда, авторасписание.',
			find: function () {
				return firstVisible([
					'[data-sa-tour="projects"]',
					'.cabinet-sa-sites',
					'.cabinet-sa-site',
					'.cabinet-sa-project'
				]) || firstVisible(['[data-sa-tour="new-crawl"]']);
			},
			placement: 'left'
		},
		{
			id: 'schedule',
			title: 'Авторасписание',
			text: 'В настройках сайта — день, час (МСК) и параметры краула. Пик 11–14 недоступен.',
			find: function () {
				openScheduleForTour();
				return firstVisible([
					'[data-sa-tour="schedule"]',
					'.cabinet-sa-site__schedule',
					'.cabinet-sa-project__schedule',
					'.cabinet-sa-site__more',
					'[data-sa-tour="projects"]'
				]);
			},
			placement: 'left'
		},
		{
			id: 'history',
			title: 'История краулов',
			text: 'Прошлые прогоны: статус, прогресс, проблемы. Поиск по домену справа.',
			find: function () {
				return firstVisible(['[data-sa-tour="history"]', '#sa-history', '.cabinet-sa-history']);
			},
			placement: 'top'
		},
		{
			id: 'done',
			title: 'Готово!',
			text: 'Снова открыть тур — «Как это работает?» у формы запуска.',
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
			if (!t || !t.closest) {
				return;
			}
			if (t.closest('[data-tour-next]')) {
				e.preventDefault();
				go(1);
			} else if (t.closest('[data-tour-prev]')) {
				e.preventDefault();
				go(-1);
			} else if (t.closest('[data-tour-skip]') || t.closest('.sa-tour-card__close')) {
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
			top = rect.top + Math.min(rect.height / 2, 80) - ch / 2;
			left = rect.left - cw - 14;
		} else {
			top = rect.top + Math.min(rect.height / 2, 80) - ch / 2;
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
		if (!el || !isShown(el)) {
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

		// не подсвечивать «всю страницу» — бессмысленно
		if (w > window.innerWidth * 0.92 && h > window.innerHeight * 0.7) {
			spot.hidden = true;
			dim.style.clipPath = 'none';
			placeCard(rect, placement || 'bottom');
			return;
		}

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

		ensureTourContext();

		var el = null;
		if (typeof step.find === 'function') {
			el = step.find();
		}

		card.querySelector('.sa-tour-card__step').textContent = 'Шаг ' + (stepIndex + 1) + ' из ' + STEPS.length;
		card.querySelector('.sa-tour-card__title').textContent = step.title;
		card.querySelector('.sa-tour-card__text').textContent = step.text;

		var prevBtn = card.querySelector('[data-tour-prev]');
		var nextBtn = card.querySelector('[data-tour-next]');
		prevBtn.style.display = stepIndex > 0 ? '' : 'none';
		nextBtn.textContent = stepIndex >= STEPS.length - 1 ? 'Готово' : 'Далее';

		if (el && !step.center) {
			try {
				el.scrollIntoView({ block: 'center', behavior: 'instant', inline: 'nearest' });
			} catch (err) {
				try {
					el.scrollIntoView(true);
				} catch (err2) {}
			}
			window.requestAnimationFrame(function () {
				window.requestAnimationFrame(function () {
					highlight(el, step.placement || 'bottom');
				});
			});
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
		ensureTourContext();
		buildOverlay();
		active = true;
		stepIndex = 0;
		document.documentElement.classList.add('sa-tour-active');
		overlay.style.display = '';
		document.addEventListener('keydown', onKey);
		window.addEventListener('resize', onResize);
		window.addEventListener('scroll', onResize, true);
		window.setTimeout(function () {
			ensureTourContext();
			renderStep();
		}, 120);
	}

	function stopTour() {
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

		function tryAutoStart() {
			if (seen || active || window.location.hash === '#sa-history') {
				return;
			}
			// сначала открываем workspace, потом тур — иначе пустые шаги
			ensureTourContext();
			window.setTimeout(function () {
				if (!active) {
					startTour();
				}
			}, 450);
		}

		if (!seen) {
			window.setTimeout(tryAutoStart, 700);
		}

		document.addEventListener('cabinet-sa-workspace-ready', function () {
			try {
				seen = window.localStorage.getItem(STORAGE_KEY) === '1';
			} catch (e) {}
			if (!seen && !active) {
				window.setTimeout(tryAutoStart, 400);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bind);
	} else {
		bind();
	}
})();
