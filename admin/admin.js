(() => {
    const icon = name => {
        const element = document.createElement('i');
        element.dataset.lucide = name;
        element.setAttribute('aria-hidden', 'true');
        return element;
    };
    const renderIcons = () => window.lucide?.createIcons();
    const fields = { student_id: 'id-card', first_name: 'user', last_name: 'user', email: 'mail', course: 'graduation-cap', username: 'user-round', password: 'lock-keyhole', pc_number: 'monitor', status: 'circle-check' };
    document.querySelectorAll('.admin-form label').forEach(label => {
        const field = label.querySelector('input, select');
        const text = label.firstChild;
        if (!field || !text || text.nodeType !== Node.TEXT_NODE) return;
        const caption = document.createElement('span');
        caption.className = 'field-caption';
        caption.append(icon(fields[field.name] || 'tag'), text);
        label.prepend(caption);
    });
    document.querySelectorAll('.admin-form select').forEach((select, index) => {
        const label = select.closest('label');
        const caption = label.querySelector('.field-caption');
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
            button.setAttribute('role', 'option');
            button.setAttribute('aria-selected', String(option.selected));
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
            list.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            (options[select.selectedIndex] || options.find(option => !option.disabled))?.focus();
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
            const enabled = options.filter(option => !option.disabled);
            const current = enabled.indexOf(document.activeElement);
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? enabled.length - 1 : (current + (event.key === 'ArrowDown' ? 1 : -1) + enabled.length) % enabled.length;
            enabled[next]?.focus();
        });
        document.addEventListener('click', event => { if (!wrapper.contains(event.target)) close(); });
        document.addEventListener('focusin', event => { if (!wrapper.contains(event.target)) close(); });
        const sync = () => {
            value.textContent = select.selectedOptions[0]?.textContent || '';
            options.forEach((option, optionIndex) => option.setAttribute('aria-selected', String(optionIndex === select.selectedIndex)));
        };
        select.addEventListener('change', sync);
        select.form?.addEventListener('reset', () => setTimeout(sync, 0));
        wrapper.append(trigger, list);
        select.after(wrapper);
        select.hidden = true;
    });
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
    document.querySelectorAll('.notice').forEach(notice => notice.setAttribute('role', notice.classList.contains('error') ? 'alert' : 'status'));
    decorateActions();
})();
