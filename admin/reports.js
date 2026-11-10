(() => {
    const data = window.liblogReportData;
    if (!data || !window.Chart) return;
    const ink = '#344054';
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.font.size = 12;
    Chart.defaults.color = ink;
    const charts = window.liblogCharts || (window.liblogCharts = {});
    const palette = ['#3156a6', '#168778', '#b45a24', '#7656a8', '#287547', '#a23e61', '#596579'];
    const empty = values => !values.length || values.every(value => Number(value) === 0);
    const options = (axisTitle, horizontal = false) => ({ responsive: true, maintainAspectRatio: false, indexAxis: horizontal ? 'y' : 'x', plugins: { legend: { display: false }, tooltip: { backgroundColor: '#202b3c', titleColor: '#fff', bodyColor: '#fff', callbacks: { label: context => `${context.dataset.label}: ${context.parsed[context.chart.options.indexAxis === 'y' ? 'x' : 'y']}` } } }, scales: { x: { beginAtZero: horizontal, grid: { display: false, color: 'rgba(52,64,84,.1)', drawBorder: false }, title: { display: horizontal || axisTitle === 'Hour of day', text: horizontal ? 'Sessions' : axisTitle, color: ink }, ticks: { color: ink, autoSkip: true, maxRotation: 0 } }, y: { beginAtZero: !horizontal, grid: { color: 'rgba(52,64,84,.1)', drawBorder: false }, title: { display: !horizontal, text: 'Sessions', color: ink }, ticks: { color: ink, precision: 0, stepSize: 1 } } } });
    const make = (id, type, labels, values, label, horizontal = false) => {
        const canvas = document.getElementById(id); if (!canvas) return;
        const wrapper = canvas.closest('.rep-chart-wrap');
        if (charts[id]) charts[id].destroy();
        if (empty(values)) { canvas.hidden = true; wrapper.querySelector('.rep-empty').hidden = false; return; }
        canvas.hidden = false; wrapper.querySelector('.rep-empty').hidden = true;
        const opts = options(id === 'hours-chart' ? 'Hour of day' : 'Sessions', horizontal);
        if (type === 'doughnut') {
            let names = [...labels], counts = [...values];
            if (names.length > 7) { const other = counts.slice(6).reduce((sum, value) => sum + Number(value), 0); names = [...names.slice(0, 6), 'Other']; counts = [...counts.slice(0, 6), other]; }
            opts.plugins.legend = { display: true, position: 'bottom', labels: { color: ink, generateLabels(chart) { const total = chart.data.datasets[0].data.reduce((sum, value) => sum + Number(value), 0); return chart.data.labels.map((text, index) => ({ text: `${text}: ${chart.data.datasets[0].data[index]} (${total ? Math.round(chart.data.datasets[0].data[index] / total * 100) : 0}%)`, fillStyle: palette[index % palette.length], strokeStyle: palette[index % palette.length], index })); } } };
            opts.plugins.tooltip.callbacks.label = context => `${context.label}: ${context.raw}`;
            charts[id] = new Chart(canvas, { type, data: { labels: names, datasets: [{ data: counts, backgroundColor: palette, borderWidth: 1, borderColor: '#fff' }] }, options: opts });
        } else {
            charts[id] = new Chart(canvas, { type, data: { labels, datasets: [{ label, data: values, borderColor: '#3156a6', backgroundColor: type === 'line' ? 'rgba(49,86,166,.18)' : '#6685cb', fill: type === 'line', tension: .25, borderRadius: 4, maxBarThickness: 34 }] }, options: opts });
        }
    };
    const hourLabels = Array.from({ length: 24 }, (_, hour) => `${hour % 12 || 12} ${hour < 12 ? 'AM' : 'PM'}`);
    make('daily-chart', 'line', data.days, data.sessions, 'Sessions');
    make('hours-chart', 'bar', hourLabels, data.hours, 'Sessions');
    make('course-chart', 'doughnut', data.courses, data.courseCounts, 'Sessions');
    make('pc-chart', 'bar', data.pcs, data.pcCounts, 'Sessions', true);
})();
