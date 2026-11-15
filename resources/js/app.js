import { Calendar } from 'fullcalendar';
import QRCode from 'qrcode';
import '../css/site-visit-form.css';
import dayGridPlugin from 'fullcalendar/daygrid';
import classicThemePlugin from 'fullcalendar/themes/classic';
import 'fullcalendar/skeleton.css';
import 'fullcalendar/themes/classic/theme.css';
import 'fullcalendar/themes/classic/palette.css';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-referral-qr]').forEach(async (card) => {
        const canvas = card.querySelector('canvas');
        const loading = card.querySelector('[data-qr-loading]');
        const download = card.querySelector('[data-download-referral]');
        const copy = card.querySelector('[data-copy-referral]');
        const url = card.dataset.referralUrl;

        if (!canvas || !url) return;

        try {
            await QRCode.toCanvas(canvas, url, {
                width: 720,
                margin: 2,
                errorCorrectionLevel: 'H',
                color: { dark: '#2d3036ff', light: '#ffffffff' },
            });

            const context = canvas.getContext('2d');
            const size = 168;
            const position = (canvas.width - size) / 2;
            context.fillStyle = '#ffffff';
            context.beginPath();
            context.roundRect(position - 9, position - 9, size + 18, size + 18, 28);
            context.fill();
            context.strokeStyle = '#f5b800';
            context.lineWidth = 7;
            context.stroke();

            const logo = new Image();
            logo.src = card.dataset.referralLogo;
            await logo.decode();
            context.drawImage(logo, position, position, size, size);

            if (download) download.href = canvas.toDataURL('image/png');
            if (loading) loading.hidden = true;
        } catch (error) {
            if (loading) loading.textContent = 'QR preview unavailable. Refresh to retry.';
        }

        copy?.addEventListener('click', async () => {
            const copyUrl = card.dataset.referralCopyUrl || url;
            try {
                await navigator.clipboard.writeText(copyUrl);
                const original = copy.textContent;
                copy.textContent = 'Copied';
                window.setTimeout(() => { copy.textContent = original; }, 1500);
            } catch (error) {
                window.prompt('Copy this referral link:', copyUrl);
            }
        });
    });

    // Marketing tools: build a referral link for any page on the public site.
    document.querySelectorAll('[data-link-builder]').forEach((box) => {
        const base = new URL(box.dataset.publicUrl);
        const select = box.querySelector('[data-link-page]');
        const paste = box.querySelector('[data-link-paste]');
        const output = box.querySelector('[data-link-output]');
        const error = box.querySelector('[data-link-error]');
        const build = (target) => {
            target.searchParams.set('ref', box.dataset.ref);
            output.value = target.toString();
        };
        select?.addEventListener('change', () => {
            if (paste) paste.value = '';
            if (error) error.textContent = '';
            build(new URL(select.value, base));
        });
        paste?.addEventListener('input', () => {
            const value = paste.value.trim();
            if (error) error.textContent = '';
            if (!value) return;
            let target;
            try { target = new URL(value.includes('://') ? value : `https://${value}`); } catch { return; }
            if (target.hostname.replace(/^www\./, '') !== base.hostname.replace(/^www\./, '')) {
                if (error) error.textContent = `Only ${base.hostname} links can carry your referral code.`;
                output.value = '';
                return;
            }
            target.protocol = base.protocol;
            target.hash = '';
            build(target);
        });
        box.querySelector('[data-link-copy]')?.addEventListener('click', async (event) => {
            const button = event.currentTarget;
            try { await navigator.clipboard.writeText(output.value); } catch { output.select(); document.execCommand('copy'); }
            const label = button.textContent;
            button.textContent = 'Copied';
            window.setTimeout(() => { button.textContent = label; }, 1500);
        });
    });

    // Marketing tools: remember grid or list per browser.
    const mediaList = document.querySelector('[data-media-list]');
    if (mediaList) {
        const buttons = document.querySelectorAll('[data-view]');
        const apply = (view) => {
            mediaList.classList.toggle('is-list', view === 'list');
            buttons.forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.view === view)));
        };
        let saved = 'grid';
        try { saved = localStorage.getItem('cucinow.media-view') || 'grid'; } catch { /* private mode */ }
        apply(saved);
        buttons.forEach((b) => b.addEventListener('click', () => {
            apply(b.dataset.view);
            try { localStorage.setItem('cucinow.media-view', b.dataset.view); } catch { /* ignore */ }
        }));
    }

    // Dashboard referral card: share icon reveals the network icons inline (NBDY style).
    document.querySelectorAll('[data-share-strip]').forEach((strip) => {
        const toggle = strip.querySelector('[data-share-strip-toggle]');
        const icons = strip.querySelector('.agent-share-icons');
        toggle?.addEventListener('click', () => {
            const open = icons.hidden;
            icons.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
            strip.classList.toggle('is-open', open);
            if (open) icons.querySelector('a')?.focus();
        });
        strip.querySelector('[data-share-strip-qr]')?.addEventListener('click', (event) => {
            const qr = document.getElementById('referral-qr');
            if (!qr) return;
            event.preventDefault();
            qr.scrollIntoView({ behavior: 'smooth', block: 'center' });
            qr.classList.remove('is-highlight');
            void qr.offsetWidth;
            qr.classList.add('is-highlight');
            qr.focus({ preventScroll: true });
        });
    });

    // Share menu: copy as HTML / text / image, or open a network with a channel-tagged referral link.
    const escapeHtml = (s) => s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const closeAllShareMenus = (except) => document.querySelectorAll('[data-share-menu]').forEach((box) => {
        if (box === except) return;
        box.querySelector('.mt-share-menu').hidden = true;
        box.querySelector('.mt-share-toggle').setAttribute('aria-expanded', 'false');
    });
    document.addEventListener('click', (event) => { if (!event.target.closest('[data-share-menu]')) closeAllShareMenus(); });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        const open = document.querySelector('[data-share-menu] .mt-share-menu:not([hidden])');
        if (!open) return;
        closeAllShareMenus();
        open.closest('[data-share-menu]').querySelector('.mt-share-toggle').focus();
    });

    document.querySelectorAll('[data-share-menu]').forEach((box) => {
        const toggle = box.querySelector('.mt-share-toggle');
        const menu = box.querySelector('.mt-share-menu');
        const status = box.querySelector('.mt-share-status');
        const items = () => [...menu.querySelectorAll('[role=menuitem]:not([hidden])')];
        if (navigator.share) menu.querySelector('[data-share-action=native]').hidden = false;

        const ref = box.dataset.refUrl || '';
        const currentText = () => (box.dataset.textFrom ? document.querySelector(box.dataset.textFrom)?.value : box.dataset.text) || ref;
        // Tag every copy of the referral link with the channel, so Analytics shows where visits came from.
        const tagged = (text, via) => {
            if (!via) return text;
            return text.replace(/https?:\/\/[^\s"'<>]+[?&]ref=[a-z0-9_-]+[^\s"'<>]*/gi, (url) => {
                try { const u = new URL(url); u.searchParams.set('via', via); return u.toString(); } catch { return url; }
            });
        };
        const firstLink = (text) => (text.match(/https?:\/\/[^\s"'<>]+/) || [ref])[0];
        const say = (message) => { status.textContent = message; window.setTimeout(() => { if (status.textContent === message) status.textContent = ''; }, 2200); };
        const open = (url) => window.open(url, '_blank', 'noopener,noreferrer,width=640,height=720');

        const setOpen = (isOpen) => {
            closeAllShareMenus(box);
            menu.hidden = !isOpen;
            toggle.setAttribute('aria-expanded', String(isOpen));
            if (isOpen) {
                // Open upwards when there is no room below.
                menu.classList.toggle('is-up', toggle.getBoundingClientRect().bottom + 380 > window.innerHeight && toggle.getBoundingClientRect().top > 380);
                items()[0]?.focus();
            }
        };
        toggle.addEventListener('click', () => setOpen(menu.hidden));
        menu.addEventListener('keydown', (event) => {
            const list = items();
            const i = list.indexOf(document.activeElement);
            if (event.key === 'ArrowDown') { event.preventDefault(); list[(i + 1) % list.length].focus(); }
            if (event.key === 'ArrowUp') { event.preventDefault(); list[(i - 1 + list.length) % list.length].focus(); }
            if (event.key === 'Tab') setOpen(false);
        });

        const actions = {
            'copy-text': async () => { await navigator.clipboard.writeText(currentText()); say('Text copied'); },
            'copy-html': async () => {
                const text = currentText();
                const link = firstLink(text);
                const body = escapeHtml(text).replace(/https?:\/\/[^\s<]+/g, (u) => `<a href="${u}">${u}</a>`).replace(/\n/g, '<br>');
                const image = box.dataset.imageUrl ? `<a href="${escapeHtml(link)}"><img src="${escapeHtml(box.dataset.imageUrl)}" alt="${escapeHtml(box.dataset.title)}" style="max-width:100%;height:auto"></a>\n` : '';
                const html = `${image}<p>${body}</p>`;
                if (window.ClipboardItem) {
                    await navigator.clipboard.write([new ClipboardItem({ 'text/html': new Blob([html], { type: 'text/html' }), 'text/plain': new Blob([html], { type: 'text/plain' }) })]);
                } else {
                    await navigator.clipboard.writeText(html);
                }
                say('HTML copied');
            },
            'copy-image': async () => {
                // Clipboards only accept PNG, so redraw the picture on a canvas first.
                const png = new Promise((resolve, reject) => {
                    const img = new Image();
                    img.crossOrigin = 'anonymous';
                    img.onload = () => {
                        const canvas = Object.assign(document.createElement('canvas'), { width: img.naturalWidth, height: img.naturalHeight });
                        canvas.getContext('2d').drawImage(img, 0, 0);
                        canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('blank'))), 'image/png');
                    };
                    img.onerror = reject;
                    img.src = box.dataset.imageUrl;
                });
                await navigator.clipboard.write([new ClipboardItem({ 'image/png': png })]);
                say('Image copied — paste it into your post');
            },
            whatsapp: () => open(`https://wa.me/?text=${encodeURIComponent(tagged(currentText(), 'whatsapp'))}`),
            facebook: () => open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(firstLink(tagged(currentText(), 'facebook')))}`),
            threads: () => open(`https://www.threads.net/intent/post?text=${encodeURIComponent(tagged(currentText(), 'threads'))}`),
            x: () => open(`https://x.com/intent/post?text=${encodeURIComponent(currentText())}`),
            pinterest: () => open(`https://www.pinterest.com/pin/create/button/?url=${encodeURIComponent(firstLink(currentText()))}&media=${encodeURIComponent(box.dataset.imageUrl)}&description=${encodeURIComponent(currentText())}`),
            native: async () => {
                const text = currentText();
                if (box.dataset.file && navigator.canShare) {
                    say('Preparing…');
                    const blob = await fetch(box.dataset.file, { credentials: 'same-origin' }).then((r) => { if (!r.ok) throw new Error('download'); return r.blob(); });
                    const file = new File([blob], box.dataset.fileName || 'cucinow', { type: box.dataset.fileType || blob.type });
                    if (navigator.canShare({ files: [file] })) { status.textContent = ''; await navigator.share({ files: [file], text }); return; }
                }
                await navigator.share({ text });
            },
        };

        menu.addEventListener('click', async (event) => {
            const item = event.target.closest('[data-share-action]');
            if (!item) return;
            const action = item.dataset.shareAction;
            try {
                await actions[action]();
                if (!action.startsWith('copy')) setOpen(false);
            } catch (error) {
                if (error?.name === 'AbortError') return;
                try { await navigator.clipboard.writeText(currentText()); say('Could not do that here — text copied instead'); } catch { say('Your browser blocked this. Copy the text manually.'); }
            }
        });
    });

    // Sales kit quiz: mark answers in place.
    document.querySelectorAll('[data-quiz]').forEach((form) => {
        const result = form.querySelector('[data-quiz-result]');
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            let score = 0;
            const sets = form.querySelectorAll('fieldset');
            sets.forEach((set) => {
                const answer = set.dataset.answer;
                set.querySelectorAll('label').forEach((label) => {
                    const input = label.querySelector('input');
                    label.classList.toggle('is-right', input.value === answer);
                    label.classList.toggle('is-wrong', input.checked && input.value !== answer);
                });
                if (set.querySelector('input:checked')?.value === answer) score += 1;
            });
            result.textContent = score === sets.length ? `${score}/${sets.length} — you're ready to go!` : `${score}/${sets.length} — check the highlighted answers and try again.`;
        });
        form.addEventListener('reset', () => {
            form.querySelectorAll('label').forEach((l) => l.classList.remove('is-right', 'is-wrong'));
            result.textContent = '';
        });
    });

    const revealRoot = document.querySelector('.hiw');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (revealRoot && 'IntersectionObserver' in window && !reduceMotion) {
        // Hide-then-reveal only once JS is running, so content is never stuck invisible.
        revealRoot.classList.add('hiw-js');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });
        revealRoot.querySelectorAll('[data-reveal]').forEach((el) => observer.observe(el));
    }

    document.querySelectorAll('[data-code-check]').forEach((input) => {
        const status = document.getElementById(input.getAttribute('aria-describedby'));
        const pattern = /^[a-z0-9][a-z0-9_-]{2,39}$/;
        let timer;
        let controller;

        const show = (state, message) => {
            if (!status) return;
            status.dataset.state = state;
            status.textContent = message;
        };

        const check = async () => {
            const code = input.value.trim().toLowerCase();
            controller?.abort();
            input.setCustomValidity('');

            if (!code) return show('', status?.dataset.default ?? '');
            if (code.length < 3) return show('', 'Keep typing — at least 3 characters.');
            if (!pattern.test(code)) return show('taken', 'Use lowercase letters, numbers, hyphens or underscores, starting with a letter or number.');

            show('checking', `Checking “${code}”…`);
            controller = new AbortController();
            try {
                const url = new URL(input.dataset.codeCheck, window.location.href);
                url.searchParams.set('code', code);
                const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
                if (!response.ok) throw new Error(String(response.status));
                const result = await response.json();
                if (result.code !== input.value.trim().toLowerCase()) return;
                show(result.available ? 'available' : 'taken', result.message);
                input.setCustomValidity(result.available ? '' : result.message);
            } catch (error) {
                // Network or rate-limit hiccup: stay quiet, the server still validates on submit.
                if (error.name !== 'AbortError') show('', status?.dataset.default ?? '');
            }
        };

        input.addEventListener('input', () => {
            const lower = input.value.toLowerCase();
            if (lower !== input.value) input.value = lower;
            window.clearTimeout(timer);
            timer = window.setTimeout(check, 400);
        });
        if (input.value) check();
    });

    document.querySelectorAll('[data-agent-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            const value = button.dataset.agentCopy;
            try {
                await navigator.clipboard.writeText(value);
                const original = button.textContent;
                button.textContent = 'Copied';
                window.setTimeout(() => { button.textContent = original; }, 1500);
            } catch {
                window.prompt('Copy this caption:', value);
            }
        });
    });

    document.querySelectorAll('[data-site-photo-upload]').forEach((form) => {
        const input = form.querySelector('input[type="file"]');
        input?.addEventListener('change', () => {
            input.setCustomValidity(input.files?.[0]?.size > 8 * 1024 * 1024 ? 'Choose a photo smaller than 8 MB.' : '');
            input.reportValidity();
        });
    });
    document.querySelectorAll('[data-photo-heic-preview]').forEach((image) => {
        const showFallback = () => {
            image.hidden = true;
            const fallback = image.nextElementSibling;
            if (fallback?.hasAttribute('data-photo-heic-fallback')) fallback.hidden = false;
        };
        image.addEventListener('error', showFallback);
        if (image.complete && image.naturalWidth === 0) showFallback();
    });
    document.querySelectorAll('[data-site-photo-modal]').forEach((modal) => {
        const image = modal.querySelector('[data-gallery-modal-image]');
        const fallback = modal.querySelector('[data-gallery-modal-fallback]');
        const original = modal.querySelector('[data-gallery-modal-original]');
        const close = () => modal.close();
        modal.closest('.site-photo-section')?.querySelectorAll('[data-gallery-preview]').forEach((preview) => {
            preview.addEventListener('click', (event) => {
                event.preventDefault();
                modal.querySelector('[data-gallery-modal-caption]').textContent = preview.dataset.photoCaption || 'Site condition';
                modal.querySelector('[data-gallery-modal-notes]').textContent = preview.dataset.photoNotes || 'No inspection note recorded.';
                modal.querySelector('[data-gallery-modal-uploader]').textContent = preview.dataset.photoUploader || 'Team member';
                modal.querySelector('[data-gallery-modal-date]').textContent = preview.dataset.photoDate || '';
                image.hidden = false;
                fallback.hidden = true;
                image.alt = `Site assessment: ${preview.dataset.photoCaption || 'site condition'}`;
                image.src = preview.dataset.photoUrl;
                original.href = preview.dataset.photoUrl;
                modal.showModal();
            });
        });
        image.addEventListener('error', () => {
            image.hidden = true;
            fallback.hidden = false;
        });
        modal.querySelector('[data-gallery-modal-close]')?.addEventListener('click', close);
        modal.addEventListener('click', (event) => {
            if (event.target === modal) close();
        });
        modal.addEventListener('close', () => {
            image.removeAttribute('src');
        });
    });
    document.querySelectorAll('[data-quote-editor]').forEach((form) => {
        const lines = form.querySelector('[data-quote-lines]');
        const template = form.querySelector('[data-quote-line-template]');
        const addButton = form.querySelector('[data-add-quote-line]');
        const discount = form.querySelector('[data-quote-discount]');
        const message = form.querySelector('[data-quote-editor-message]');
        const currency = new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' });
        let nextIndex = Math.max(-1, ...[...lines.querySelectorAll('input[name]')].map((input) => Number(input.name.match(/^items\[(\d+)\]/)?.[1] ?? -1))) + 1;
        const money = (cents) => currency.format(cents / 100);
        const cents = (value) => Math.round((Number(value) || 0) * 100);
        const syncRichEditor = (editor) => {
            const canvas = editor?.querySelector('[data-quote-rich-canvas]');
            const source = editor?.querySelector('[data-quote-rich-source]');
            if (canvas && source) source.value = canvas.innerHTML;
        };

        const recalculate = () => {
            const rows = [...lines.querySelectorAll('[data-quote-line]')];
            let subtotal = 0;
            rows.forEach((row) => {
                const amount = Math.round(cents(row.querySelector('[data-line-quantity]').value) * cents(row.querySelector('[data-line-rate]').value) / 100);
                subtotal += amount;
                row.querySelector('[data-line-amount]').textContent = money(amount);
                row.querySelector('[data-remove-quote-line]').disabled = rows.length <= 1;
            });
            const discountCents = cents(discount.value);
            const invalidDiscount = discountCents > subtotal;
            discount.setCustomValidity(invalidDiscount ? 'Discount cannot exceed the services subtotal.' : '');
            message.textContent = invalidDiscount ? 'Discount cannot exceed the services subtotal.' : '';
            const taxable = Math.max(0, subtotal - discountCents);
            const tax = Math.round(taxable * cents(form.dataset.taxRate) / 10000);
            form.querySelector('[data-quote-subtotal]').textContent = money(subtotal);
            form.querySelector('[data-quote-tax]').textContent = money(tax);
            form.querySelector('[data-quote-total]').textContent = money(taxable + tax);
            addButton.disabled = rows.length >= 100;
        };

        addButton.addEventListener('click', () => {
            const fragment = template.content.cloneNode(true);
            fragment.querySelectorAll('[name]').forEach((input) => {
                input.name = input.name.replace('__INDEX__', String(nextIndex));
            });
            nextIndex += 1;
            lines.append(fragment);
            recalculate();
            lines.lastElementChild.querySelector('[data-line-description]').focus();
        });
        lines.addEventListener('click', (event) => {
            const richCommand = event.target.closest('[data-quote-rich-command]');
            const richDivider = event.target.closest('[data-quote-rich-hr]');
            if (richCommand || richDivider) {
                const editor = event.target.closest('[data-quote-rich-editor]');
                const canvas = editor?.querySelector('[data-quote-rich-canvas]');
                canvas?.focus();
                document.execCommand(richCommand?.dataset.quoteRichCommand ?? 'insertHorizontalRule', false);
                syncRichEditor(editor);
                return;
            }
            const button = event.target.closest('[data-remove-quote-line]');
            if (!button || lines.querySelectorAll('[data-quote-line]').length <= 1) return;
            button.closest('[data-quote-line]').remove();
            recalculate();
        });
        lines.addEventListener('mousedown', (event) => {
            if (event.target.closest('[data-quote-rich-command],[data-quote-rich-hr]')) event.preventDefault();
        });
        lines.addEventListener('input', (event) => {
            syncRichEditor(event.target.closest('[data-quote-rich-editor]'));
            recalculate();
        });
        lines.addEventListener('paste', (event) => {
            if (!event.target.closest('[data-quote-rich-canvas]')) return;
            event.preventDefault();
            document.execCommand('insertText', false, event.clipboardData?.getData('text/plain') ?? '');
            syncRichEditor(event.target.closest('[data-quote-rich-editor]'));
        });
        form.addEventListener('submit', () => {
            form.querySelectorAll('[data-quote-rich-editor]').forEach(syncRichEditor);
        });
        form.addEventListener('input', recalculate);
        recalculate();
    });

    document.querySelectorAll('[data-quote-contacts]').forEach((editor) => {
        const list = editor.querySelector('[data-quote-contact-list]');
        const template = editor.querySelector('[data-quote-contact-template]');
        const addButton = editor.querySelector('[data-add-quote-contact]');
        let nextIndex = Math.max(-1, ...[...list.querySelectorAll('[name]')].map((input) => Number(input.name.match(/^contacts\[(\d+)\]/)?.[1] ?? -1))) + 1;
        const sync = () => {
            const rows = list.querySelectorAll('[data-quote-contact-row]');
            addButton.disabled = rows.length >= 10;
        };
        addButton.addEventListener('click', () => {
            if (list.querySelectorAll('[data-quote-contact-row]').length >= 10) return;
            const fragment = template.content.cloneNode(true);
            fragment.querySelectorAll('[name]').forEach((input) => {
                input.name = input.name.replace('__INDEX__', String(nextIndex));
            });
            nextIndex += 1;
            list.append(fragment);
            list.lastElementChild.querySelector('input').focus();
            sync();
        });
        list.addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove-quote-contact]');
            if (!button) return;
            button.closest('[data-quote-contact-row]').remove();
            sync();
        });
        sync();
    });

    const menuButton = document.querySelector('[data-menu-button]');
    const menu = document.querySelector('[data-menu]');
    menuButton?.addEventListener('click', () => {
        const open = menu?.classList.toggle('open') ?? false;
        menuButton.setAttribute('aria-expanded', String(open));
    });
    menu?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
        menu.classList.remove('open');
        menuButton?.setAttribute('aria-expanded', 'false');
    }));
    document.querySelector('[data-admin-menu]')?.addEventListener('click', () => {
        document.querySelector('[data-admin-sidebar]')?.classList.toggle('open');
    });

    document.querySelectorAll('[data-full-calendar]').forEach((calendarElement) => {
        const errorMessage = calendarElement.parentElement?.querySelector('[data-calendar-error]');
        const calendar = new Calendar(calendarElement, {
            plugins: [dayGridPlugin, classicThemePlugin],
            themeSystem: 'classic',
            initialView: 'dayGridMonth',
            firstDay: 1,
            fixedWeekCount: false,
            showNonCurrentDates: true,
            aspectRatio: window.matchMedia('(max-width: 600px)').matches ? 0.72 : 1.35,
            dayMaxEvents: window.matchMedia('(max-width: 600px)').matches ? 1 : 3,
            dayCellClass: 'cucinow-calendar-day',
            dayHeaderClass: 'cucinow-calendar-weekday',
            viewClass: 'cucinow-calendar-view',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: '',
            },
            buttonText: { today: 'Today' },
            events: {
                url: calendarElement.dataset.eventsUrl,
                failure: () => {
                    if (errorMessage) errorMessage.hidden = false;
                },
                success: () => {
                    if (errorMessage) errorMessage.hidden = true;
                },
            },
            eventDidMount: ({ event, el }) => {
                const details = [
                    event.extendedProps.reference,
                    event.extendedProps.status,
                    event.extendedProps.service,
                    event.extendedProps.company,
                    event.extendedProps.address,
                    event.extendedProps.source,
                ].filter(Boolean);

                el.title = details.join(' · ');
                el.setAttribute('aria-label', `${event.title}. ${details.join('. ')}`);
            },
        });

        calendar.render();
    });

    document.querySelectorAll('[data-accordion]').forEach((accordion) => {
        const items = [...accordion.querySelectorAll('details')];

        items.forEach((item) => item.addEventListener('toggle', () => {
            if (!item.open) return;

            items.forEach((otherItem) => {
                if (otherItem !== item) otherItem.open = false;
            });
        }));
    });

    document.querySelectorAll('[data-subscriber-bulk-form]').forEach((form) => {
        const selectAll = form.querySelector('[data-select-all-subscribers]');
        const checkboxes = [...form.querySelectorAll('[data-subscriber-checkbox]')];
        const selectedCount = form.querySelector('[data-selected-count]');

        const updateSelection = () => {
            const checked = checkboxes.filter((checkbox) => checkbox.checked).length;
            if (selectedCount) selectedCount.textContent = String(checked);
            if (selectAll) {
                selectAll.checked = checkboxes.length > 0 && checked === checkboxes.length;
                selectAll.indeterminate = checked > 0 && checked < checkboxes.length;
            }
        };

        selectAll?.addEventListener('change', () => {
            checkboxes.forEach((checkbox) => { checkbox.checked = selectAll.checked; });
            updateSelection();
        });
        checkboxes.forEach((checkbox) => checkbox.addEventListener('change', updateSelection));
        form.addEventListener('submit', (event) => {
            if (!checkboxes.some((checkbox) => checkbox.checked)) {
                event.preventDefault();
                window.alert('Select at least one subscriber first.');
                return;
            }

            if (event.submitter?.matches('[data-subscriber-bulk-delete]') && !window.confirm('Delete the selected subscribers permanently?')) {
                event.preventDefault();
            }
        });
        updateSelection();
    });

    document.querySelectorAll('[data-html-editor]').forEach((editor) => {
        const canvas = editor.querySelector('[data-editor-canvas]');
        const source = editor.querySelector('[data-editor-source]');
        const sourceToggle = editor.querySelector('[data-editor-source-toggle]');
        const format = editor.querySelector('[data-editor-format]');
        const count = editor.querySelector('[data-editor-count]');
        const imageButton = editor.querySelector('[data-editor-image-button]');
        const imageInput = editor.querySelector('[data-editor-image-input]');
        const editorStatus = editor.querySelector('[data-editor-status]');
        const imageUploadUrl = editor.dataset.imageUploadUrl;
        const form = editor.closest('form');
        let sourceMode = false;
        let savedRange = null;

        if (!canvas || !source) return;

        canvas.innerHTML = source.value;
        document.execCommand('defaultParagraphSeparator', false, 'p');

        const rememberSelection = () => {
            const selection = window.getSelection();
            if (!selection?.rangeCount) return;

            const range = selection.getRangeAt(0);
            if (canvas.contains(range.commonAncestorContainer)) savedRange = range.cloneRange();
        };

        const restoreSelection = () => {
            canvas.focus();
            if (!savedRange) return;

            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(savedRange);
        };

        const plainText = () => {
            if (!sourceMode) return canvas.textContent ?? '';

            const temporary = document.createElement('div');
            temporary.innerHTML = source.value;
            return temporary.textContent ?? '';
        };

        const updateCount = () => {
            const length = plainText().trim().length;
            if (count) count.textContent = `${length.toLocaleString()} character${length === 1 ? '' : 's'}`;
        };

        const syncSource = () => {
            if (!sourceMode) source.value = canvas.innerHTML;
            updateCount();
        };

        const escapeAttribute = (value) => String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('"', '&quot;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;');

        const setEditorStatus = (message, state = '') => {
            if (!editorStatus) return;
            editorStatus.textContent = message;
            editorStatus.dataset.state = state;
        };

        editor.querySelectorAll('[data-editor-command]').forEach((button) => {
            button.addEventListener('mousedown', (event) => event.preventDefault());
            button.addEventListener('click', () => {
                restoreSelection();
                document.execCommand(button.dataset.editorCommand, false);
                syncSource();
                rememberSelection();
            });
        });

        editor.querySelectorAll('[data-editor-merge-tag]').forEach((button) => {
            button.addEventListener('mousedown', (event) => event.preventDefault());
            button.addEventListener('click', () => {
                const mergeTag = button.dataset.editorMergeTag;

                if (sourceMode) {
                    const start = source.selectionStart;
                    const end = source.selectionEnd;
                    source.setRangeText(mergeTag, start, end, 'end');
                    source.focus();
                    updateCount();
                    return;
                }

                restoreSelection();
                document.execCommand('insertText', false, mergeTag);
                syncSource();
                rememberSelection();
            });
        });

        format?.addEventListener('change', () => {
            restoreSelection();
            document.execCommand('formatBlock', false, format.value);
            syncSource();
            rememberSelection();
        });

        editor.querySelector('[data-editor-link]')?.addEventListener('mousedown', (event) => event.preventDefault());
        editor.querySelector('[data-editor-link]')?.addEventListener('click', () => {
            const enteredUrl = window.prompt('Enter the link URL, email address, or telephone number:');
            if (!enteredUrl) return;

            let url = enteredUrl.trim();
            if (url.includes('@') && !url.includes('://') && !url.startsWith('mailto:')) url = `mailto:${url}`;
            if (!/^(https?:\/\/|mailto:|tel:|\/)/i.test(url)) url = `https://${url}`;

            restoreSelection();
            document.execCommand('createLink', false, url);
            syncSource();
            rememberSelection();
        });

        imageButton?.addEventListener('mousedown', (event) => event.preventDefault());
        imageButton?.addEventListener('click', () => imageInput?.click());
        imageInput?.addEventListener('change', async () => {
            const file = imageInput.files?.[0];
            if (!file || !imageUploadUrl || !form) return;

            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!allowedTypes.includes(file.type) || file.size > 2 * 1024 * 1024) {
                setEditorStatus('Choose a JPG, PNG or WebP image up to 2 MB.', 'error');
                imageInput.value = '';
                return;
            }

            const suggestedAlt = file.name.replace(/\.[^.]+$/, '').replaceAll(/[-_]+/g, ' ');
            const alt = window.prompt('Describe this image for accessibility:', suggestedAlt);
            if (alt === null) {
                imageInput.value = '';
                return;
            }

            const formData = new FormData();
            formData.append('image', file);
            formData.append('alt', alt);
            formData.append('_token', form.querySelector('input[name="_token"]')?.value ?? '');

            imageButton.disabled = true;
            setEditorStatus('Uploading image...', 'working');

            try {
                const response = await fetch(imageUploadUrl, {
                    method: 'POST',
                    body: formData,
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                const payload = await response.json();

                if (!response.ok) {
                    const validationMessage = Object.values(payload.errors ?? {}).flat()[0];
                    throw new Error(validationMessage || payload.message || 'The image upload failed.');
                }

                restoreSelection();
                document.execCommand(
                    'insertHTML',
                    false,
                    `<p><img src="${escapeAttribute(payload.url)}" alt="${escapeAttribute(payload.alt)}" style="display:block;max-width:100%;height:auto;margin:20px auto;border-radius:12px"></p><p><br></p>`,
                );
                syncSource();
                rememberSelection();
                setEditorStatus('Image uploaded and inserted.', 'success');
            } catch (error) {
                setEditorStatus(error instanceof Error ? error.message : 'The image upload failed.', 'error');
            } finally {
                imageButton.disabled = sourceMode;
                imageInput.value = '';
            }
        });

        sourceToggle?.addEventListener('click', () => {
            if (!sourceMode) syncSource();

            sourceMode = !sourceMode;
            canvas.hidden = sourceMode;
            source.hidden = !sourceMode;
            editor.classList.toggle('source-mode', sourceMode);
            sourceToggle.classList.toggle('active', sourceMode);
            sourceToggle.innerHTML = sourceMode ? 'Visual' : '&lt;/&gt; HTML';

            editor.querySelectorAll('.html-editor-toolbar button:not([data-editor-source-toggle]), .html-editor-toolbar select')
                .forEach((control) => { control.disabled = sourceMode; });

            if (sourceMode) {
                source.focus();
            } else {
                canvas.innerHTML = source.value;
                canvas.focus();
            }

            updateCount();
        });

        ['input', 'keyup', 'mouseup', 'focus'].forEach((eventName) => {
            canvas.addEventListener(eventName, () => {
                rememberSelection();
                syncSource();
            });
        });
        source.addEventListener('input', updateCount);
        form?.addEventListener('submit', syncSource);
        updateCount();
    });
});
