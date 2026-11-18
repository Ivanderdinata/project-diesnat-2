/* Home page: validasi form + mulai kuis */
(function () {
    'use strict';

    const form = document.getElementById('start-form');
    if (!form) return;

    const btn = document.getElementById('start-btn');
    const nameInput = document.getElementById('player-name');
    const kelasInput = document.getElementById('player-kelas');
    const errName = document.getElementById('err-name');
    const errKelas = document.getElementById('err-kelas');

    function setError(input, el, message) {
        el.textContent = message || '';
        input.closest('.field').classList.toggle('has-error', Boolean(message));
        input.setAttribute('aria-invalid', message ? 'true' : 'false');
    }

    function validate() {
        let ok = true;
        const name = nameInput.value.trim();
        const kelas = kelasInput.value.trim();

        if (name.length < 2) {
            setError(nameInput, errName, 'Nama minimal 2 karakter.');
            ok = false;
        } else if (name.length > 50) {
            setError(nameInput, errName, 'Nama maksimal 50 karakter.');
            ok = false;
        } else {
            setError(nameInput, errName, '');
        }

        if (kelas.length < 1) {
            setError(kelasInput, errKelas, 'Kelas wajib diisi.');
            ok = false;
        } else if (kelas.length > 30) {
            setError(kelasInput, errKelas, 'Kelas maksimal 30 karakter.');
            ok = false;
        } else {
            setError(kelasInput, errKelas, '');
        }

        return ok;
    }

    [nameInput, kelasInput].forEach((input) => {
        input.addEventListener('input', () => {
            if (input === nameInput) setError(input, errName, '');
            else setError(input, errKelas, '');
        });
    });

    /** Overlay roket "liftoff" sebelum pindah ke halaman kuis. */
    function showLaunch() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return false;

        const overlay = document.createElement('div');
        overlay.className = 'launch-overlay';

        const source = document.querySelector('.px-hero-rocket svg');
        const rocket = source
            ? source.outerHTML
            : '<span style="font-size:64px">🚀</span>';

        const astroSource = document.querySelector('.px-hero-astro svg');
        const astro = astroSource
            ? '<div class="launch-astro">' + astroSource.outerHTML + '</div>'
            : '';

        overlay.innerHTML =
            astro +
            '<div class="launch-rocket">' + rocket + '</div>' +
            '<p class="launch-text">Liftoff!</p>';

        document.body.appendChild(overlay);
        setTimeout(() => overlay.remove(), 1500);
        return true;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!validate()) {
            (document.querySelector('.has-error .input') || nameInput).focus();
            return;
        }

        btn.disabled = true;
        const original = btn.innerHTML;
        btn.innerHTML = '<span>Memuat…</span>';
        let navigating = false;

        try {
            const response = await fetch(window.APP_BASE + '/api/start.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.APP_CSRF,
                },
                body: JSON.stringify({
                    name: nameInput.value.trim(),
                    kelas: kelasInput.value.trim(),
                    csrf_token: window.APP_CSRF,
                }),
            });

            const data = await response.json();

            if (!data.ok) {
                if (data.errors) {
                    if (data.errors.name) setError(nameInput, errName, data.errors.name);
                    if (data.errors.kelas) setError(kelasInput, errKelas, data.errors.kelas);
                }
                window.showToast(data.error || 'Gagal memulai kuis.', 'err');
                return;
            }

            navigating = true;

            // Simpan identitas untuk sorotan "KAMU" di halaman leaderboard.
            try {
                localStorage.setItem('quiz_last_entry', JSON.stringify({
                    name: nameInput.value.trim(),
                    kelas: kelasInput.value.trim(),
                }));
            } catch (err) { /* abaikan */ }

            const launched = showLaunch();

            // Saat overlay peluncuran tampil, tunda sebentar agar animasi
            // roket sempat terlihat sebelum halaman kuis terbuka.
            window.setTimeout(() => {
                window.location.href = data.play_url;
            }, launched ? 820 : 0);
        } catch (error) {
            console.error(error);
            window.showToast('Koneksi bermasalah. Coba lagi.', 'err');
        } finally {
            // Jangan pulihkan tombol bila halaman sedang berpindah,
            // agar tidak terlihat seperti bisa diklik dua kali.
            if (!navigating) {
                btn.disabled = false;
                btn.innerHTML = original;
            }
        }
    });
})();
