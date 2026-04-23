/* FIN TOKHROJ v3 — Spectacular Animation Engine */
document.addEventListener('DOMContentLoaded', () => {
    const settingsUserHolder = document.querySelector('[data-settings-user-key]');
    const settingsUserKey = settingsUserHolder?.getAttribute('data-settings-user-key') || 'guest';
    const scopedStorageKey = (name) => `ft-${settingsUserKey}-${name}`;

    /* ── THEME TOGGLE (PERSISTENT) ───────────────────────── */
    const root = document.documentElement;
    const themeToggleBtn = document.querySelector('[data-theme-toggle]');
    const storedTheme = localStorage.getItem(scopedStorageKey('theme')) || localStorage.getItem('ft-theme');

    if (storedTheme === 'dark' || storedTheme === 'light') {
        root.setAttribute('data-theme', storedTheme);
    }

    const syncThemeIcon = () => {
        if (!themeToggleBtn) return;
        const current = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        themeToggleBtn.textContent = current === 'dark' ? '☀' : '◐';
        themeToggleBtn.setAttribute('aria-label', current === 'dark' ? 'Mode clair' : 'Mode sombre');
        themeToggleBtn.setAttribute('title', current === 'dark' ? 'Mode clair' : 'Mode sombre');
    };

    syncThemeIcon();

    themeToggleBtn?.addEventListener('click', () => {
        const current = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        const next = current === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-theme', next);
        localStorage.setItem(scopedStorageKey('theme'), next);
        syncThemeIcon();
    });

    /* ── DISPLAY SETTINGS (PERSISTENT) ───────────────────── */
    const fontFamilyField = document.querySelector('[data-setting-font-family]');
    const fontSizeField = document.querySelector('[data-setting-font-size]');
    const brightnessField = document.querySelector('[data-setting-brightness]');
    const fontSizeValue = document.querySelector('[data-setting-font-size-value]');
    const brightnessValue = document.querySelector('[data-setting-brightness-value]');
    const settingsResetBtn = document.querySelector('[data-settings-reset]');

    const defaults = {
        fontFamily: "'Plus Jakarta Sans','Segoe UI',sans-serif",
        fontSize: '16',
        brightness: '100'
    };

    const applyDisplaySettings = ({ fontFamily, fontSize, brightness }) => {
        root.style.setProperty('--ft-user-font-family', fontFamily);
        root.style.setProperty('--ft-user-font-size', `${fontSize}px`);
        root.style.setProperty('--ft-user-brightness', `${brightness}%`);

        if (fontFamilyField) fontFamilyField.value = fontFamily;
        if (fontSizeField) fontSizeField.value = fontSize;
        if (brightnessField) brightnessField.value = brightness;
        if (fontSizeValue) fontSizeValue.textContent = `${fontSize}px`;
        if (brightnessValue) brightnessValue.textContent = `${brightness}%`;
    };

    const readDisplaySettings = () => ({
        fontFamily: localStorage.getItem(scopedStorageKey('font-family')) || defaults.fontFamily,
        fontSize: localStorage.getItem(scopedStorageKey('font-size')) || defaults.fontSize,
        brightness: localStorage.getItem(scopedStorageKey('brightness')) || defaults.brightness
    });

    applyDisplaySettings(readDisplaySettings());

    fontFamilyField?.addEventListener('change', () => {
        const next = fontFamilyField.value || defaults.fontFamily;
        localStorage.setItem(scopedStorageKey('font-family'), next);
        applyDisplaySettings({ ...readDisplaySettings(), fontFamily: next });
    });

    fontSizeField?.addEventListener('input', () => {
        const next = String(fontSizeField.value || defaults.fontSize);
        localStorage.setItem(scopedStorageKey('font-size'), next);
        applyDisplaySettings({ ...readDisplaySettings(), fontSize: next });
    });

    brightnessField?.addEventListener('input', () => {
        const next = String(brightnessField.value || defaults.brightness);
        localStorage.setItem(scopedStorageKey('brightness'), next);
        applyDisplaySettings({ ...readDisplaySettings(), brightness: next });
    });

    settingsResetBtn?.addEventListener('click', () => {
        localStorage.removeItem(scopedStorageKey('font-family'));
        localStorage.removeItem(scopedStorageKey('font-size'));
        localStorage.removeItem(scopedStorageKey('brightness'));
        applyDisplaySettings(defaults);
    });

    const closePanels = () => {
        document.querySelectorAll('[data-profile-panel], [data-settings-panel]').forEach((el) => {
            el.classList.remove('is-open');
            el.setAttribute('aria-hidden', 'true');
            el.hidden = true;
        });
        document.querySelectorAll('[data-profile-toggle], [data-settings-toggle]').forEach((el) => {
            el.setAttribute('aria-expanded', 'false');
        });
    };

    /* ── PROFILE PANEL (NAME CLICK ONLY) ─────────────────── */
    document.querySelectorAll('[data-profile-root]').forEach((profileRoot) => {
        const toggle = profileRoot.querySelector('[data-profile-toggle]');
        const panel = profileRoot.querySelector('[data-profile-panel]');
        if (!toggle || !panel) return;

        const closePanel = () => {
            closePanels();
        };

        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const opening = panel.hidden;
            closePanels();

            if (opening) {
                panel.hidden = false;
                requestAnimationFrame(() => panel.classList.add('is-open'));
            }
            toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
        });

        panel.addEventListener('click', (event) => {
            event.stopPropagation();
        });

        document.addEventListener('click', (event) => {
            if (!profileRoot.contains(event.target)) {
                closePanel();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closePanel();
            }
        });
    });

    /* ── SETTINGS PANEL (CLICK ONLY) ─────────────────────── */
    document.querySelectorAll('[data-settings-root]').forEach((settingsRoot) => {
        const toggle = settingsRoot.querySelector('[data-settings-toggle]');
        const panel = settingsRoot.querySelector('[data-settings-panel]');
        if (!toggle || !panel) return;

        const closePanel = () => {
            closePanels();
        };

        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const opening = panel.hidden;
            closePanels();

            if (opening) {
                panel.hidden = false;
                requestAnimationFrame(() => panel.classList.add('is-open'));
            }
            toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
        });

        panel.addEventListener('click', (event) => {
            event.stopPropagation();
        });

        document.addEventListener('click', (event) => {
            if (!settingsRoot.contains(event.target)) {
                closePanel();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closePanel();
            }
        });
    });

    /* ── RIPPLE EFFECT ─────────────────────────────────────── */
    const rippleSel = '.primary-btn, .outline-btn, .danger-btn, .cta-btn, .icon-btn';
    document.querySelectorAll(rippleSel).forEach(btn => {
        btn.addEventListener('click', e => {
            const rect = btn.getBoundingClientRect();
            const ripple = document.createElement('span');
            ripple.className = 'btn-ripple';
            const size = Math.max(rect.width, rect.height) * 0.9;
            ripple.style.cssText = `width:${size}px;height:${size}px;left:${e.clientX-rect.left-size/2}px;top:${e.clientY-rect.top-size/2}px`;
            btn.appendChild(ripple);
            ripple.addEventListener('animationend', () => ripple.remove(), { once: true });
        });
    });

    /* ── CURSOR GLOW ───────────────────────────────────────── */
    if (window.matchMedia('(pointer:fine)').matches) {
        const glow = document.createElement('div');
        glow.className = 'cursor-glow';
        document.body.appendChild(glow);
        let mx = 0, my = 0, cx = 0, cy = 0;
        document.addEventListener('mousemove', e => { mx = e.clientX; my = e.clientY; });
        const animGlow = () => {
            cx += (mx - cx) * 0.1;
            cy += (my - cy) * 0.1;
            glow.style.left = cx + 'px';
            glow.style.top  = cy + 'px';
            requestAnimationFrame(animGlow);
        };
        animGlow();
    }

    /* ── SCROLL REVEAL ─────────────────────────────────────── */
    const revealCards = document.querySelectorAll(
        '.listing-card, .info-card, .city-card, .stat-card, .user-card, .sortie-card'
    );
    if ('IntersectionObserver' in window) {
        const revealObs = new IntersectionObserver((entries) => {
            entries.forEach((entry, i) => {
                if (entry.isIntersecting) {
                    entry.target.style.transitionDelay = (i % 6) * 0.07 + 's';
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                    entry.target.classList.add('ft-visible');
                    revealObs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
        revealCards.forEach(el => {
            // Only add reveal to cards not already animated by CSS nth-child
            if (!el.closest('.listing-grid') && !el.closest('.grid-cards')) {
                el.style.opacity = '0';
                el.style.transform = 'translateY(28px)';
                el.style.transition = 'opacity .7s cubic-bezier(.16,1,.3,1), transform .7s cubic-bezier(.16,1,.3,1)';
                el.classList.add('ft-reveal');
                revealObs.observe(el);
            }
        });

        // Add class when visible
        document.querySelectorAll('.ft-reveal').forEach(el => {
            revealObs.observe(el);
        });
    }

    // Fix for reveal class
    document.querySelectorAll('.ft-reveal').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(28px)';
        el.style.transition = 'opacity .7s cubic-bezier(.16,1,.3,1), transform .7s cubic-bezier(.16,1,.3,1)';
    });

    /* ── STAT COUNTER ANIMATION ────────────────────────────── */
    document.querySelectorAll('.stat-card__value').forEach(el => {
        const text = el.textContent.trim();
        const num = parseFloat(text.replace(/[^\d.]/g,''));
        if (!isNaN(num) && num > 0) {
            const obs = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (!entry.isIntersecting) return;
                    obs.unobserve(el);
                    let start = 0, duration = 1200;
                    const startTime = performance.now();
                    const tick = (now) => {
                        const elapsed = now - startTime;
                        const progress = Math.min(elapsed / duration, 1);
                        const eased = 1 - Math.pow(1 - progress, 3);
                        const current = Math.round(eased * num);
                        el.textContent = current + (text.replace(/[\d.]/g,'').trim() || '');
                        if (progress < 1) requestAnimationFrame(tick);
                    };
                    requestAnimationFrame(tick);
                });
            }, { threshold: 0.5 });
            obs.observe(el);
        }
    });

    /* ── DATE REFRESH ──────────────────────────────────────── */
    document.querySelectorAll('[data-refresh-date]').forEach(el => {
        el.textContent = new Date().toLocaleDateString('fr-FR', {
            weekday: 'long', day: '2-digit', month: 'long', year: 'numeric'
        });
    });

    /* ── COUNTDOWN ─────────────────────────────────────────── */
    const countdownEls = [...document.querySelectorAll('[data-offer-countdown]')];
    const fmt = (ms) => {
        if (ms <= 0) return 'Expirée';
        const s = Math.floor(ms / 1000);
        const d = Math.floor(s / 86400), h = Math.floor((s % 86400) / 3600), m = Math.floor((s % 3600) / 60);
        if (d > 0) return `${d}j ${h}h ${m}m`;
        if (h > 0) return `${h}h ${m}m`;
        return `${m}m`;
    };
    const refreshCD = () => {
        const now = Date.now();
        countdownEls.forEach(el => {
            const end = new Date(el.getAttribute('data-offer-countdown'));
            if (isNaN(end.getTime())) return;
            el.textContent = `Expire dans ${fmt(end.getTime() - now)}`;
            if (end.getTime() - now <= 0) el.classList.add('is-expired');
        });
    };
    if (countdownEls.length > 0) { refreshCD(); setInterval(refreshCD, 1000); }

    /* ── SORTIE MODAL ──────────────────────────────────────── */
    const sortieModal = document.getElementById('sortie-detail-modal');
    const syncBodyScrollLock = () => {
        // Keep page scroll enabled even when the detail modal is open.
        document.body.style.overflow = '';
    };
    const closeModal = () => {
        if (!sortieModal) return;
        sortieModal.hidden = true;
        syncBodyScrollLock();
    };
    const openModal  = () => {
        if (!sortieModal) return;
        sortieModal.hidden = false;
        syncBodyScrollLock();
    };
    syncBodyScrollLock();
    if (sortieModal) {
        sortieModal.querySelectorAll('[data-sortie-modal-close]').forEach(el => el.addEventListener('click', closeModal));
        sortieModal.querySelector('.sortie-modal__backdrop')?.addEventListener('click', closeModal);
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
    }

    const toPrettyStatus = (status) => {
        const s = (status || '').toUpperCase();
        if (s === 'OUVERTE') return 'Ouverte';
        if (s === 'CLOTUREE') return 'Cloturee';
        if (s === 'ANNULEE') return 'Annulee';
        if (s === 'TERMINEE') return 'Terminee';
        return status || '-';
    };

    const toPrettyPoint = (point) => {
        if (!point) return '';
        const raw = String(point).trim();
        const parts = raw.split(',').map(v => Number(v.trim()));
        if (parts.length === 2 && !Number.isNaN(parts[0]) && !Number.isNaN(parts[1])) {
            return `${parts[0].toFixed(5)}, ${parts[1].toFixed(5)}`;
        }
        return raw;
    };

    const parseQuestions = (rawQuestions) => {
        if (!rawQuestions) return [];
        try {
            const parsed = JSON.parse(rawQuestions);
            if (Array.isArray(parsed)) {
                return parsed.map(q => String(q || '').trim()).filter(Boolean);
            }
        } catch (_) {
            // Ignore invalid JSON and try plain-text fallback below.
        }
        return String(rawQuestions)
            .split(/\n|\||;/)
            .map(q => q.trim())
            .filter(Boolean);
    };

    document.querySelectorAll('[data-sortie-detail]').forEach(card => {
        card.addEventListener('click', e => {
            if (e.target.closest('a,button,form')) return;
            if (!sortieModal) return;
            const data = {
                titre:        card.dataset.sortieTitle || card.dataset.sortieDetail || '',
                description:  card.dataset.sortieDescription || '',
                lieu:         card.dataset.sortieLieu || '',
                point:        card.dataset.sortiePoint || '',
                date:         card.dataset.sortieDate || '',
                budget:       card.dataset.sortieBudget || '',
                participants: card.dataset.sortiePlaces || card.dataset.sortieParticipants || '',
                questions:    card.dataset.sortieQuestions || '',
                mediaUrl:     card.dataset.sortieImage || card.dataset.sortieMedia || '',
                statut:       card.dataset.sortieStatus || card.dataset.sortieStatut || '',
                type:         card.dataset.sortieType || '',
                ville:        card.dataset.sortieCity || '',
                createur:     card.dataset.sortieCreateur || '',
            };
            const setT = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };

            setT('sortie-modal-title', data.titre || 'Sortie');
            setT('sortie-modal-description', data.description || 'Aucune description.');

            const meta = document.getElementById('sortie-modal-meta');
            if (meta) {
                const items = [
                    { label: 'Date sortie', value: data.date },
                    { label: 'Ville', value: data.ville },
                    { label: 'Lieu', value: data.lieu },
                    { label: 'Type', value: data.type },
                    { label: 'Statut', value: toPrettyStatus(data.statut) },
                    { label: 'Places', value: data.participants ? `${data.participants}` : '' },
                    { label: 'Budget max', value: data.budget ? `${data.budget} TND` : '' },
                    { label: 'Createur', value: data.createur },
                ].filter(item => item.value && String(item.value).trim() !== '');

                meta.innerHTML = items
                    .map(item => `<span class="sortie-modal-meta-item"><small>${item.label}</small><strong>${item.value}</strong></span>`)
                    .join('');
            }

            const media = document.getElementById('sortie-modal-media');
            if (media) {
                media.innerHTML = data.mediaUrl
                    ? `<img src="${data.mediaUrl}" alt="${data.titre}" onerror="this.onerror=null;this.style.display='none'">`
                    : `<div class="sortie-modal__media-placeholder">Aucune image disponible</div>`;
            }

            const pointMap = document.getElementById('sortie-modal-point-map');
            if (pointMap) {
                const point = (data.point || '').trim();
                if (point !== '') {
                    const mapSrc = `https://maps.google.com/maps?q=${encodeURIComponent(point)}&z=15&output=embed`;
                    pointMap.innerHTML = `<iframe title="Point de rencontre" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="${mapSrc}"></iframe>`;
                    pointMap.hidden = false;
                } else {
                    pointMap.hidden = true;
                    pointMap.innerHTML = '';
                }
            }

            const qEl = document.getElementById('sortie-modal-questions');
            if (qEl) {
                const qs = parseQuestions(data.questions);
                if (qs.length > 0) {
                    qEl.hidden = false;
                    qEl.innerHTML = `<h4>Questions participation</h4><ul>${qs.map(q => `<li>${q}</li>`).join('')}</ul>`;
                } else {
                    qEl.hidden = true;
                    qEl.innerHTML = '';
                }
            }
            openModal();
        });
    });

    /* ── SORTIE FILTER & SORT (FRONT + ADMIN) ─────────────── */
    document.querySelectorAll('[data-sortie-controls]').forEach((controls) => {
        const searchInput = controls.querySelector('[data-sortie-search]');
        const statusSelect = controls.querySelector('[data-sortie-status]');
        const sortSelect = controls.querySelector('[data-sortie-sort]');
        const section = controls.closest('section') || document;
        const list = section.querySelector('[data-sortie-list]');
        const empty = section.querySelector('[data-sortie-empty]');

        if (!list) return;

        const getCards = () => Array.from(list.querySelectorAll('[data-sortie-detail]'));

        const getText = (card) => {
            const title = String(card.dataset.sortieTitle || '').toLowerCase();
            const city = String(card.dataset.sortieCity || '').toLowerCase();
            const type = String(card.dataset.sortieType || '').toLowerCase();
            const lieu = String(card.dataset.sortieLieu || '').toLowerCase();
            const createur = String(card.dataset.sortieCreateur || '').toLowerCase();
            const description = String(card.dataset.sortieDescription || '').toLowerCase();
            return `${title} ${city} ${type} ${lieu} ${createur} ${description}`;
        };

        const parseNum = (value) => {
            const n = Number(value);
            return Number.isFinite(n) ? n : 0;
        };

        const applySort = (cards) => {
            const mode = String(sortSelect?.value || 'recent');
            const sorted = [...cards].sort((a, b) => {
                const aDate = parseNum(a.dataset.sortieDateTs || 0);
                const bDate = parseNum(b.dataset.sortieDateTs || 0);
                const aTitle = String(a.dataset.sortieTitle || '').toLowerCase();
                const bTitle = String(b.dataset.sortieTitle || '').toLowerCase();
                const aCity = String(a.dataset.sortieCity || '').toLowerCase();
                const bCity = String(b.dataset.sortieCity || '').toLowerCase();
                const aPlaces = parseNum(a.dataset.sortiePlaces || 0);
                const bPlaces = parseNum(b.dataset.sortiePlaces || 0);

                if (mode === 'date_asc') return aDate - bDate;
                if (mode === 'date_desc' || mode === 'recent') return bDate - aDate;
                if (mode === 'title_asc') return aTitle.localeCompare(bTitle, 'fr');
                if (mode === 'title_desc') return bTitle.localeCompare(aTitle, 'fr');
                if (mode === 'city_asc') return aCity.localeCompare(bCity, 'fr');
                if (mode === 'places_desc') return bPlaces - aPlaces;
                return 0;
            });

            sorted.forEach((card) => list.appendChild(card));
        };

        const applyFilterAndSort = () => {
            const q = String(searchInput?.value || '').trim().toLowerCase();
            const st = String(statusSelect?.value || '').trim().toLowerCase();
            const cards = getCards();
            let visibleCount = 0;

            cards.forEach((card) => {
                const text = getText(card);
                const statut = String(card.dataset.sortieStatus || card.dataset.sortieStatut || '').toLowerCase();
                const matches = (!q || text.includes(q)) && (!st || statut === st);

                card.classList.toggle('sortie-card--hidden', !matches);
                card.hidden = !matches;
                if (matches) visibleCount += 1;
            });

            applySort(cards.filter((card) => !card.hidden));

            if (empty) {
                empty.hidden = visibleCount !== 0;
            }
        };

        searchInput?.addEventListener('input', applyFilterAndSort);
        statusSelect?.addEventListener('change', applyFilterAndSort);
        sortSelect?.addEventListener('change', applyFilterAndSort);

        applyFilterAndSort();
    });

    /* ── NOTIFICATION PANEL ────────────────────────────────── */
    document.querySelectorAll('[data-notification-root]').forEach((root) => {
        const feedUrl = root.getAttribute('data-feed-url') || '';
        const readAllUrl = root.getAttribute('data-read-all-url') || '';
        const typePrefixesAttr = root.getAttribute('data-notification-type-prefixes') || '';
        const typePrefixes = typePrefixesAttr
            .split(',')
            .map((value) => value.trim().toUpperCase())
            .filter(Boolean);
        const panel = root.querySelector('[data-notification-panel]');
        const list = root.querySelector('[data-notification-list]');
        const badge = root.querySelector('[data-notification-badge]');
        const toggleBtn = root.querySelector('[data-notification-toggle]');
        const readAllBtn = root.querySelector('[data-notification-read-all]');
        const csrfToken = readAllBtn?.getAttribute('data-notification-token') || '';

        if (!panel || !list || !toggleBtn || !feedUrl) return;

        const fmtDate = (raw) => {
            const d = new Date(raw);
            if (Number.isNaN(d.getTime())) return '';
            return d.toLocaleString('fr-FR', {
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });
        };

        const renderEmpty = (message = 'Aucune notification') => {
            list.innerHTML = `<div class="notification-empty">${message}</div>`;
        };

        const updateBadge = (unread) => {
            if (!badge) return;
            const count = Number(unread || 0);
            if (count > 0) {
                badge.hidden = false;
                badge.textContent = String(count);
            } else {
                badge.hidden = true;
                badge.textContent = '0';
            }
        };

        const renderItems = (items) => {
            if (!Array.isArray(items) || items.length === 0) {
                renderEmpty();
                return;
            }

            list.innerHTML = items.map((item) => {
                const unreadClass = item.read_at ? '' : ' notification-item--unread';
                const metaDate = fmtDate(item.created_at);
                const title = String(item.title || 'Notification');
                const body = String(item.body || '');
                const userUrl = item.url ? `<a class="outline-btn" href="${item.url}">Voir</a>` : '';
                const adminUrl = item.admin_url ? `<a class="outline-btn" href="${item.admin_url}">Admin</a>` : '';
                const actions = (userUrl || adminUrl)
                    ? `<div class="notification-item__actions">${userUrl}${adminUrl}</div>`
                    : '';

                return `
                    <article class="notification-item${unreadClass}">
                        <h4 class="notification-item__title">${title}</h4>
                        <p class="notification-item__body">${body}</p>
                        <div class="notification-item__meta">${metaDate}</div>
                        ${actions}
                    </article>
                `;
            }).join('');
        };

        const loadFeed = async () => {
            try {
                const res = await fetch(feedUrl, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
                if (!res.ok) {
                    renderEmpty('Impossible de charger les notifications.');
                    return;
                }

                const payload = await res.json();
                const rawItems = Array.isArray(payload.items) ? payload.items : [];
                const filteredItems = typePrefixes.length > 0
                    ? rawItems.filter((item) => {
                        const type = String(item?.type || '').toUpperCase();
                        return typePrefixes.some((prefix) => type.startsWith(prefix));
                    })
                    : rawItems;

                renderItems(filteredItems);
                const unreadFiltered = filteredItems.filter((item) => !item?.read_at).length;
                updateBadge(unreadFiltered);
            } catch (_) {
                renderEmpty('Impossible de charger les notifications.');
            }
        };

        toggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            panel.hidden = !panel.hidden;
            if (!panel.hidden) {
                loadFeed();
            }
        });

        document.addEventListener('click', (e) => {
            if (!panel.contains(e.target) && !toggleBtn.contains(e.target)) {
                panel.hidden = true;
            }
        });

        if (readAllBtn && readAllUrl) {
            readAllBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                try {
                    const res = await fetch(readAllUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ _token: csrfToken }),
                        credentials: 'same-origin'
                    });

                    if (res.ok) {
                        await loadFeed();
                    }
                } catch (_) {
                    // Ignore silently; UI remains usable.
                }
            });
        }

        loadFeed();
        setInterval(loadFeed, 45000);
    });

    /* ── HERO PARALLAX ─────────────────────────────────────── */
    const hero = document.querySelector('.hero');
    if (hero) {
        window.addEventListener('scroll', () => {
            const scrolled = window.scrollY;
            if (scrolled < window.innerHeight) {
                hero.style.transform = `translateY(${scrolled * 0.18}px)`;
            }
        }, { passive: true });
    }

    /* ── HERO TAG CLICK FILTER ─────────────────────────────── */
    document.querySelectorAll('.hero-tag').forEach(tag => {
        tag.addEventListener('click', () => {
            const heroInput = document.querySelector('.hero-search input');
            if (heroInput) { heroInput.value = tag.textContent; heroInput.focus(); }
        });
    });

    /* ── TILT EFFECT ON CARDS ──────────────────────────────── */
    const tiltCards = document.querySelectorAll('.stat-card, .hero-mini-card');
    tiltCards.forEach(card => {
        card.addEventListener('mousemove', e => {
            const rect = card.getBoundingClientRect();
            const x = (e.clientX - rect.left) / rect.width - 0.5;
            const y = (e.clientY - rect.top) / rect.height - 0.5;
            card.style.transform = `perspective(600px) rotateX(${-y * 6}deg) rotateY(${x * 6}deg) translateY(-5px)`;
        });
        card.addEventListener('mouseleave', () => {
            card.style.transform = '';
        });
    });

    /* ── FLOATING PARTICLES ────────────────────────────────── */
    const canvas = document.createElement('canvas');
    canvas.id = 'ft-particles';
    canvas.style.cssText = 'position:fixed;inset:0;z-index:-1;pointer-events:none;opacity:0.5';
    document.body.appendChild(canvas);
    const ctx = canvas.getContext('2d');
    let W, H, particles = [];
    const resize = () => {
        W = canvas.width = window.innerWidth;
        H = canvas.height = window.innerHeight;
    };
    resize();
    window.addEventListener('resize', resize);
    const colors = ['rgba(63,168,224,', 'rgba(212,170,35,', 'rgba(26,74,138,'];
    for (let i = 0; i < 30; i++) {
        particles.push({
            x: Math.random() * window.innerWidth,
            y: Math.random() * window.innerHeight,
            r: Math.random() * 2.5 + 0.5,
            dx: (Math.random() - 0.5) * 0.4,
            dy: (Math.random() - 0.5) * 0.4,
            color: colors[Math.floor(Math.random() * colors.length)],
            alpha: Math.random() * 0.4 + 0.1
        });
    }
    const drawParticles = () => {
        ctx.clearRect(0, 0, W, H);
        particles.forEach(p => {
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fillStyle = p.color + p.alpha + ')';
            ctx.fill();
            p.x += p.dx; p.y += p.dy;
            if (p.x < 0 || p.x > W) p.dx *= -1;
            if (p.y < 0 || p.y > H) p.dy *= -1;
        });
        requestAnimationFrame(drawParticles);
    };
    drawParticles();

});
