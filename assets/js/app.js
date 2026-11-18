/* Util bersama: toast, confetti, fetch helper */
(function () {
    'use strict';

    const toastEl = document.getElementById('toast');
    let toastTimer = null;

    window.showToast = function (message, type) {
        if (!toastEl) return;
        toastEl.textContent = message;
        toastEl.classList.toggle('err', type === 'err');
        toastEl.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toastEl.classList.remove('show'), 3200);
    };

    window.esc = function (value) {
        return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[ch]));
    };

    window.formatScore = function (value) {
        return Number(value || 0).toLocaleString('id-ID');
    };

    window.formatTime = function (ms) {
        return (Number(ms || 0) / 1000).toLocaleString('id-ID', {
            minimumFractionDigits: 1,
            maximumFractionDigits: 1,
        }) + 's';
    };

    /** Ambil JSON dari endpoint aplikasi, dengan opsi POST JSON + CSRF. */
    window.apiFetch = async function (path, options = {}) {
        const opts = Object.assign({ method: 'GET', headers: {} }, options);

        if (opts.body && !(opts.body instanceof FormData)) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(opts.body);
        }
        if (opts.method !== 'GET') {
            opts.headers['X-CSRF-Token'] = window.APP_CSRF || '';
        }

        const response = await fetch(window.APP_BASE + path, opts);
        let data = null;
        try {
            data = await response.json();
        } catch (err) {
            throw new Error('Respons server tidak valid.');
        }
        if (!response.ok && (!data || !data.ok)) {
            const error = new Error((data && data.error) || 'Permintaan gagal.');
            error.data = data;
            throw error;
        }
        return data;
    };

    /** Konfeti ringan untuk momen menang. */
    window.celebrate = function (count = 70) {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        const layer = document.createElement('div');
        layer.className = 'confetti-layer';
        const colors = ['#ffcb45', '#7c5cff', '#24d67a', '#3d9bff', '#ff4d6d', '#ff9f43'];

        for (let i = 0; i < count; i++) {
            const piece = document.createElement('span');
            piece.className = 'confetti-piece';
            piece.style.left = Math.random() * 100 + 'vw';
            piece.style.background = colors[Math.floor(Math.random() * colors.length)];
            piece.style.animationDuration = 1.9 + Math.random() * 1.5 + 's';
            piece.style.animationDelay = Math.random() * 0.45 + 's';
            piece.style.setProperty('--dx', (Math.random() * 180 - 90) + 'px');
            piece.style.opacity = String(0.65 + Math.random() * 0.35);
            if (Math.random() > 0.6) piece.style.borderRadius = '50%';
            layer.appendChild(piece);
        }

        document.body.appendChild(layer);
        setTimeout(() => layer.remove(), 4200);
    };
})();
