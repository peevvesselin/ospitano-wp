/* First-visit tour of the hotel panel: a card next to each menu item, "Назад / Напред", and "Разбрах" at the end. */
(function () {
	var cfg = window.ospaTour;
	if (!cfg || !cfg.steps || !cfg.steps.length) return;
	var steps = cfg.steps.filter(function (s) { return !s.el || document.querySelector(s.el); });
	var i = 0;

	var shade = document.createElement('div');
	shade.className = 'ospa-tour-shade';
	var ring = document.createElement('div');
	ring.className = 'ospa-tour-ring';
	var card = document.createElement('div');
	card.className = 'ospa-tour-card';
	card.setAttribute('role', 'dialog');
	card.setAttribute('aria-live', 'polite');

	function done() {
		try {
			var body = new FormData();
			body.append('action', 'ospa_tour_done');
			body.append('_ajax_nonce', cfg.nonce);
			fetch(cfg.ajax, { method: 'POST', body: body, credentials: 'same-origin' });
		} catch (e) {}
		shade.remove(); ring.remove(); card.remove();
		document.removeEventListener('keydown', onKey);
		window.removeEventListener('resize', place);
	}

	function target() {
		var s = steps[i];
		var el = s.el ? document.querySelector(s.el) : null;
		if (el && el.closest('li.menu-top')) el = el.closest('li.menu-top');
		if (!el) return null;
		var r = el.getBoundingClientRect();
		return r.width > 0 && r.height > 0 ? el : null;
	}

	function place() {
		var el = target();
		if (!el) {
			ring.style.display = 'none';
			shade.style.display = 'block';
			card.style.left = ''; card.style.top = '';
			card.classList.add('is-center');
			return;
		}
		el.scrollIntoView({ block: 'nearest' });
		var r = el.getBoundingClientRect();
		shade.style.display = 'none';
		ring.style.display = 'block';
		ring.style.top = (r.top - 4) + 'px';
		ring.style.left = (r.left - 4) + 'px';
		ring.style.width = (r.width + 8) + 'px';
		ring.style.height = (r.height + 8) + 'px';
		card.classList.remove('is-center');
		var cw = card.offsetWidth, ch = card.offsetHeight;
		var left = r.right + 16, top = r.top;
		if (left + cw > window.innerWidth - 12) { left = Math.max(12, Math.min(r.left, window.innerWidth - cw - 12)); top = r.bottom + 14; }
		card.style.left = left + 'px';
		card.style.top = Math.max(12, Math.min(top, window.innerHeight - ch - 12)) + 'px';
	}

	function show() {
		var s = steps[i], last = i === steps.length - 1;
		card.innerHTML =
			'<p class="ospa-tour-count">' + (i + 1) + ' от ' + steps.length + '</p>' +
			'<h3></h3><p class="ospa-tour-text"></p>' +
			'<div class="ospa-tour-btns">' +
			(i > 0 ? '<button type="button" class="ospa-tour-back">Назад</button>' : '<button type="button" class="ospa-tour-skip">Пропусни</button>') +
			'<button type="button" class="ospa-tour-next">' + (last ? 'Разбрах' : 'Напред') + '</button></div>';
		card.querySelector('h3').textContent = s.title;
		card.querySelector('.ospa-tour-text').textContent = s.text;
		var back = card.querySelector('.ospa-tour-back'), skip = card.querySelector('.ospa-tour-skip');
		if (back) back.onclick = function () { i--; show(); };
		if (skip) skip.onclick = done;
		var next = card.querySelector('.ospa-tour-next');
		next.onclick = function () { if (last) { done(); } else { i++; show(); } };
		place();
		next.focus();
	}

	function onKey(e) {
		if (e.key === 'Escape') done();
		if (e.key === 'ArrowRight' && i < steps.length - 1) { i++; show(); }
		if (e.key === 'ArrowLeft' && i > 0) { i--; show(); }
	}

	document.body.appendChild(shade);
	document.body.appendChild(ring);
	document.body.appendChild(card);
	document.addEventListener('keydown', onKey);
	window.addEventListener('resize', place);
	show();
})();
