(() => {
    const panel = document.getElementById('kiosk-panel');
    let busy = false;
    let inactivityTimer;

    // Reset only the kiosk UI. No database session is ended by Cancel or inactivity.
    const resetKiosk = () => {
        clearTimeout(inactivityTimer);
        window.Swal?.close();
        panel.replaceChildren();
        location.replace('checkin.php');
    };
    const armInactivity = () => {
        clearTimeout(inactivityTimer);
        if (!busy && panel.querySelector('form')?.dataset.mode !== 'initial') {
            inactivityTimer = setTimeout(resetKiosk, 45000);
        }
    };
    const showError = message => {
        const error = panel.querySelector('#checkin-error');
        error.textContent = message;
        error.classList.add('is-visible');
    };
    const showSuccess = success => {
        // The response already contains a blank ID screen and no student identity.
        panel.querySelector('#kiosk-success')?.remove();
        if (window.Swal) {
            Swal.fire({ icon: 'success', title: success.title, text: success.text,
                showConfirmButton: false, timer: 1800, timerProgressBar: true });
        } else {
            const message = document.createElement('p');
            message.className = 'form-success is-visible';
            message.setAttribute('role', 'status');
            message.textContent = success.title + '. ' + success.text;
            panel.append(message);
            setTimeout(() => message.remove(), 1800);
        }
    };

    panel.addEventListener('submit', async event => {
        const form = event.target;
        if (form.id !== 'checkin-form') return;
        event.preventDefault();
        if (busy) return;
        const action = event.submitter?.value === 'cancel' ? 'cancel' : form.querySelector('input[name="action"]').value;
        if (action === 'continue') {
            const input = form.elements.id_number;
            input.value = input.value.trim();
            const valid = /^[0-9]{7}-[0-9]$/.test(input.value);
            input.closest('.field').classList.toggle('is-invalid', !valid);
            input.closest('.field').classList.toggle('incorrect', !valid);
            if (!valid) {
                const message = input.value ? 'Please enter a valid Student ID.' : 'ID number is required.';
                panel.querySelector('#id-number-error').textContent = message;
                showError(message);
                input.focus();
                return;
            }
        }
        if (action === 'start' && !form.querySelector('input[name="pc_number"]:checked:not(:disabled)')) {
            showError('Please select an available PC.');
            form.querySelector('input[name="pc_number"]:not(:disabled)')?.focus();
            return;
        }
        const body = new FormData(form);
        body.set('action', action);
        busy = true;
        clearTimeout(inactivityTimer);
        const button = action === 'cancel' ? event.submitter : form.querySelector('#checkin-button');
        const controls = [...form.querySelectorAll('button, input')].filter(control => !control.disabled);
        controls.forEach(control => control.disabled = true);
        button?.classList.add('is-loading');
        button?.setAttribute('aria-busy', 'true');
        if (button?.querySelector('.kiosk-spinner')) button.querySelector('.kiosk-spinner').hidden = false;
        const controller = new AbortController();
        const requestTimeout = setTimeout(() => controller.abort(), 15000);
        try {
            const response = await fetch('checkin.php', { method: 'POST', body,
                headers: { 'X-Requested-With': 'fetch' }, signal: controller.signal, cache: 'no-store' });
            if (!response.ok) throw new Error('Request failed');
            const result = await response.json();
            if (typeof result.html !== 'string') throw new Error('Unexpected response');
            panel.innerHTML = result.html;
            if (result.success) showSuccess(result.success);
            panel.querySelector('#id-number, input[name="pc_number"]:not(:disabled), #checkin-button')?.focus();
        } catch (error) {
            showError('Unable to complete the request. Please try again or approach the librarian.');
        } finally {
            clearTimeout(requestTimeout);
            controls.forEach(control => control.disabled = false);
            button?.classList.remove('is-loading');
            button?.removeAttribute('aria-busy');
            if (button?.querySelector('.kiosk-spinner')) button.querySelector('.kiosk-spinner').hidden = true;
            busy = false;
            armInactivity();
        }
    });
    panel.addEventListener('input', event => {
        if (event.target.id === 'id-number') event.target.closest('.field').classList.remove('incorrect', 'is-invalid');
        panel.querySelector('#checkin-error')?.classList.remove('is-visible');
    });
    ['pointerdown', 'keydown', 'input'].forEach(name => document.addEventListener(name, armInactivity, { passive: true }));
    window.addEventListener('pageshow', event => { if (event.persisted) resetKiosk(); });
    const success = panel.querySelector('#kiosk-success');
    if (success) showSuccess({ title: success.dataset.title, text: success.textContent.trim() });
    armInactivity();
})();
