/**
 * SIDMS Admin Dashboard JS
 */
(function () {
    'use strict';

    const PALETTE = ['#2f81f7','#3fb950','#d29922','#f85149','#a5d6ff','#ffa657','#7ee787','#ff7b72'];
    let actChart, catChart;

    function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function actionBadgeCls(a) { return {'LOGIN_OK':'bg-success','LOGIN_FAIL':'bg-danger','UPLOAD':'bg-primary','DOWNLOAD':'bg-info text-dark','DELETE':'bg-warning text-dark','LOGOUT':'bg-secondary','ADMIN_ACTION':'bg-purple','PREVIEW':'bg-light text-dark'}[a]||'bg-dark'; }

    async function refresh() {
        try {
            const res = await fetch(BASE_URL+'/api/stats.php');
            const d = await res.json();
            const s = d.stats;

            document.getElementById('s-users').textContent = s.users;
            document.getElementById('s-files').textContent = s.files;
            document.getElementById('s-storage').textContent = s.storage;
            document.getElementById('s-alerts').textContent = s.alerts;
            document.getElementById('s-failed').textContent = s.failed;
            document.getElementById('s-downloads').textContent = s.downloads;

            const badge = document.getElementById('alert-badge');
            if (badge) {
                if (s.alerts > 0) { badge.textContent = s.alerts; badge.classList.remove('d-none'); }
                else badge.classList.add('d-none');
            }

            const lbls = d.hourly.map(h => h.hr);
            const vals = d.hourly.map(h => parseInt(h.n));
            if (!actChart) {
                actChart = new Chart(document.getElementById('activity-chart'), {
                    type: 'bar',
                    data: { labels: lbls, datasets: [{ label: 'Actions', data: vals, backgroundColor: 'rgba(47,129,247,.6)', borderColor: '#2f81f7', borderWidth: 1, borderRadius: 4 }] },
                    options: {
                        responsive: true, plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { color: 'rgba(48,54,61,.5)' }, ticks: { color: '#8b949e' } },
                            y: { grid: { color: 'rgba(48,54,61,.5)' }, ticks: { color: '#8b949e' }, beginAtZero: true }
                        }
                    }
                });
            } else {
                actChart.data.labels = lbls;
                actChart.data.datasets[0].data = vals;
                actChart.update('none');
            }

            const cl = d.cats.map(c => c.ai_category);
            const cv = d.cats.map(c => parseInt(c.n));
            if (!catChart) {
                catChart = new Chart(document.getElementById('cat-chart'), {
                    type: 'doughnut',
                    data: { labels: cl, datasets: [{ data: cv, backgroundColor: PALETTE, borderColor: '#161b22', borderWidth: 2 }] },
                    options: { plugins: { legend: { position: 'right', labels: { color: '#e6edf3', padding: 12, boxWidth: 12 } } } }
                });
            } else {
                catChart.data.labels = cl;
                catChart.data.datasets[0].data = cv;
                catChart.update('none');
            }

            const tbody = document.getElementById('recent-tbody');
            tbody.innerHTML = d.recent.map(r => `<tr><td>${esc(r.created_at)}</td><td>${esc(r.username||'—')}</td><td><span class="badge ${actionBadgeCls(r.action)}">${esc(r.action)}</span></td><td>${esc(r.detail)}</td><td>${esc(r.ip_address)}</td></tr>`).join('');
        } catch (e) {
            SIDMS.toast?.error('Dashboard refresh failed');
        }
    }

    refresh();
    setInterval(refresh, 5000);
})();