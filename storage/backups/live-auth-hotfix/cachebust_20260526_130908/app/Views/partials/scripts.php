<?php
$baseUrl='';
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
if ($requestPath === '/index.php') {
	$requestPath = '/';
}
if ($requestPath !== '/') {
	$requestPath = rtrim($requestPath, '/');
	if ($requestPath === '') {
		$requestPath = '/';
	}
}
$isCategoryRoute = $requestPath === '/category' || strpos($requestPath, '/category/') === 0;
?>
<?php $assetVersion = '20260519-d'; ?>

<script src="<?=$baseUrl?>/client/assets/js/utils.20260402-1125.js?v=<?= $assetVersion ?>" defer></script>
<script src="<?=$baseUrl?>/client/assets/js/scroll-preserve.js?v=<?= @filemtime(__DIR__ . '/../../../client/assets/js/scroll-preserve.js') ?: $assetVersion ?>" defer></script>

<script src="<?=$baseUrl?>/client/assets/js/cart.js?v=<?= $assetVersion ?>" defer></script>
<script src="<?=$baseUrl?>/client/assets/js/modal.js?v=<?= $assetVersion ?>" defer></script>
<script src="<?=$baseUrl?>/client/assets/js/app.20260402-1125.js?v=<?= $assetVersion ?>" defer></script>
<?php if ($isCategoryRoute): ?>
<script src="<?=$baseUrl?>/client/assets/js/category-hero-v2.js?v=<?= filemtime(__DIR__ . '/../../../client/assets/js/category-hero-v2.js') ?>" defer></script>
<script src="<?=$baseUrl?>/client/category/category.js?v=<?= filemtime(__DIR__ . '/../../../client/category/category.js') ?>" defer></script>
<?php endif; ?>
<script src="<?=$baseUrl?>/client/assets/js/customer-dashboard.js?v=<?= $assetVersion ?>" defer></script>
<script src="<?=$baseUrl?>/client/assets/js/admin.js?v=<?= $assetVersion ?>" defer></script>


<script src="<?=$baseUrl?>/client/assets/js/navbar.js?v=<?= $assetVersion ?>" defer></script>

