/* Halaman kuis: timer kosmetik, pengiriman jawaban, transisi soal */
(function () {
    'use strict';

    const root = document.getElementById('game');
    if (!root) return;

    const state = {
        token: root.dataset.token,
        total: Number(root.dataset.total || 0),
        index: Number(root.dataset.index || 0),
        duration: Number(root.dataset.duration || 15),
        score: Number(root.dataset.score || 0),
        fresh: root.dataset.fresh === '1',
        locked: true,
        finishing: false,
        timerId: null,
        deadline: 0,
        muted: readMuted(),
        audio: null,
        streak: 0,
    };

    const els = {
        qText: document.getElementById('q-text'),
        answers: document.getElementById('answers'),
        feedback: document.getElementById('feedback'),
        loading: document.getElementById('loading'),
        progress: document.getElementById('progress-fill'),
        progressTrack: document.querySelector('.progress-track'),
        qNumber: document.getElementById('q-number'),
        qTotal: document.getElementById('q-total'),
        score: document.getElementById('score-value'),
        timerNum: document.getElementById('timer-num'),
        timerBar: document.getElementById('timer-bar'),
        timerRing: document.getElementById('timer-ring'),
        ring: document.getElementById('ring-fg'),
        timerLive: document.getElementById('timer-live'),
        countdown: document.getElementById('countdown'),
        countdownNum: document.getElementById('countdown-num'),
        muteBtn: document.getElementById('mute-btn'),
        muteIcon: document.getElementById('mute-icon'),
        qCard: document.getElementById('q-card'),
        streakChip: document.getElementById('streak-chip'),
        streakValue: document.getElementById('streak-value'),
    };

    const CIRCUMFERENCE = 2 * Math.PI * 25;

    /* ---------------- Audio (WebAudio, tanpa file eksternal) ---------------- */
    function readMuted() {
        try { return localStorage.getItem('quiz_muted') === '1'; }
        catch (err) { return false; }
    }

    function saveMuted(value) {
        try { localStorage.setItem('quiz_muted', value ? '1' : '0'); }
        catch (err) { /* abaikan */ }
    }

    function audioCtx() {
        if (!state.audio) {
            const AudioCtor = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtor) return null;
            state.audio = new AudioCtor();
        }
        if (state.audio.state === 'suspended') {
            state.audio.resume().catch(() => {});
        }
        return state.audio;
    }

    function tone(freq, duration, type, volume, delay) {
        if (state.muted) return;
        const audio = audioCtx();
        if (!audio) return;

        const start = audio.currentTime + (delay || 0);
        const osc = audio.createOscillator();
        const gain = audio.createGain();

        osc.type = type || 'sine';
        osc.frequency.setValueAtTime(freq, start);
        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(volume || 0.16, start + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);

        osc.connect(gain).connect(audio.destination);
        osc.start(start);
        osc.stop(start + duration + 0.05);
    }

    const sfx = {
        correct() {
            tone(660, 0.14, 'triangle', 0.18, 0);
            tone(880, 0.2, 'triangle', 0.16, 0.11);
            tone(1180, 0.26, 'triangle', 0.13, 0.22);
        },
        wrong() {
            tone(220, 0.22, 'sawtooth', 0.1, 0);
            tone(160, 0.32, 'sawtooth', 0.1, 0.16);
        },
        tick() { tone(880, 0.05, 'square', 0.045, 0); },
        go() { tone(520, 0.1, 'triangle', 0.14, 0); tone(780, 0.22, 'triangle', 0.14, 0.09); },
    };

    if (state.muted) els.muteIcon.textContent = '🔇';
    els.muteBtn.setAttribute('aria-pressed', state.muted ? 'true' : 'false');

    els.muteBtn.addEventListener('click', () => {
        state.muted = !state.muted;
        saveMuted(state.muted);
        els.muteIcon.textContent = state.muted ? '🔇' : '🔊';
        els.muteBtn.setAttribute('aria-pressed', state.muted ? 'true' : 'false');
        if (!state.muted) tone(720, 0.1, 'sine', 0.1, 0);
    });

    /* ---------------- Timer (kosmetik; server tetap penentu) ---------------- */
    function paintTimer(remaining, totalMs) {
        const seconds = Math.max(0, Math.ceil(remaining / 1000));
        els.timerNum.textContent = String(seconds);

        const ratio = totalMs > 0 ? Math.max(0, Math.min(1, remaining / totalMs)) : 0;
        els.timerBar.style.width = (ratio * 100).toFixed(2) + '%';
        els.ring.style.strokeDashoffset = String(CIRCUMFERENCE * (1 - ratio));

        const danger = remaining <= 5000;
        const warning = remaining <= 10000 && !danger;

        els.timerBar.classList.toggle('is-danger', danger);
        els.timerBar.classList.toggle('is-warning', warning);
        els.timerRing.classList.toggle('is-danger', danger);
        els.timerRing.classList.toggle('is-warning', warning);

        return seconds;
    }

    function stopTimer() {
        if (state.timerId) {
            clearInterval(state.timerId);
            state.timerId = null;
        }
    }

    function runTimer(remainingMs) {
        stopTimer();
        const totalMs = state.duration * 1000;
        state.deadline = performance.now() + remainingMs;

        let lastSecond = -1;
        paintTimer(remainingMs, totalMs);

        state.timerId = setInterval(() => {
            const remaining = Math.max(0, state.deadline - performance.now());
            const seconds = paintTimer(remaining, totalMs);

            if (seconds !== lastSecond) {
                lastSecond = seconds;
                if (seconds <= 5 && seconds > 0) sfx.tick();
                if (seconds <= 10 && seconds > 0) els.timerLive.textContent = seconds + ' detik tersisa';
            }

            if (remaining <= 0) {
                stopTimer();
                handleTimeout();
            }
        }, 80);
    }

    /* ---------------- Render ---------------- */
    function setProgress(index) {
        const percent = state.total > 0 ? Math.min(100, (index / state.total) * 100) : 0;
        els.progress.style.width = percent.toFixed(2) + '%';
        if (els.progressTrack) els.progressTrack.setAttribute('aria-valuenow', String(index));
        els.qNumber.textContent = String(Math.min(index + 1, state.total));
        els.qTotal.textContent = String(state.total);
    }

    function renderQuestion(payload) {
        state.index = payload.index;
        state.total = payload.total;
        state.duration = payload.duration;

        if (els.loading) els.loading.hidden = true;

        els.qText.textContent = payload.question.text;
        els.answers.innerHTML = '';

        Object.keys(payload.question.options).forEach((key) => {
            const text = payload.question.options[key];
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'answer';
            button.dataset.key = key;
            button.setAttribute('aria-label', 'Pilihan ' + key + '. ' + text);
            button.innerHTML =
                '<span class="answer-key" aria-hidden="true">' + window.esc(key) + '</span>' +
                '<span class="answer-text">' + window.esc(text) + '</span>';
            button.addEventListener('click', () => submit(button, key));
            els.answers.appendChild(button);
        });

        // Tiap pilihan muncul berurutan agar terasa "di-deal" seperti kartu.
        Array.from(els.answers.children).forEach((el, i) => {
            el.classList.add('fx-answer-in');
            el.style.animationDelay = (i * 55) + 'ms';
        });

        els.qCard.style.animation = 'none';
        void els.qCard.offsetWidth;
        els.qCard.style.animation = '';

        setProgress(state.index);
        els.feedback.hidden = true;
        els.feedback.innerHTML = '';
        state.locked = false;
        runTimer(Math.max(0, payload.remaining_ms));
    }

    function setScore(value) {
        state.score = value;
        els.score.textContent = window.formatScore(value);
        if (els.score.animate) {
            els.score.animate(
                [{ transform: 'scale(1)' }, { transform: 'scale(1.22)' }, { transform: 'scale(1)' }],
                { duration: 340, easing: 'cubic-bezier(.22,1,.36,1)' }
            );
        }
    }

    function popScore(gained) {
        if (gained <= 0) return;
        const pop = document.createElement('div');
        pop.className = 'score-pop';
        pop.textContent = '+' + gained;
        const rect = els.score.getBoundingClientRect();
        pop.style.left = rect.left + rect.width / 2 + 'px';
        pop.style.top = rect.top + 'px';
        document.body.appendChild(pop);
        setTimeout(() => pop.remove(), 1100);
    }

    /** Tampilkan chip streak (mulai dari 2 jawaban benar berturut-turut). */
    function updateStreak() {
        if (!els.streakChip) return;

        if (state.streak >= 2) {
            els.streakChip.hidden = false;
            els.streakValue.textContent = String(state.streak);
            els.streakChip.classList.remove('is-hot');
            void els.streakChip.offsetWidth;
            els.streakChip.classList.add('is-hot');
        } else {
            els.streakChip.hidden = true;
        }
    }

    /* ---------------- Alur jawaban ---------------- */
    async function submit(button, key) {
        if (state.locked || state.finishing) return;
        state.locked = true;
        stopTimer();

        Array.from(els.answers.children).forEach((el) => { el.disabled = true; });
        if (button) button.classList.add('is-selected');

        await sendAnswer(key);
    }

    function handleTimeout() {
        if (state.locked || state.finishing) return;
        state.locked = true;
        stopTimer();
        Array.from(els.answers.children).forEach((el) => { el.disabled = true; });
        sendAnswer(null);
    }

    async function sendAnswer(key) {
        let data;
        try {
            data = await window.apiFetch('/api/answer.php', {
                method: 'POST',
                body: { token: state.token, answer: key },
            });
        } catch (error) {
            const message = (error.data && error.data.error) || 'Gagal mengirim jawaban. Coba lagi.';
            window.showToast(message, 'err');
            state.locked = false;
            const left = state.deadline - performance.now();
            if (!state.finishing && left > 0) runTimer(left);
            return;
        }

        if (data.finished) state.finishing = true;

        revealFeedback(data, key);
    }

    function revealFeedback(data, chosenKey) {
        const correctKey = data.correct_answer;

        Array.from(els.answers.children).forEach((el) => {
            el.disabled = true;
            // Bersihkan animasi masuk agar animasi shake/verdict tidak tertunda.
            el.classList.remove('fx-answer-in');
            el.style.animationDelay = '';
            const key = el.dataset.key;

            if (key === correctKey) {
                el.classList.add('is-correct');
                el.insertAdjacentHTML('beforeend', '<span class="verdict" aria-hidden="true">✓</span>');
            } else if (chosenKey && key === chosenKey) {
                el.classList.add('is-wrong');
                el.insertAdjacentHTML('beforeend', '<span class="verdict" aria-hidden="true">✕</span>');
            } else {
                el.classList.add('is-dimmed');
            }
        });

        if (data.gained > 0) {
            setScore(Number(data.score));
            popScore(data.gained);
        } else if (typeof data.score === 'number') {
            setScore(Number(data.score));
        }

        const isCorrect = Boolean(data.is_correct);
        const timedOut = Boolean(data.timed_out);
        const chosenBtn = chosenKey
            ? els.answers.querySelector('.answer[data-key="' + chosenKey + '"]')
            : null;

        if (isCorrect) sfx.correct();
        else sfx.wrong();

        // Efek piksel + kilat layar mengikuti jawaban.
        if (isCorrect) {
            state.streak += 1;
            if (chosenBtn && window.pixelBurst) {
                const rect = chosenBtn.getBoundingClientRect();
                window.pixelBurst(
                    rect.left + rect.width / 2,
                    rect.top + rect.height / 2,
                    ['#00fa64', '#00f0ff', '#dbfcff'],
                    18
                );
            }
            if (window.fxFlash) window.fxFlash('rgba(0, 250, 100, .14)');
        } else {
            state.streak = 0;
            if (window.fxFlash) window.fxFlash('rgba(255, 74, 141, .12)');
        }
        updateStreak();

        els.feedback.hidden = false;
        els.feedback.className = 'feedback ' + (isCorrect ? 'ok' : 'bad');
        els.feedback.innerHTML =
            '<div class="feedback-head">' +
                (isCorrect
                    ? '<span aria-hidden="true">🎉</span> Benar!'
                    : timedOut
                        ? '<span aria-hidden="true">⏰</span> Waktu habis!'
                        : '<span aria-hidden="true">😖</span> Salah!') +
            '</div>' +
            (isCorrect && state.streak >= 3
                ? '<p class="streak-note">🔥 Streak ' + state.streak + '× — pertahankan!</p>'
                : '') +
            (data.explanation ? '<p>' + window.esc(data.explanation) + '</p>' : '') +
            '<p class="keyline">Jawaban benar: <b>' + window.esc(correctKey) + '</b>' +
                (chosenKey && !isCorrect ? ' — jawabanmu: ' + window.esc(chosenKey) : '') + '</p>' +
            '<div class="feedback-actions">' +
                '<button type="button" class="btn btn-sm" id="next-btn">' +
                    (data.finished ? 'Lihat Hasil <span aria-hidden="true">🏁</span>'
                                   : 'Soal Berikutnya <span aria-hidden="true">→</span>') +
                '</button>' +
            '</div>';

        const nextBtn = els.feedback.querySelector('#next-btn');
        nextBtn.addEventListener('click', () => {
            if (data.finished) window.location.href = data.result_url;
            else loadQuestion();
        });
        nextBtn.focus({ preventScroll: true });
        els.feedback.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    /* ---------------- Muat soal ---------------- */
    async function loadQuestion() {
        els.feedback.hidden = true;
        els.feedback.innerHTML = '';
        els.qText.textContent = 'Memuat soal…';
        els.answers.innerHTML = '';
        if (els.loading) {
            els.loading.hidden = false;
        }
        state.locked = true;

        try {
            const data = await window.apiFetch(
                '/api/question.php?token=' + encodeURIComponent(state.token)
            );

            if (data.finished) {
                window.location.href = data.result_url;
                return;
            }

            renderQuestion(data);
        } catch (error) {
            window.showToast('Gagal memuat soal. Mencoba lagi…', 'err');
            setTimeout(loadQuestion, 1500);
        }
    }

    /* ---------------- Keyboard 1-4 / A-D ---------------- */
    document.addEventListener('keydown', (event) => {
        if (event.ctrlKey || event.metaKey || event.altKey) return;

        const keyMap = {
            '1': 'A', '2': 'B', '3': 'C', '4': 'D',
            'a': 'A', 'b': 'B', 'c': 'C', 'd': 'D',
        };
        const key = keyMap[event.key.toLowerCase()];
        if (!key) return;

        if (!els.feedback.hidden) {
            const nextBtn = document.getElementById('next-btn');
            if (nextBtn) {
                event.preventDefault();
                nextBtn.click();
            }
            return;
        }

        const button = els.answers.querySelector('.answer[data-key="' + key + '"]');
        if (button && !button.disabled) {
            event.preventDefault();
            button.click();
        }
    });

    /* ---------------- Countdown pembuka ---------------- */
    function startCountdown(onDone) {
        const steps = ['3', '2', '1', 'GO!'];
        let step = 0;
        els.countdown.hidden = false;
        els.countdown.classList.add('is-active');
        els.countdown.classList.remove('is-go');

        const show = () => {
            if (step >= steps.length) {
                els.countdown.hidden = true;
                els.countdown.classList.remove('is-active', 'is-go');
                onDone();
                return;
            }

            els.countdownNum.textContent = steps[step];
            els.countdown.classList.toggle('is-go', steps[step] === 'GO!');
            els.countdownNum.style.animation = 'none';
            void els.countdownNum.offsetWidth;
            els.countdownNum.style.animation = '';

            if (steps[step] === 'GO!') {
                sfx.go();
                if (window.fxFlash) window.fxFlash('rgba(0, 240, 255, .2)');
            } else {
                sfx.tick();
            }

            // Ledakan piksel di sekitar angka hitung mundur.
            if (window.pixelBurst) {
                const rect = els.countdownNum.getBoundingClientRect();
                window.pixelBurst(
                    rect.left + rect.width / 2,
                    rect.top + rect.height / 2,
                    steps[step] === 'GO!'
                        ? ['#00f0ff', '#00fa64', '#dbfcff']
                        : ['#00f0ff', '#ff4a8d', '#ffcb45'],
                    steps[step] === 'GO!' ? 30 : 10
                );
            }

            step++;
            setTimeout(show, steps[step - 1] === 'GO!' ? 520 : 760);
        };

        show();
    }

    /* ---------------- Init ---------------- */
    setProgress(state.index);

    if (state.fresh) {
        // Sesi baru: hitung mundur dulu, lalu minta soal pertama ke server.
        els.timerNum.textContent = String(state.duration);
        paintTimer(state.duration * 1000, state.duration * 1000);
        startCountdown(loadQuestion);
    } else {
        // Lanjut setelah reload: langsung minta soal (timer server tetap jalan).
        loadQuestion();
    }
})();
