import { Calendar } from 'fullcalendar';
import dayGridPlugin from 'fullcalendar/daygrid';
import classicThemePlugin from 'fullcalendar/themes/classic';
import 'fullcalendar/skeleton.css';
import 'fullcalendar/themes/classic/theme.css';
import 'fullcalendar/themes/classic/palette.css';

document.addEventListener('DOMContentLoaded', () => {
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
            dayCellClassNames: 'cucinow-calendar-day',
            dayHeaderClassNames: 'cucinow-calendar-weekday',
            viewClassNames: 'cucinow-calendar-view',
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
