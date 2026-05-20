<?php $baseUrl='';
 ?>
<?php $assetVersion = '20260519-d'; ?>

<script src="<?=$baseUrl?>/client/assets/js/utils.20260402-1125.js?v=<?= $assetVersion ?>" defer></script>

<script src="<?=$baseUrl?>/client/assets/js/cart.js?v=<?= $assetVersion ?>" defer></script>
<script src="<?=$baseUrl?>/client/assets/js/modal.js?v=<?= $assetVersion ?>" defer></script>
<script src="<?=$baseUrl?>/client/assets/js/app.20260402-1125.js?v=<?= $assetVersion ?>" defer></script>
<script src="<?=$baseUrl?>/client/assets/js/admin.js?v=<?= $assetVersion ?>" defer></script>


<script src="<?=$baseUrl?>/client/assets/js/navbar.js?v=<?= $assetVersion ?>" defer></script>

<script>
(function () {
	const currentPath = window.location.pathname || '/';
	const isAuthRoute = currentPath === '/login' || currentPath === '/b2b/login';
	if (currentPath.startsWith('/admin/')) {
		return;
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

	const injectBannerFromLegacyPlacement = () => {
		if (!isAuthRoute || document.querySelector('.site-top-offer')) {
			return Promise.resolve(false);
		}

		return fetch((window.BASE_URL || '') + '/api/banners?placement=site_top_offer', {
			credentials: 'include',
			headers: { 'Accept': 'application/json' }
		})
			.then((response) => response.json())
			.then((payload) => {
				const record = Array.isArray(payload?.data) ? payload.data[0] : null;
				if (!record || !record.is_active) {
					return false;
				}

				const title = String(record.title || 'Limited-time Offer');
				const subtitle = String(record.subtitle || '');
				const ctaLabel = String(record.cta_label || 'Shop Now');
				const ctaUrl = String(record.cta_url || '/shop');
				const endsAt = String(record.ends_at || '');

				const html = [
					'<section class="site-top-offer" aria-label="Limited time offer">',
					'  <div class="site-top-offer__inner">',
					`    <span class="site-top-offer__title">${title.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</span>`,
					subtitle ? `    <span class="site-top-offer__subtitle">${subtitle.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</span>` : '',
					endsAt ? `    <span class="site-top-offer__countdown" data-offer-countdown data-offer-expires-at="${endsAt.replace(/"/g, '&quot;')}">Loading offer timer...</span>` : '',
					`    <a href="${ctaUrl.replace(/"/g, '&quot;')}" class="site-top-offer__cta">${ctaLabel.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</a>`,
					'  </div>',
					'</section>'
				].join('\n');

				return injectBannerHtml(html);
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
				return injectBannerFromLegacyPlacement().then((legacyInjected) => {
					if (legacyInjected) {
						return true;
					}
					return injectBannerFromHomepage();
				});
			}

			return true;
		})
		.catch(() => {
			injectBannerFromLegacyPlacement().then((legacyInjected) => {
				if (!legacyInjected) {
					injectBannerFromHomepage();
				}
			});
		});
})();
</script>





</body>
</html>
