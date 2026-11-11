(() => {
    const monitor = document.getElementById('pc-monitor');
    if (!monitor) return;
    const errorBox = document.getElementById('monitor-error');
    const token = monitor.dataset.token;
    const cards = new Map();
    const formatElapsed = seconds => {
        const minutes = Math.floor(Math.max(0, Number(seconds) || 0) / 60);
        return `${Math.floor(minutes / 60) ? `${Math.floor(minutes / 60)}h ` : ''}${minutes % 60}m`;
    };
    const createCard = computer => {
        const card = document.createElement('article');
        card.className = 'pcm-tile';
        const heading = document.createElement('div'); heading.className = 'pcm-heading';
        const number = document.createElement('strong'); number.className = 'pcm-number';
        const pill = document.createElement('span'); pill.className = 'pcm-status';
        const dot = document.createElement('i'); dot.setAttribute('aria-hidden', 'true');
        const status = document.createElement('span'); pill.append(dot, status); heading.append(number, pill);
        const body = document.createElement('div'); body.className = 'pcm-body';
        const student = document.createElement('strong'); student.className = 'pcm-student';
        const studentId = document.createElement('span'); studentId.className = 'pcm-student-id';
        const timing = document.createElement('span'); timing.className = 'pcm-timing';
        const force = document.createElement('button'); force.type = 'button'; force.className = 'pcm-force'; force.textContent = 'Force End';
        card.append(heading, body); return { card, number, status, student, studentId, timing, force, body };
    };
    const update = computer => {
        const state = computer.status === 'In Use' ? 'Occupied' : computer.status;
        let nodes = cards.get(String(computer.pc_id));
        if (!nodes) { nodes = createCard(computer); cards.set(String(computer.pc_id), nodes); monitor.append(nodes.card); }
        const { card, number, status, student, studentId, timing, force, body } = nodes;
        card.className = `pcm-tile ${state.toLowerCase().replaceAll(' ', '-')}`;
        const pcNumber = String(computer.pc_number || '').trim();
        number.textContent = /^PC-/i.test(pcNumber) ? pcNumber : `PC-${pcNumber}`; status.textContent = state;
        const occupied = state === 'Occupied' && computer.student_id;
        if (occupied) {
            card.querySelector('.pcm-description')?.remove();
            const fullName = `${computer.first_name || ''} ${computer.last_name || ''}`.trim();
            student.textContent = fullName; student.title = fullName;
            studentId.textContent = computer.student_id;
            const startedAt = computer.started_at ? new Date(computer.started_at.replace(' ', 'T')) : null;
            const startedText = startedAt && !Number.isNaN(startedAt.getTime()) ? startedAt.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : '—';
            timing.textContent = `Started ${startedText} · ${formatElapsed(computer.elapsed_seconds)}`;
            timing.dataset.elapsed = String(computer.elapsed_seconds || 0);
            if (!student.isConnected) body.append(student, studentId, timing, force);
            force.onclick = () => forceEnd(computer.pc_id);
        } else {
            student.remove(); studentId.remove(); timing.remove(); force.remove();
            const message = document.createElement('span'); message.className = 'pcm-description';
            message.textContent = state === 'Available' ? 'Ready to use' : 'Out of service';
            const prior = card.querySelector('.pcm-description');
            if (prior) prior.replaceWith(message); else body.append(message);
        }
    };
    const refresh = async () => {
        try {
            const response = await fetch('api_pc_status.php', { headers: { Accept: 'application/json' }, cache: 'no-store' });
            const data = await response.json();
            if (!response.ok || !Array.isArray(data.computers)) throw new Error('Refresh failed');
            const ids = new Set(data.computers.map(pc => String(pc.pc_id)));
            for (const [id, nodes] of cards) if (!ids.has(id)) { nodes.card.remove(); cards.delete(id); }
            data.computers.forEach(update);
            if (!data.computers.length) { const empty = document.createElement('p'); empty.className = 'pcm-empty'; empty.textContent = 'No computers have been added yet.'; monitor.replaceChildren(empty); }
            else { monitor.querySelector('.pcm-empty')?.remove(); monitor.querySelectorAll('.pcm-skeleton').forEach(node => node.remove()); }
            errorBox.hidden = true;
        } catch { errorBox.hidden = false; }
    };
    async function forceEnd(pcId) {
        const result = window.Swal ? await Swal.fire({ title: 'Force end this session?', text: 'The student’s computer session will be closed.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Force End', confirmButtonColor: '#b42318' }) : { isConfirmed: window.confirm('Force end this session?') };
        if (!result.isConfirmed) return;
        try {
            const body = new URLSearchParams({ pc_id: String(pcId), csrf_token: token });
            const response = await fetch('force_end.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' }, body });
            const data = await response.json(); if (!response.ok) throw new Error(data.error || 'Unable to end this session.');
            if (window.Swal) await Swal.fire({ icon: 'success', title: 'Session ended', timer: 1200, showConfirmButton: false }); await refresh();
        } catch (error) { if (window.Swal) Swal.fire({ icon: 'error', title: 'Could not end session', text: error.message }); else window.alert(error.message); }
    }
    for (let i = 0; i < 5; i++) { const skeleton = document.createElement('div'); skeleton.className = 'pcm-skeleton'; monitor.append(skeleton); }
    refresh(); window.setInterval(refresh, 15000);
    window.setInterval(() => monitor.querySelectorAll('[data-elapsed]').forEach(node => { node.dataset.elapsed = String(Number(node.dataset.elapsed) + 60); node.textContent = `Started ${node.textContent.split('·')[0].replace('Started ', '')} · ${formatElapsed(node.dataset.elapsed)}`; }), 60000);
})();