<script>
(function () {
	const currentPath = window.location.pathname || '/';
	const isAuthRoute = currentPath === '/login' || currentPath === '/b2b/login';
	if (currentPath.startsWith('/admin/')) {
		return;
	}

	const phoneFieldEnhancer = () => {
		const forms = Array.from(document.querySelectorAll('form'));
		forms.forEach((form) => {
			const phoneInputs = Array.from(form.querySelectorAll('input[type="tel"]'));
			phoneInputs.forEach((input, index) => {
				if (input.dataset.countryEnhanced === '1') {
					return;
				}

				const parent = input.parentElement;
				if (!parent) {
					return;
				}

				if (parent.querySelector('select[name*="country_code"], select[id*="CountryCode"], select[id*="countryCode"]')) {
					input.dataset.countryEnhanced = '1';
					return;
				}

				const existingSelectInForm = form.querySelector('select[name*="country_code"][data-for-input="' + (input.name || ('phone_' + index)) + '"]');
				if (existingSelectInForm) {
					input.dataset.countryEnhanced = '1';
					return;
				}

				const COUNTRIES = [
					['\uD83C\uDDEE\uD83C\uDDF3', '+91',  'India'],
					['\uD83C\uDDFA\uD83C\uDDF8', '+1',   'US/Canada'],
					['\uD83C\uDDEC\uD83C\uDDE7', '+44',  'UK'],
					['\uD83C\uDDE6\uD83C\uDDEA', '+971', 'UAE'],
					['\uD83C\uDDF8\uD83C\uDDE6', '+966', 'Saudi Arabia'],
					['\uD83C\uDDF6\uD83C\uDDE6', '+974', 'Qatar'],
					['\uD83C\uDDF0\uD83C\uDDFC', '+965', 'Kuwait'],
					['\uD83C\uDDE7\uD83C\uDDED', '+973', 'Bahrain'],
					['\uD83C\uDDF4\uD83C\uDDF2', '+968', 'Oman'],
					['\uD83C\uDDE6\uD83C\uDDFA', '+61',  'Australia'],
					['\uD83C\uDDF3\uD83C\uDDFF', '+64',  'New Zealand'],
					['\uD83C\uDDE8\uD83C\uDDE6', '+1',   'Canada'],
					['\uD83C\uDDE9\uD83C\uDDEA', '+49',  'Germany'],
					['\uD83C\uDDEB\uD83C\uDDF7', '+33',  'France'],
					['\uD83C\uDDEE\uD83C\uDDF9', '+39',  'Italy'],
					['\uD83C\uDDF3\uD83C\uDDF1', '+31',  'Netherlands'],
					['\uD83C\uDDF8\uD83C\uDDEC', '+65',  'Singapore'],
					['\uD83C\uDDF2\uD83C\uDDFE', '+60',  'Malaysia'],
					['\uD83C\uDDF5\uD83C\uDDED', '+63',  'Philippines'],
					['\uD83C\uDDF3\uD83C\uDDF5', '+977', 'Nepal'],
					['\uD83C\uDDF1\uD83C\uDDF0', '+94',  'Sri Lanka'],
					['\uD83C\uDDE7\uD83C\uDDE9', '+880', 'Bangladesh'],
					['\uD83C\uDDE6\uD83C\uDDEB', '+93',  'Afghanistan'],
					['\uD83C\uDDE7\uD83C\uDDE7', '+1',   'Barbados'],
				];

				const wrap = document.createElement('div');
				wrap.className = 'phone-country-wrap';
				wrap.style.cssText = 'position:relative;display:inline-flex;gap:0;align-items:stretch;width:100%';

				const btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'phone-cc-btn';
				btn.setAttribute('aria-label', 'Select country code');
				btn.style.cssText = 'flex-shrink:0;cursor:pointer;border:1px solid #d0d5dd;border-right:0;border-radius:10px 0 0 10px;padding:0 10px;background:#f9fafb;font-size:0.88rem;white-space:nowrap;color:#374151';

				const hiddenInput = document.createElement('input');
				hiddenInput.type = 'hidden';
				hiddenInput.name = (input.name || ('phone_' + index)) + '_country_code';
				hiddenInput.value = '+91';

				const panel = document.createElement('div');
				panel.className = 'phone-cc-panel';
				panel.style.cssText = 'display:none;position:absolute;top:calc(100% + 4px);left:0;z-index:200;background:#fff;border:1px solid #d0d5dd;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,0.12);min-width:230px;overflow:hidden;flex-direction:column';

				const searchBox = document.createElement('input');
				searchBox.type = 'text';
				searchBox.placeholder = '\uD83D\uDD0D Search country or code…';
				searchBox.style.cssText = 'width:100%;box-sizing:border-box;padding:8px 12px;border:0;border-bottom:1px solid #e5e7eb;outline:none;font-size:0.85rem;background:#fafafa';

				const list = document.createElement('div');
				list.style.cssText = 'overflow-y:auto;max-height:240px';

				function setCountry(flag, code, name) {
					btn.textContent = flag + ' ' + code + ' ▾';
					hiddenInput.value = code;
					panel.style.display = 'none';
				}

				COUNTRIES.forEach(function([flag, code, name]) {
					const opt = document.createElement('button');
					opt.type = 'button';
					opt.style.cssText = 'display:flex;width:100%;text-align:left;align-items:center;gap:8px;padding:8px 12px;border:0;background:transparent;cursor:pointer;font-size:0.86rem;color:#111827';
					opt.innerHTML = '<span style="font-size:1.1em">' + flag + '</span><span style="font-weight:600;flex-shrink:0">' + code + '</span><span style="color:#6b7280">' + name + '</span>';
					opt.dataset.search = (code + ' ' + name).toLowerCase();
					opt.addEventListener('click', function() { setCountry(flag, code, name); });
					opt.addEventListener('pointerover', function() { this.style.background = '#f3f4f6'; });
					opt.addEventListener('pointerout',  function() { this.style.background = 'transparent'; });
					list.appendChild(opt);
				});

				searchBox.addEventListener('input', function() {
					var q = this.value.toLowerCase();
					Array.from(list.querySelectorAll('button')).forEach(function(o) {
						o.style.display = o.dataset.search.indexOf(q) !== -1 ? '' : 'none';
					});
				});

				panel.style.display = 'none';
				panel.appendChild(searchBox);
				panel.appendChild(list);

				// Set India as default
				setCountry(COUNTRIES[0][0], COUNTRIES[0][1], COUNTRIES[0][2]);

				btn.addEventListener('click', function(e) {
					e.stopPropagation();
					var open = panel.style.display !== 'none';
					panel.style.display = open ? 'none' : 'flex';
					panel.style.flexDirection = 'column';
					if (!open) {
						searchBox.value = '';
						searchBox.dispatchEvent(new Event('input'));
						requestAnimationFrame(function() { searchBox.focus(); });
					}
				});

				document.addEventListener('click', function() { panel.style.display = 'none'; }, true);

				input.style.flex = '1';
				input.style.minWidth = '0';
				input.style.borderRadius = '0 10px 10px 0';

				const inlineWrap = document.createElement('div');
				inlineWrap.style.display = 'flex';
				inlineWrap.style.gap = '8px';
				inlineWrap.style.alignItems = 'center';
				inlineWrap.style.width = '100%';

				parent.insertBefore(inlineWrap, input);
				inlineWrap.appendChild(btn);
				inlineWrap.appendChild(hiddenInput);
				inlineWrap.appendChild(input);
				inlineWrap.appendChild(panel);

				if (!String(input.value || '').trim().startsWith('+')) {
					input.placeholder = input.placeholder || '98765 43210';
				}

				input.dataset.countryEnhanced = '1';
				input.dataset.countrySelectName = hiddenInput.name;
			});

			if (form.dataset.phoneCountrySubmitBound === '1') {
				return;
			}

			form.addEventListener('submit', () => {
				Array.from(form.querySelectorAll('input[type="tel"][data-country-enhanced="1"]')).forEach((phoneInput) => {
					const raw = String(phoneInput.value || '').trim();
					if (!raw || raw.startsWith('+')) {
						return;
					}

					const selectName = phoneInput.dataset.countrySelectName;
					if (!selectName) {
						return;
					}

					const codeInput = form.querySelector('input[type="hidden"][name="' + selectName + '"]');
					const code = codeInput ? String(codeInput.value || '+91').trim() : '+91';
					phoneInput.value = code + raw.replace(/^0+/, '');
				});
			});

			form.dataset.phoneCountrySubmitBound = '1';
		});
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', phoneFieldEnhancer);
	} else {
		phoneFieldEnhancer();
	}

	if (document.querySelector('.site-top-offer')) {
		return;
	}

	const startCountdown = (bannerEl) => {
		const countdownEl = bannerEl.querySelector('[data-offer-countdown]');
		if (!countdownEl) {
			return;
		}

		const rawExpiry = countdownEl.getAttribute('data-offer-expires-at') || '';
		const expiryAt = new Date(String(rawExpiry).replace(' ', 'T')).getTime();
		if (!Number.isFinite(expiryAt)) {
			countdownEl.textContent = 'Limited-time offer';
			return;
		}

		const formatCountdown = (remainingMs) => {
			const totalSeconds = Math.max(0, Math.floor(remainingMs / 1000));
			const days = Math.floor(totalSeconds / 86400);
			const hours = Math.floor((totalSeconds % 86400) / 3600);
			const minutes = Math.floor((totalSeconds % 3600) / 60);
			const seconds = totalSeconds % 60;
			if (days > 0) {
				return `${days}d ${hours}h ${minutes}m`;
			}
			if (hours > 0) {
				return `${hours}h ${minutes}m ${seconds}s`;
			}
			if (minutes > 0) {
				return `${minutes}m ${seconds}s`;
			}
			return `${seconds}s`;
		};

		const tick = () => {
			const remainingMs = expiryAt - Date.now();
			if (remainingMs <= 0) {
				countdownEl.textContent = 'Offer ended';
				bannerEl.classList.add('is-expired');
				return;
			}
			countdownEl.textContent = `Ends in ${formatCountdown(remainingMs)}`;
		};

		tick();
		window.setInterval(tick, 1000);
	};

	const attachCopyButtons = (bannerEl) => {
		bannerEl.querySelectorAll('[data-copy-code]').forEach((button) => {
			button.addEventListener('click', async () => {
				const code = button.getAttribute('data-copy-code') || '';
				if (!code || !navigator.clipboard) {
					return;
				}

				try {
					await navigator.clipboard.writeText(code);
					const originalLabel = button.textContent;
					button.textContent = 'Copied';
					window.setTimeout(() => {
						button.textContent = originalLabel;
					}, 1500);
				} catch (error) {
					// Ignore clipboard failures on unsupported browsers.
				}
			});
		});
	};

	const injectBannerHtml = (html) => {
		if (!html || document.querySelector('.site-top-offer')) {
			return false;
		}

		const template = document.createElement('template');
		template.innerHTML = String(html).trim();
		const bannerEl = template.content.querySelector('.site-top-offer');
		if (!bannerEl) {
			return false;
		}

		const headerEl = document.querySelector('.site-header');
		if (headerEl && headerEl.parentNode) {
			headerEl.insertAdjacentElement('afterend', bannerEl);
		} else {
			document.body.insertAdjacentElement('afterbegin', bannerEl);
		}

		template.content.querySelectorAll('script').forEach((scriptEl) => {
			if (scriptEl.textContent) {
				try {
					window.eval(scriptEl.textContent);
				} catch (error) {
					// Ignore bootstrap errors from the injected banner script.
				}
			}
		});

		startCountdown(bannerEl);
		attachCopyButtons(bannerEl);
		return true;
	};

	const injectBannerFromHomepage = () => {
		if (!isAuthRoute || document.querySelector('.site-top-offer')) {
			return Promise.resolve(false);
		}

		return fetch((window.BASE_URL || '') + '/', {
			credentials: 'include',
			headers: { 'Accept': 'text/html' }
		})
			.then((response) => {
				if (!response.ok) {
					return '';
				}
				return response.text();
			})
			.then((html) => {
				if (!html) {
					return false;
				}

				const parser = new DOMParser();
				const doc = parser.parseFromString(html, 'text/html');
				const offerNode = doc.querySelector('.site-top-offer');
				return offerNode ? injectBannerHtml(offerNode.outerHTML) : false;
			})
			.catch(() => false);
	};

	fetch((window.BASE_URL || '') + '/api/site-top-offer', {
		credentials: 'include',
		headers: { 'Accept': 'application/json' }
	})
		.then((response) => response.json())
		.then((payload) => {
			const injected = payload && payload.success && payload.data && payload.data.html
				? injectBannerHtml(payload.data.html)
				: false;

			if (!injected) {
				return injectBannerFromHomepage();
			}

			return true;
		})
		.catch(() => {
			injectBannerFromHomepage();
		});
})();
</script>





</body>
</html>
