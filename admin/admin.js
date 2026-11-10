(() => {
    const icon = name => {
        const element = document.createElement('i');
        element.dataset.lucide = name;
        element.setAttribute('aria-hidden', 'true');
        return element;
    };
    const renderIcons = () => window.lucide?.createIcons();
    const fields = { student_id: 'id-card', first_name: 'user', last_name: 'user', email: 'mail', course: 'graduation-cap', username: 'user-round', password: 'lock-keyhole', pc_number: 'monitor', status: 'circle-check', library_name: 'library', opening_time: 'clock', closing_time: 'clock', max_session_minutes: 'timer', logs_per_page: 'list' };
    document.querySelectorAll('.admin-form label').forEach(label => {
        const field = label.querySelector('input, select');
        const text = label.firstChild;
        if (!field || !text || text.nodeType !== Node.TEXT_NODE) return;
        const caption = document.createElement('span');
        caption.className = 'field-caption';
        caption.append(icon(fields[field.name] || 'tag'), text);
        label.prepend(caption);
    });
    document.querySelectorAll('.admin-form select, .logs-filter-form select').forEach((select, index) => {
        const label = select.closest('label');
        const omitSelected = Boolean(select.closest('.logs-filter-form'));
        let caption = label.querySelector('.field-caption, .rounded-select-caption');
        if (!caption) {
            const labelText = [...label.childNodes].find(node => node.nodeType === Node.TEXT_NODE && node.textContent.trim());
            caption = document.createElement('span');
            caption.className = 'rounded-select-caption';
            caption.textContent = labelText?.textContent.trim() || 'Select an option';
            labelText?.remove();
            label.prepend(caption);
        }
        caption.id = `select-caption-${index}`;
        const wrapper = document.createElement('div');
        wrapper.className = 'rounded-select';
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'rounded-select-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-labelledby', `${caption.id} select-value-${index}`);
        const value = document.createElement('span');
        value.id = `select-value-${index}`;
        value.textContent = select.selectedOptions[0]?.textContent || '';
        trigger.append(value, icon('chevron-down'));
        const list = document.createElement('div');
        list.id = `select-options-${index}`;
        list.className = 'rounded-select-options';
        list.setAttribute('role', 'listbox');
        list.setAttribute('aria-labelledby', caption.id);
        list.hidden = true;
        trigger.setAttribute('aria-controls', list.id);
        const close = () => {
            list.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
        };
        const options = [...select.options].map(option => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'rounded-select-option';
            button.textContent = option.textContent;
            button.disabled = option.disabled;
            button.hidden = option.disabled || (omitSelected && option.selected);
            button.setAttribute('role', 'option');
            button.setAttribute('aria-selected', String(!omitSelected && option.selected));
            button.addEventListener('click', () => {
                select.value = option.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                close();
                trigger.focus();
            });
            list.append(button);
            return button;
        });
        const open = () => {
            const available = options.filter(option => !option.hidden && !option.disabled);
            if (!available.length) return;
            list.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            const initial = omitSelected ? available[0] : (options[select.selectedIndex] || available[0]);
            initial?.focus();
        };
        trigger.addEventListener('click', () => list.hidden ? open() : close());
        trigger.addEventListener('keydown', event => {
            if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
                event.preventDefault();
                open();
            }
        });
        wrapper.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                event.preventDefault();
                close();
                trigger.focus();
            }
            if (list.hidden || !['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const enabled = options.filter(option => !option.hidden && !option.disabled);
            const current = enabled.indexOf(document.activeElement);
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? enabled.length - 1 : (current + (event.key === 'ArrowDown' ? 1 : -1) + enabled.length) % enabled.length;
            enabled[next]?.focus();
        });
        document.addEventListener('click', event => { if (!wrapper.contains(event.target)) close(); });
        document.addEventListener('focusin', event => { if (!wrapper.contains(event.target)) close(); });
        const sync = () => {
            value.textContent = select.selectedOptions[0]?.textContent || '';
            options.forEach((option, optionIndex) => {
                const selected = optionIndex === select.selectedIndex;
                option.hidden = option.disabled || (omitSelected && selected);
                option.setAttribute('aria-selected', String(!omitSelected && selected));
            });
            trigger.disabled = omitSelected && !options.some(option => !option.hidden && !option.disabled);
        };
        select.addEventListener('change', sync);
        select.form?.addEventListener('reset', () => setTimeout(sync, 0));
        sync();
        wrapper.append(trigger, list);
        select.after(wrapper);
        select.hidden = true;
    });
    // Trim before native pattern validation, including Enter-key submission.
    document.querySelectorAll('input[name="pc_number"]').forEach(input => {
        const validate = () => {
            input.value = input.value.trim();
            input.setCustomValidity(input.value && (!/^PC-[1-9][0-9]*$/.test(input.value) || input.value.length > 10)
                ? 'PC number must use the format PC-1 (up to 10 characters).' : '');
        };
        input.addEventListener('input', validate);
        input.addEventListener('change', validate);
        input.form.addEventListener('submit', event => {
            validate();
            if (!input.reportValidity()) event.preventDefault();
        });
    });
    // All record editors share the same dialog behavior and styling.
    const editTriggers = new Map();
    const closeModal = modal => {
        modal.close();
        editTriggers.get(modal.id)?.focus();
        const url = new URL(location.href);
        if (url.searchParams.has('edit')) {
            url.searchParams.delete('edit');
            history.replaceState(null, '', url);
        }
    };
    document.addEventListener('click', event => {
        const edit = event.target.closest('[data-edit-modal]');
        if (edit) {
            const modal = document.getElementById(edit.dataset.editModal);
            if (!modal) return;
            event.preventDefault();
            editTriggers.set(modal.id, edit);
            modal.querySelectorAll('.notice').forEach(notice => notice.remove());
            const form = modal.querySelector('form');
            form.reset();
            const record = JSON.parse(edit.dataset.record);
            Object.entries(record).forEach(([name, value]) => {
                const field = form.elements.namedItem(name);
                if (field && name !== 'password') {
                    field.value = name === 'status' && value === 'In Use' ? 'Occupied' : (value ?? '');
                    if (field instanceof HTMLSelectElement) field.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
            form.querySelectorAll('input').forEach(input => input.setCustomValidity(''));
            if (!modal.open) modal.showModal();
            form.querySelector('[autofocus], input:not([type="hidden"]):not([readonly])')?.focus();
        }
        const close = event.target.closest('[data-close-modal]');
        if (close) {
            const modal = close.closest('dialog');
            if (!modal) return;
            event.preventDefault();
            closeModal(modal);
        }
    });
    document.querySelectorAll('.admin-modal').forEach(modal => {
        modal.addEventListener('cancel', event => { event.preventDefault(); closeModal(modal); });
        if (modal.dataset.open === 'true') modal.showModal();
    });
    const importModal = document.getElementById('students-import-modal');
    if (importModal) {
        const form = document.getElementById('students-import-form');
        const input = form.querySelector('.import-file-input');
        const zone = form.querySelector('.import-dropzone');
        const row = form.querySelector('.import-file-row');
        const error = form.querySelector('.import-error');
        const submit = form.querySelector('[type="submit"]');
        const close = () => importModal.close();
        document.querySelector('[data-open-import]')?.addEventListener('click', () => importModal.showModal());
        importModal.querySelectorAll('[data-close-import]').forEach(button => button.addEventListener('click', close));
        const setFile = file => {
            error.hidden = true;
            if (!file) { input.value = ''; row.hidden = true; submit.disabled = true; return; }
            if (!/\.csv$/i.test(file.name) || file.size > 2 * 1024 * 1024) {
                input.value = ''; row.hidden = true; submit.disabled = true;
                error.textContent = !/\.csv$/i.test(file.name) ? 'Choose a .csv file.' : 'The file must be 2 MB or smaller.';
                error.hidden = false; return;
            }
            const transfer = new DataTransfer(); transfer.items.add(file); input.files = transfer.files;
            form.querySelector('.import-file-name').textContent = file.name;
            form.querySelector('.import-file-size').textContent = `${(file.size / 1024).toFixed(1)} KB`;
            row.hidden = false; submit.disabled = false;
        };
        zone.addEventListener('click', () => input.click());
        zone.addEventListener('keydown', event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); input.click(); } });
        input.addEventListener('change', () => setFile(input.files[0]));
        form.querySelector('.import-remove').addEventListener('click', () => setFile(null));
        ['dragenter', 'dragover'].forEach(type => zone.addEventListener(type, event => { event.preventDefault(); zone.classList.add('is-dragover'); }));
        ['dragleave', 'drop'].forEach(type => zone.addEventListener(type, event => { event.preventDefault(); zone.classList.remove('is-dragover'); }));
        zone.addEventListener('drop', event => setFile(event.dataTransfer.files[0]));
        form.addEventListener('submit', () => { if (submit.disabled) return; submit.disabled = true; submit.classList.add('is-loading'); submit.querySelector('span').textContent = 'Importing'; });
    }
    const decorateActions = () => {
        document.querySelectorAll('.actions a, .actions button, .admin-form > button').forEach(button => {
            if (button.querySelector('[data-lucide], svg')) return;
            button.prepend(icon(button.classList.contains('link-danger') ? 'trash-2' : button.tagName === 'A' ? 'pencil' : button.textContent.includes('Save') ? 'save' : 'plus'));
        });
        renderIcons();
    };
    document.querySelectorAll('.stat-card').forEach((card, index) => {
        card.prepend(icon(['monitor', 'circle-check', 'user-round', 'calendar-days'][index]));
    });
    document.querySelectorAll('.content-card h2').forEach(heading => heading.prepend(icon(heading.textContent.includes('Session') || heading.textContent.includes('History') ? 'history' : heading.textContent.includes('Computer') ? 'monitor' : heading.textContent.includes('Student') ? 'graduation-cap' : 'users')));

    document.querySelectorAll('.content-card:has(table)').forEach(section => {
        if (section.classList.contains('logs-table-card')) return;
        let form = section.querySelector('.search-form');
        const serverSearch = Boolean(form);
        if (!form && !location.pathname.endsWith('dashboard.php')) {
            const heading = section.querySelector('h2');
            const toolbar = document.createElement('div');
            toolbar.className = 'section-heading';
            heading.replaceWith(toolbar);
            toolbar.append(heading);
            form = document.createElement('form');
            form.className = 'search-form';
            form.setAttribute('role', 'search');
            const input = document.createElement('input');
            input.type = 'search';
            input.name = 'search';
            input.placeholder = location.pathname.endsWith('staff.php') ? 'Search staff name, email or username' : 'Search PC or status';
            input.setAttribute('aria-label', 'Search records');
            form.append(icon('search'), input);
            toolbar.append(form);
        }
        if (!form) return;
        const input = form.querySelector('input');
        const count = document.createElement('p');
        count.className = 'record-count';
        count.setAttribute('role', 'status');
        count.setAttribute('aria-live', 'polite');
        section.querySelector('.table-wrap').before(count);
        const empty = document.createElement('p');
        empty.className = 'empty-state live-empty';
        empty.textContent = 'No matching records found.';
        empty.hidden = true;
        section.append(empty);
        section.querySelectorAll('.empty-state:not(.live-empty)').forEach(element => element.hidden = true);
        const updateCount = () => {
            const rows = [...section.querySelectorAll('tbody tr')];
            const visible = rows.filter(row => !row.hidden).length;
            count.textContent = `${visible} record${visible === 1 ? '' : 's'}${input.value.trim() ? ' matching your search' : ''}`;
            empty.hidden = visible !== 0;
        };
        let timer;
        let controller;
        let version = 0;
        const search = async () => {
            if (!serverSearch) {
                const term = input.value.trim().toLocaleLowerCase();
                section.querySelectorAll('tbody tr').forEach(row => {
                    const values = [...row.cells].filter(cell => !cell.classList.contains('actions')).map(cell => cell.textContent).join(' ').toLocaleLowerCase();
                    row.hidden = !values.includes(term);
                });
                updateCount();
                return;
            }
            const requestVersion = version;
            controller = new AbortController();
            section.setAttribute('aria-busy', 'true');
            count.textContent = 'Searching...';
            const url = new URL(location.href);
            url.searchParams.set('search', input.value);
            url.searchParams.delete('edit');
            try {
                const response = await fetch(url, { signal: controller.signal, headers: { 'X-Requested-With': 'fetch' } });
                if (!response.ok) throw new Error('Search failed');
                const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                const table = page.querySelector('.content-card .table-wrap');
                if (!table || page.querySelector('.notice.error')) throw new Error('Search unavailable');
                if (requestVersion !== version) return;
                section.querySelector('.table-wrap').replaceWith(table);
                updateCount();
                decorateActions();
            } catch (error) {
                if (error.name !== 'AbortError' && requestVersion === version) count.textContent = 'Search unavailable. Please try again.';
            } finally {
                if (requestVersion === version) section.removeAttribute('aria-busy');
            }
        };
        input.addEventListener('input', () => {
            version++;
            controller?.abort();
            clearTimeout(timer);
            timer = setTimeout(search, serverSearch ? 250 : 0);
        });
        form.addEventListener('submit', event => {
            event.preventDefault();
            version++;
            controller?.abort();
            clearTimeout(timer);
            search();
        });
        updateCount();
    });
    document.querySelectorAll('.logs-export:not(.is-disabled)').forEach(link => link.addEventListener('click', () => {
        link.classList.add('is-loading');
        link.setAttribute('aria-disabled', 'true');
        window.setTimeout(() => { link.classList.remove('is-loading'); link.removeAttribute('aria-disabled'); }, 1500);
    }));
    document.querySelectorAll('.notice').forEach(notice => notice.setAttribute('role', notice.classList.contains('error') ? 'alert' : 'status'));
    decorateActions();
})();
