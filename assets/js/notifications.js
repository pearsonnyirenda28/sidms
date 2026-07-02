(function(global) {
    'use strict';

    const container = document.getElementById('toast-container');
    if (!container) return;

    const icons = {
        success: 'bi-check-circle-fill',
        error:   'bi-x-circle-fill',
        warning: 'bi-exclamation-triangle-fill',
        info:    'bi-info-circle-fill'
    };
    const colors = {
        success: 'toast-success',
        error:   'toast-error',
        warning: 'toast-warning',
        info:    'toast-info'
    };

    function playBeep() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = 800;
            gain.gain.value = 0.1;
            osc.start();
            osc.stop(ctx.currentTime + 0.1);
        } catch (e) {}
    }

    function showToast(msg, type = 'info', duration = 4000) {
        const toast = document.createElement('div');
        toast.className = `sidms-toast ${colors[type] || 'toast-info'}`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `<div class="toast-icon"><i class="bi ${icons[type] || icons.info}"></i></div><div class="toast-content">${escHtml(msg)}</div><button class="toast-close"><i class="bi bi-x-lg"></i></button>`;
        container.appendChild(toast);
        playBeep();
        toast.querySelector('.toast-close').addEventListener('click', () => dismissToast(toast));
        if (duration > 0) setTimeout(() => dismissToast(toast), duration);
        return toast;
    }

    function dismissToast(toast) {
        toast.classList.add('fade-out');
        setTimeout(() => { if (toast.parentNode === container) container.removeChild(toast); }, 200);
    }

    function showConfirm(message, title = 'Confirm', confirmText = 'Confirm', cancelText = 'Cancel') {
        return new Promise(resolve => {
            const overlay = document.createElement('div');
            overlay.className = 'confirm-overlay';
            overlay.innerHTML = `<div class="confirm-dialog">
                <h5>${escHtml(title)}</h5><p>${escHtml(message)}</p>
                <div class="btn-group">
                    <button class="btn btn-outline-secondary" id="confirm-cancel">${escHtml(cancelText)}</button>
                    <button class="btn btn-danger" id="confirm-ok">${escHtml(confirmText)}</button>
                </div></div>`;
            document.body.appendChild(overlay);
            const cancel = overlay.querySelector('#confirm-cancel');
            const ok = overlay.querySelector('#confirm-ok');
            const cleanup = () => document.body.removeChild(overlay);
            cancel.addEventListener('click', () => { cleanup(); resolve(false); });
            ok.addEventListener('click', () => { cleanup(); resolve(true); });
            overlay.addEventListener('click', e => { if (e.target === overlay) { cleanup(); resolve(false); } });
            document.addEventListener('keydown', function escHandler(e) {
                if (e.key === 'Escape') { cleanup(); resolve(false); document.removeEventListener('keydown', escHandler); }
            });
            ok.focus();
        });
    }

    function escHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    global.SIDMS = global.SIDMS || {};
    global.SIDMS.toast = {
        success: (msg, dur) => showToast(msg, 'success', dur),
        error:   (msg, dur) => showToast(msg, 'error', dur),
        warning: (msg, dur) => showToast(msg, 'warning', dur),
        info:    (msg, dur) => showToast(msg, 'info', dur),
        confirm: showConfirm,
        dismiss: dismissToast
    };
})(window);