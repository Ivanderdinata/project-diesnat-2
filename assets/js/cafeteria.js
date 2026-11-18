/* ============================================================
   ASTRO-DINER 77 — Cafeteria eksplorasi pixel.
   Karakter astronot digerakkan WASD / panah di stasiun multi-zona.
   Interaksi: mesin kuis, terminal leaderboard, pintu keluar,
   mini-game arcade, NPC, dan dek observasi rahasia.
   Tanpa library.
   ============================================================ */
(function () {
    'use strict';

    const CONFIG = window.CAFE_CONFIG || {};
    const URLS = CONFIG.urls || {};
    const TOP = Array.isArray(CONFIG.top) ? CONFIG.top : [];

    const canvas = document.getElementById('worldCanvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const miniCanvas = document.getElementById('minimapCanvas');
    const miniCtx = miniCanvas ? miniCanvas.getContext('2d') : null;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ==========================================================
       1. AUDIO RETRO (WebAudio, tanpa file)
       ========================================================== */
    const RetroAudio = {
        ctx: null,
        enabled: true,

        init() {
            if (!this.ctx) {
                const Ctor = window.AudioContext || window.webkitAudioContext;
                if (Ctor) this.ctx = new Ctor();
            }
            if (this.ctx && this.ctx.state === 'suspended') {
                this.ctx.resume().catch(() => {});
            }
        },

        tone(freq, type, duration, volume, slideTo) {
            if (!this.enabled) return;
            this.init();
            if (!this.ctx) return;

            const now = this.ctx.currentTime;
            const osc = this.ctx.createOscillator();
            const gain = this.ctx.createGain();

            osc.type = type || 'square';
            osc.frequency.setValueAtTime(freq, now);
            if (slideTo) osc.frequency.exponentialRampToValueAtTime(slideTo, now + duration);

            gain.gain.setValueAtTime(volume || 0.05, now);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + duration);

            osc.connect(gain).connect(this.ctx.destination);
            osc.start(now);
            osc.stop(now + duration + 0.02);
        },

        step()  { this.tone(130 + Math.random() * 25, 'triangle', 0.04, 0.022); },
        blip()  { this.tone(540, 'square', 0.06, 0.045, 720); },
        coin()  { this.tone(987, 'square', 0.07, 0.045); setTimeout(() => this.tone(1318, 'square', 0.14, 0.045), 70); },
        laser() { this.tone(880, 'square', 0.1, 0.045, 220); },
        boom()  { this.tone(140, 'sawtooth', 0.18, 0.06, 42); },
        door(open) {
            if (open) {
                this.tone(160, 'sawtooth', 0.35, 0.055, 380);
                setTimeout(() => this.tone(620, 'square', 0.1, 0.045, 880), 260);
            } else {
                this.tone(340, 'sawtooth', 0.35, 0.055, 120);
            }
        },
        warp() {
            [392, 523, 659, 784].forEach((f, i) => {
                setTimeout(() => this.tone(f, 'triangle', 0.14, 0.05), i * 90);
            });
        }
    };

    /* ==========================================================
       2. PETA STASIUN
       ========================================================== */
    const TILE = 32;
    const MAP_COLS = 42;
    const MAP_ROWS = 30;

    // 0 kosong/hampa, 1 lantai utama, 2 dinding, 3 jendela angkasa,
    // 4 lantai checkerboard, 5 karpet lounge, 6 dek airlock, 7 garis hazard.
    const mapGrid = Array.from({ length: MAP_ROWS }, () => Array(MAP_COLS).fill(0));

    (function buildMap() {
        // Lambung utama
        for (let r = 3; r <= 26; r++) {
            for (let c = 2; c <= 31; c++) {
                const edge = r === 3 || r === 26 || c === 2 || c === 31;
                mapGrid[r][c] = edge ? 2 : 1;
            }
        }

        // Jendela panorama
        for (let c = 5; c <= 13; c++) mapGrid[3][c] = 3;
        for (let c = 18; c <= 26; c++) mapGrid[3][c] = 3;
        for (let r = 16; r <= 23; r++) mapGrid[r][2] = 3;

        // Lounge
        for (let r = 15; r <= 25; r++) {
            for (let c = 3; c <= 12; c++) mapGrid[r][c] = 5;
        }

        // Konter makanan
        for (let r = 4; r <= 10; r++) {
            for (let c = 3; c <= 15; c++) mapGrid[r][c] = 4;
        }

        // Sudut arcade
        for (let r = 9; r <= 17; r++) {
            for (let c = 24; c <= 30; c++) mapGrid[r][c] = 4;
        }

        // Dek observasi rahasia
        for (let r = 11; r <= 19; r++) {
            for (let c = 31; c <= 39; c++) {
                const edge = r === 11 || r === 19 || c === 39 || c === 31;
                mapGrid[r][c] = edge ? 2 : 6;
            }
        }
        for (let r = 13; r <= 17; r++) mapGrid[r][39] = 3;

        // Ambang pintu sekat
        [[14, 31], [15, 31], [14, 30], [15, 30], [14, 32], [15, 32]].forEach(([r, c]) => {
            mapGrid[r][c] = 7;
        });
    })();

    /* ==========================================================
       3. LATAR ANGKASA
       ========================================================== */
    const stars = Array.from({ length: 160 }, () => ({
        x: Math.random() * 1800,
        y: Math.random() * 1200,
        size: Math.random() < 0.75 ? 2 : 3,
        twinkle: Math.random() * Math.PI * 2,
        speed: 0.05 + Math.random() * 0.15,
        color: ['#ffffff', '#bae6fd', '#fde68a', '#f5d0fe'][Math.floor(Math.random() * 4)]
    }));

    const asteroids = Array.from({ length: 7 }, (_, i) => ({
        x: 150 + i * 240,
        y: 80 + (i % 4) * 260,
        r: 10 + (i % 3) * 6,
        vx: 0.25 + (i % 3) * 0.12
    }));

    /* ==========================================================
       4. PINTU SEKAAT + PROPS
       ========================================================== */
    const bulkhead = {
        x: 31 * TILE,
        y: 14 * TILE,
        w: TILE,
        h: TILE * 2,
        isOpen: false,
        progress: 0,
        announced: false
    };

    const props = [
        // Konter & peralatan
        { type: 'counter_h', x: 4 * TILE, y: 7 * TILE, w: 9 * TILE, h: TILE, solid: true },
        { type: 'coffee_machine', x: 5 * TILE, y: 6 * TILE + 8, w: TILE, h: TILE - 8, solid: false },
        { type: 'slushie_machine', x: 7 * TILE, y: 6 * TILE + 8, w: TILE, h: TILE - 8, solid: false },
        { type: 'food_dispenser', x: 10 * TILE, y: 4 * TILE, w: 2 * TILE, h: TILE + 8, solid: true },
        { type: 'fridge_glow', x: 13 * TILE, y: 4 * TILE, w: 2 * TILE, h: TILE + 8, solid: true },
        { type: 'neon_menu', x: 6 * TILE, y: 3 * TILE + 6, w: 3 * TILE, h: 18, solid: false },

        // Kursi bar
        { type: 'stool', x: 5 * TILE + 6, y: 8 * TILE + 8, w: 20, h: 20, solid: true },
        { type: 'stool', x: 7 * TILE + 6, y: 8 * TILE + 8, w: 20, h: 20, solid: true },
        { type: 'stool', x: 9 * TILE + 6, y: 8 * TILE + 8, w: 20, h: 20, solid: true },
        { type: 'stool', x: 11 * TILE + 6, y: 8 * TILE + 8, w: 20, h: 20, solid: true },

        // Meja makan
        { type: 'dining_table', x: 16 * TILE, y: 7 * TILE, w: 2 * TILE, h: TILE + 12, solid: true, food: 'burger' },
        { type: 'dining_table', x: 20 * TILE, y: 7 * TILE, w: 2 * TILE, h: TILE + 12, solid: true, food: 'ramen' },
        { type: 'dining_table', x: 16 * TILE, y: 12 * TILE, w: 2 * TILE, h: TILE + 12, solid: true, food: 'pie' },
        { type: 'dining_table', x: 20 * TILE, y: 12 * TILE, w: 2 * TILE, h: TILE + 12, solid: true, food: 'drink' },
        { type: 'dining_table', x: 16 * TILE, y: 18 * TILE, w: 2 * TILE, h: TILE + 12, solid: true, food: 'ramen' },
        { type: 'dining_table', x: 21 * TILE, y: 19 * TILE, w: 2 * TILE, h: TILE + 12, solid: true, food: 'burger' },

        // Lounge
        { type: 'lounge_sofa_v', x: 4 * TILE, y: 17 * TILE, w: TILE + 6, h: 3 * TILE, solid: true },
        { type: 'holo_table', x: 6 * TILE + 8, y: 18 * TILE, w: TILE + 16, h: TILE + 16, solid: true },
        { type: 'lounge_sofa_h', x: 5 * TILE, y: 23 * TILE, w: 3 * TILE, h: TILE + 6, solid: true },

        // Kabinet arcade
        {
            type: 'arcade_cab', id: 'arc_quiz', name: 'QUIZ DIES NAT', mode: 'quiz',
            color: '#34d399', screenColor: '#00f0ff',
            x: 25 * TILE, y: 9 * TILE, w: TILE + 4, h: TILE + 18, solid: true
        },
        {
            type: 'arcade_cab', id: 'arc2', name: 'NEBULA INVADERS', mode: 'game',
            color: '#a855f7', screenColor: '#4ade80',
            x: 27 * TILE, y: 9 * TILE, w: TILE + 4, h: TILE + 18, solid: true
        },
        {
            type: 'arcade_cab', id: 'arc3', name: 'ASTEROID DASH DX', mode: 'game',
            color: '#f59e0b', screenColor: '#facc15',
            x: 29 * TILE, y: 9 * TILE, w: TILE + 4, h: TILE + 18, solid: true
        },
        { type: 'claw_machine', x: 25 * TILE, y: 15 * TILE + 8, w: TILE + 6, h: TILE + 16, solid: true },
        { type: 'jukebox', x: 27 * TILE + 8, y: 15 * TILE + 10, w: TILE + 4, h: TILE + 14, solid: true },
        { type: 'door_keypad', x: 30 * TILE + 6, y: 13 * TILE + 6, w: 20, h: 24, solid: true },

        // Dek rahasia
        { type: 'star_telescope', x: 36 * TILE + 8, y: 14 * TILE + 8, w: TILE + 12, h: TILE + 12, solid: true },
        { type: 'cargo_crate', x: 33 * TILE, y: 12 * TILE, w: TILE + 8, h: TILE, solid: true },
        { type: 'cargo_crate', x: 33 * TILE, y: 17 * TILE + 8, w: TILE + 8, h: TILE, solid: true },
        { type: 'captain_chair', x: 35 * TILE, y: 12 * TILE + 6, w: TILE + 6, h: TILE + 6, solid: true },

        // Papan leaderboard besar di dek utama (dinding utara, area kosong)
        {
            type: 'leaderboard_board',
            x: 22 * TILE, y: 4 * TILE,
            w: 3 * TILE + 8, h: 2 * TILE + 16, solid: true
        },

        // Pintu keluar ke beranda (dinding selatan)
        {
            type: 'exit_door',
            x: 18 * TILE + 8, y: 25 * TILE + 6,
            w: TILE + 14, h: TILE + 20, solid: true
        },

        // Tanaman alien
        { type: 'alien_plant', x: 14 * TILE, y: 4 * TILE + 12, w: 26, h: 28, solid: true },
        { type: 'alien_plant', x: 29 * TILE + 4, y: 4 * TILE + 12, w: 26, h: 28, solid: true },
        { type: 'alien_plant', x: 3 * TILE + 6, y: 14 * TILE, w: 26, h: 28, solid: true },
        { type: 'alien_plant', x: 12 * TILE, y: 24 * TILE + 4, w: 26, h: 28, solid: true },
        { type: 'alien_plant', x: 29 * TILE + 4, y: 24 * TILE + 4, w: 26, h: 28, solid: true },
        { type: 'alien_plant', x: 37 * TILE + 6, y: 12 * TILE, w: 26, h: 28, solid: true }
    ];

    /* ==========================================================
       5. PEMAIN, NPC & KOLEKSI
       ========================================================== */
    const player = {
        x: 14 * TILE,
        y: 15 * TILE,
        w: 22,
        h: 24,
        speed: 155,
        dir: 'down',
        animTimer: 0,
        animFrame: 0,
        credits: 50,
        shards: 0,
        moving: false
    };

    const npcs = [
        {
            id: 'chef_bot', name: 'CHEF BEEP-9 (Robot Koki)',
            x: 8 * TILE, y: 5 * TILE + 12, w: 24, h: 26,
            type: 'robot', color: '#38bdf8', visor: '#fbbf24', bob: 0,
            lines: [
                'BZZT! Selamat datang di Astro-Diner 77, Kapten! Hari ini spesial Ramen Nebula Pedas dan Burger Meteor.',
                'Lihat terminal papan skor di dekat lounge? Di situ nama-nama legenda stasiun ini terpampang.',
                'Mesin arcade hijau di sudut timur itu bukan mainan — dari situ kamu bisa memulai kuis Dies Natalis!'
            ],
            shop: { name: 'Pesan Kopi Gravitasi Nol (5 ◈)', cost: 5, reply: 'SLURP! Kopi Gravitasi Nol melayang di lidahmu! (+Energi kosmik)' }
        },
        {
            id: 'alien_zorp', name: 'ZORP (Pelancong Andromeda)',
            x: 15 * TILE + 4, y: 7 * TILE + 8, w: 22, h: 24,
            type: 'alien', color: '#4ade80', visor: '#f43f5e', bob: 1.5,
            lines: [
                'Glork-glork! Perjalanan dari Galaksi Andromeda bikin tiga perutku keroncongan.',
                'Aku baru kalah skor di Nebula Invaders. Coba kamu pecahkan rekorku di mesin ungu itu!'
            ]
        },
        {
            id: 'astro_lyra', name: 'KADET LYRA (Teknisi Stasiun)',
            x: 8 * TILE, y: 19 * TILE + 8, w: 22, h: 24,
            type: 'human', color: '#f472b6', visor: '#38bdf8', bob: 3.1,
            lines: [
                'Pemandangan nebula dari jendela lounge selalu bikin tenang setelah shift memperbaiki panel surya.',
                'Psst... Pintu sekat di sebelah mesin arcade sudah kubuka pengamannya. Di baliknya ada dek observasi rahasia!',
                'Konon ada kristal bintang berserakan di sana. Coba kumpulkan.'
            ]
        },
        {
            id: 'gamer_rex', name: 'REX-7 (Jagoan Arcade)',
            x: 26 * TILE + 4, y: 11 * TILE + 6, w: 22, h: 24,
            type: 'robot', color: '#fbbf24', visor: '#a855f7', bob: 4.2,
            lines: [
                'WOOAH! Hampir saja aku menembus gelombang ke-10 di Star Blaster!',
                'Tekan [E] di depan mesin arcade kalau berani menantang skorku!'
            ]
        }
    ];

    // Kristal bintang untuk dieksplorasi
    const shards = [
        { x: 34 * TILE, y: 15 * TILE, taken: false },
        { x: 37 * TILE + 4, y: 17 * TILE, taken: false },
        { x: 5 * TILE, y: 21 * TILE, taken: false },
        { x: 22 * TILE, y: 24 * TILE, taken: false },
        { x: 12 * TILE, y: 16 * TILE, taken: false }
    ];

    /* ==========================================================
       6. STATE & KAMERA
       ========================================================== */
    const camera = { x: 0, y: 0, zoom: 2 };
    const keys = {};

    let activeInteractable = null;
    let dialogueOpen = false;
    let arcadeOpen = false;
    let paused = false;
    let leaving = false;
    let lastTime = performance.now();

    function resizeCanvas() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        ctx.imageSmoothingEnabled = false;
        camera.zoom = window.innerWidth < 640 ? 1.6 : 2.1;
    }
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();

    /* ==========================================================
       7. UTIL
       ========================================================== */
    function overlap(ax, ay, aw, ah, bx, by, bw, bh) {
        return ax < bx + bw && ax + aw > bx && ay < by + bh && ay + ah > by;
    }

    function announce(text) {
        const live = document.getElementById('cafe-live');
        if (!live) return;
        if (live.textContent === text) return;
        live.textContent = text;
    }

    function go(url) {
        if (!url || leaving) return;
        leaving = true;
        RetroAudio.warp();
        const el = document.getElementById('game-container');
        if (el && !reduceMotion) el.classList.add('cafe-fading');
        window.setTimeout(() => { window.location.href = url; }, reduceMotion ? 80 : 380);
    }

    /* ==========================================================
       8. INTERAKTABEL
       ========================================================== */
    function getInteractables() {
        const list = [];

        // Pintu sekat
        list.push({
            x: bulkhead.x - 18,
            y: bulkhead.y - 10,
            w: bulkhead.w + 36,
            h: bulkhead.h + 20,
            label: bulkhead.isOpen ? 'Tutup Pintu Dek Rahasia' : 'Buka Pintu Dek Rahasia',
            run () {
                bulkhead.isOpen = !bulkhead.isOpen;
                RetroAudio.door(bulkhead.isOpen);
                if (bulkhead.isOpen && !bulkhead.announced) {
                    bulkhead.announced = true;
                    openDialogue('SISTEM PINTU SEKTOR-B',
                        'KLIK-HISS! Pintu geser hidrolik di sebelah mesin arcade terbuka. Jalur menuju Dek Observasi Rahasia kini bisa dilewati.');
                }
            }
        });

        // Kabinet arcade
        props.filter((p) => p.type === 'arcade_cab').forEach((cab) => {
            list.push({
                x: cab.x - 6,
                y: cab.y,
                w: cab.w + 12,
                h: cab.h + 24,
                label: cab.mode === 'quiz' ? 'Mulai Kuis Dies Natalis' : 'Mainkan ' + cab.name,
                run () {
                    if (cab.mode === 'quiz') {
                        RetroAudio.coin();

                        // Hitungan mundur otomatis; dibatalkan bila pemain menolak.
                        const launch = window.setTimeout(() => go(URLS.quiz), 2600);

                        openDialogue('MESIN KUIS',
                            'Memuat kuis Dies Natalis... Bersiap, Kapten!', [
                                { label: 'Mulai sekarang', action: () => { clearTimeout(launch); go(URLS.quiz); } },
                                { label: 'Nanti dulu', action: () => { clearTimeout(launch); closeDialogue(); } }
                            ]);
                    } else {
                        RetroAudio.coin();
                        startArcade(cab.name, cab.screenColor);
                    }
                }
            });
        });

        // Papan leaderboard
        const board = props.find((p) => p.type === 'leaderboard_board');
        if (board) {
            const topName = TOP[0] ? TOP[0].name : null;
            list.push({
                x: board.x - 14,
                y: board.y - 14,
                w: board.w + 28,
                h: board.h + 36,
                label: 'Buka Papan Peringkat',
                run () {
                    RetroAudio.blip();
                    const lead = topName
                        ? 'Peringkat teratas saat ini: ' + topName + '.'
                        : 'Papan masih kosong — jadilah yang pertama!';
                    openDialogue('PAPAN HALL OF FAME', lead + ' Lihat papan lengkap?', [
                        { label: 'Lihat papan lengkap', action: () => go(URLS.leaderboard) },
                        { label: 'Tutup', action: closeDialogue }
                    ]);
                }
            });
        }

        // Pintu keluar
        const exit = props.find((p) => p.type === 'exit_door');
        if (exit) {
            list.push({
                x: exit.x - 14,
                y: exit.y - 14,
                w: exit.w + 28,
                h: exit.h + 34,
                label: 'Pergi ke Beranda',
                run () { go(URLS.home); }
            });
        }

        // Mesin capit
        const claw = props.find((p) => p.type === 'claw_machine');
        if (claw) {
            list.push({
                x: claw.x - 8, y: claw.y - 8, w: claw.w + 16, h: claw.h + 24,
                label: 'Mesin Capit Boneka Alien (5 ◈)',
                run () {
                    if (player.credits >= 5) {
                        player.credits -= 5;
                        paintCredits();
                        RetroAudio.coin();
                        const prizes = [
                            'Boneka Mini-Alien Bermata Tiga!',
                            'Gantungan Kunci Roket Retro!',
                            'Plushie Kucing Astronot!',
                            'Yah, capitnya meleset sedikit! Coba lagi lain kali.'
                        ];
                        openDialogue('MESIN CAPIT KOSMIK',
                            'BZZZT... CLANK! Capit turun perlahan... Hasilnya: ' + prizes[Math.floor(Math.random() * prizes.length)]);
                    } else {
                        RetroAudio.blip();
                        openDialogue('MESIN CAPIT KOSMIK', 'Kredit tidak cukup! Kumpulkan kristal bintang atau main arcade untuk mendapat kredit.');
                    }
                }
            });
        }

        // Jukebox
        const juke = props.find((p) => p.type === 'jukebox');
        if (juke) {
            list.push({
                x: juke.x - 8, y: juke.y - 8, w: juke.w + 16, h: juke.h + 24,
                label: 'Putar Jukebox Synthwave',
                run () {
                    RetroAudio.tone(440, 'triangle', 0.12, 0.05);
                    setTimeout(() => RetroAudio.tone(554, 'triangle', 0.12, 0.05), 110);
                    setTimeout(() => RetroAudio.tone(659, 'triangle', 0.2, 0.05), 220);
                    openDialogue('JUKEBOX GALAKSI',
                        '♪ Memutar "Midnight Cruise Over Saturn Rings (8-Bit Remix)" ♪ Suasana kafetaria terasa lebih hangat!');
                }
            });
        }

        // Teleskop
        const tele = props.find((p) => p.type === 'star_telescope');
        if (tele) {
            list.push({
                x: tele.x - 14, y: tele.y - 14, w: tele.w + 28, h: tele.h + 28,
                label: 'Intip Teleskop Kuantum',
                run () {
                    RetroAudio.blip();
                    openDialogue('TELESKOP KUANTUM',
                        'Kamu mengintip melalui lensa kuantum... Terlihat gugus bintang Orion, cincin es raksasa, dan komet berkilau melintas di kejauhan!');
                }
            });
        }

        // Konter makanan
        list.push({
            x: 4 * TILE, y: 8 * TILE, w: 9 * TILE, h: 28,
            label: 'Bicara dengan Chef Beep-9',
            run () {
                const chef = npcs.find((n) => n.id === 'chef_bot');
                if (chef) talkTo(chef);
            }
        });

        // NPC
        npcs.forEach((npc) => {
            list.push({
                x: npc.x - 18, y: npc.y - 18, w: npc.w + 36, h: npc.h + 36,
                label: 'Bicara: ' + npc.name.split(' ')[0],
                run () { talkTo(npc); }
            });
        });

        // Kristal bintang
        shards.forEach((shard) => {
            if (shard.taken) return;
            list.push({
                x: shard.x - 20, y: shard.y - 20, w: 40, h: 40,
                label: 'Ambil Kristal Bintang (+10 ◈)',
                run () {
                    shard.taken = true;
                    player.shards += 1;
                    player.credits += 10;
                    paintCredits();
                    RetroAudio.coin();
                    if (window.pixelBurst) {
                        window.pixelBurst(window.innerWidth / 2, window.innerHeight / 2,
                            ['#fde047', '#34d399', '#38bdf8'], 18);
                    }
                    openDialogue('KRISTAL BINTANG',
                        'Kristal bintang berpendar di tanganmu! +10 ◈  (Terkumpul: ' + player.shards + '/' + shards.length + ')');
                }
            });
        });

        return list;
    }

    /* ==========================================================
       9. DIALOG
       ========================================================== */
    const dialogueBox = document.getElementById('dialogue-box');
    const dialogueSpeaker = document.getElementById('dialogue-speaker');
    const dialogueBody = document.getElementById('dialogue-body');
    const dialogueChoices = document.getElementById('dialogue-choices');

    function openDialogue(speaker, text, choices) {
        dialogueOpen = true;
        dialogueSpeaker.textContent = speaker;
        dialogueBody.textContent = text;
        dialogueChoices.innerHTML = '';

        (choices || []).forEach((choice) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'choice-btn';
            btn.textContent = choice.label;
            btn.addEventListener('click', () => choice.action());
            dialogueChoices.appendChild(btn);
        });

        dialogueBox.style.display = 'flex';
        announce(speaker + ': ' + text);
    }

    function closeDialogue() {
        dialogueOpen = false;
        dialogueBox.style.display = 'none';
        RetroAudio.tone(320, 'square', 0.05, 0.03);
    }

    document.getElementById('dialogue-close-btn').addEventListener('click', closeDialogue);

    function talkTo(npc) {
        RetroAudio.blip();
        if (npc.lineIdx === undefined) npc.lineIdx = 0;
        const text = npc.lines[npc.lineIdx % npc.lines.length];
        npc.lineIdx++;

        const choices = [];
        if (npc.shop) {
            choices.push({
                label: npc.shop.name,
                action() {
                    if (player.credits >= npc.shop.cost) {
                        player.credits -= npc.shop.cost;
                        paintCredits();
                        RetroAudio.coin();
                        dialogueBody.textContent = npc.shop.reply;
                        dialogueChoices.innerHTML = '';
                    } else {
                        dialogueBody.textContent = 'BZZT! Kreditmu tidak cukup, Kapten.';
                        dialogueChoices.innerHTML = '';
                    }
                }
            });
        }

        openDialogue(npc.name, text, choices);
    }

    function paintCredits() {
        const el = document.getElementById('credit-count');
        if (el) el.textContent = String(player.credits);
    }

    /* ==========================================================
       10. MINI-GAME ARCADE
       ========================================================== */
    const arcadeModal = document.getElementById('arcade-modal');
    const arcCanvas = document.getElementById('arcadeCanvas');
    const arcCtx = arcCanvas.getContext('2d');
    let arcState = null;
    let arcLeft = false;
    let arcRight = false;

    function startArcade(title, theme) {
        arcadeOpen = true;
        document.getElementById('arcade-title').textContent = '★ ' + title + ' ★';
        arcadeModal.style.display = 'flex';

        arcState = {
            shipX: 150,
            bullets: [],
            enemies: [],
            score: 0,
            over: false,
            spawn: 0,
            theme: theme || '#38bdf8'
        };
    }

    function closeArcade() {
        if (!arcadeOpen) return;
        arcadeOpen = false;
        arcadeModal.style.display = 'none';

        if (arcState && arcState.score > 0) {
            const reward = Math.max(5, Math.floor(arcState.score / 20));
            player.credits += reward;
            paintCredits();
            openDialogue('MESIN ARCADE',
                'Permainan selesai! Skor akhir: ' + arcState.score + '. Hadiah +' + reward + ' ◈');
        }
        arcState = null;
    }

    document.getElementById('arcade-exit-btn').addEventListener('click', closeArcade);

    function arcadeFire() {
        if (!arcadeOpen || !arcState) return;
        if (arcState.over) {
            arcState.shipX = 150;
            arcState.bullets = [];
            arcState.enemies = [];
            arcState.score = 0;
            arcState.over = false;
            return;
        }
        if (arcState.bullets.length < 4) {
            arcState.bullets.push({ x: arcState.shipX + 8, y: 204 });
            RetroAudio.laser();
        }
    }

    function bindHold(id, setter) {
        const el = document.getElementById(id);
        if (!el) return;
        const on = (e) => { e.preventDefault(); setter(true); };
        const off = (e) => { e.preventDefault(); setter(false); };
        el.addEventListener('mousedown', () => setter(true));
        el.addEventListener('mouseup', () => setter(false));
        el.addEventListener('mouseleave', () => setter(false));
        el.addEventListener('touchstart', on, { passive: false });
        el.addEventListener('touchend', off, { passive: false });
        el.addEventListener('touchcancel', off, { passive: false });
    }

    bindHold('arc-left', (v) => { arcLeft = v; });
    bindHold('arc-right', (v) => { arcRight = v; });
    document.getElementById('arc-fire').addEventListener('click', arcadeFire);

    function drawArcade(dt) {
        if (!arcadeOpen || !arcState) return;

        arcCtx.fillStyle = '#050510';
        arcCtx.fillRect(0, 0, 320, 240);

        arcCtx.fillStyle = '#475569';
        for (let i = 0; i < 20; i++) {
            const sx = (i * 53) % 320;
            const sy = ((i * 37) + performance.now() * 0.05) % 240;
            arcCtx.fillRect(sx, sy, 2, 2);
        }

        if (!arcState.over) {
            if (keys.KeyA || keys.ArrowLeft || arcLeft) arcState.shipX -= 180 * dt;
            if (keys.KeyD || keys.ArrowRight || arcRight) arcState.shipX += 180 * dt;
            arcState.shipX = Math.max(8, Math.min(292, arcState.shipX));

            arcState.bullets.forEach((b) => { b.y -= 280 * dt; });
            arcState.bullets = arcState.bullets.filter((b) => b.y > -10);

            arcState.spawn -= dt;
            if (arcState.spawn <= 0) {
                arcState.enemies.push({
                    x: 16 + Math.random() * 270,
                    y: -16,
                    speed: 55 + Math.min(90, arcState.score * 0.4)
                });
                arcState.spawn = Math.max(0.35, 1.05 - arcState.score * 0.004);
            }

            arcState.enemies.forEach((en) => {
                en.y += en.speed * dt;
                if (en.y > 205) {
                    arcState.over = true;
                    RetroAudio.boom();
                }
                arcState.bullets.forEach((b) => {
                    if (b.x > en.x - 4 && b.x < en.x + 18 && b.y > en.y && b.y < en.y + 16) {
                        en.dead = true;
                        b.y = -99;
                        arcState.score += 10;
                        RetroAudio.boom();
                    }
                });
            });
            arcState.enemies = arcState.enemies.filter((en) => !en.dead);
        }

        const sx = Math.floor(arcState.shipX);
        arcCtx.fillStyle = arcState.theme;
        arcCtx.fillRect(sx + 8, 206, 4, 6);
        arcCtx.fillRect(sx + 4, 212, 12, 6);
        arcCtx.fillRect(sx, 218, 20, 6);

        arcCtx.fillStyle = '#fde047';
        arcState.bullets.forEach((b) => arcCtx.fillRect(Math.floor(b.x), Math.floor(b.y), 3, 8));

        arcCtx.fillStyle = '#f43f5e';
        arcState.enemies.forEach((en) => {
            const ex = Math.floor(en.x);
            const ey = Math.floor(en.y);
            arcCtx.fillRect(ex + 3, ey, 10, 4);
            arcCtx.fillRect(ex, ey + 4, 16, 6);
            arcCtx.fillRect(ex + 2, ey + 10, 3, 4);
            arcCtx.fillRect(ex + 11, ey + 10, 3, 4);
        });

        arcCtx.fillStyle = '#ffffff';
        arcCtx.font = '12px "Press Start 2P", monospace';
        arcCtx.fillText('SKOR: ' + arcState.score, 10, 20);

        if (arcState.over) {
            arcCtx.fillStyle = 'rgba(0,0,0,0.72)';
            arcCtx.fillRect(30, 85, 260, 75);
            arcCtx.fillStyle = '#f43f5e';
            arcCtx.fillText('GAME OVER!', 95, 114);
            arcCtx.fillStyle = '#fde047';
            arcCtx.font = '9px "Press Start 2P", monospace';
            arcCtx.fillText('TEKAN SPASI / TEMBAK ULANG', 40, 140);
        }
    }

    /* ==========================================================
       11. INPUT
       ========================================================== */
    window.addEventListener('keydown', (e) => {
        keys[e.code] = true;

        if (arcadeOpen) {
            if (e.code === 'Escape') closeArcade();
            if (e.code === 'Space') {
                e.preventDefault();
                arcadeFire();
            }
            return;
        }

        if (dialogueOpen) {
            if (e.code === 'Escape' || e.code === 'KeyE' || e.code === 'Space' || e.code === 'Enter') {
                e.preventDefault();
                closeDialogue();
            }
            return;
        }

        if (e.code === 'KeyE' || e.code === 'Space' || e.code === 'Enter') {
            if (activeInteractable) {
                e.preventDefault();
                activeInteractable.run();
            }
        }
    });

    window.addEventListener('keyup', (e) => {
        keys[e.code] = false;
    });

    window.addEventListener('blur', () => {
        Object.keys(keys).forEach((k) => { keys[k] = false; });
    });

    document.querySelectorAll('.dpad-btn').forEach((btn) => {
        const map = { up: 'ArrowUp', down: 'ArrowDown', left: 'ArrowLeft', right: 'ArrowRight' };
        const code = map[btn.dataset.dir];
        if (!code) return;

        const on = (e) => { e.preventDefault(); keys[code] = true; };
        const off = (e) => { e.preventDefault(); keys[code] = false; };

        btn.addEventListener('touchstart', on, { passive: false });
        btn.addEventListener('touchend', off, { passive: false });
        btn.addEventListener('touchcancel', off, { passive: false });
        btn.addEventListener('mousedown', () => { keys[code] = true; });
        btn.addEventListener('mouseup', () => { keys[code] = false; });
        btn.addEventListener('mouseleave', () => { keys[code] = false; });
    });

    document.getElementById('touch-action-btn').addEventListener('click', () => {
        if (dialogueOpen) closeDialogue();
        else if (activeInteractable) activeInteractable.run();
    });

    const promptEl = document.getElementById('interact-prompt');
    promptEl.addEventListener('click', () => {
        if (activeInteractable && !dialogueOpen && !arcadeOpen) activeInteractable.run();
    });

    document.getElementById('btn-audio').addEventListener('click', (e) => {
        RetroAudio.enabled = !RetroAudio.enabled;
        e.currentTarget.textContent = RetroAudio.enabled ? '🔊 SFX: ON' : '🔇 SFX: OFF';
        if (RetroAudio.enabled) RetroAudio.blip();
    });

    document.addEventListener('visibilitychange', () => {
        const hidden = document.hidden;
        if (hidden) Object.keys(keys).forEach((k) => { keys[k] = false; });
        lastTime = performance.now();
    });

    /* ==========================================================
       12. TABRAKAN
       ========================================================== */
    function collides(nx, ny, nw, nh) {
        const left = Math.floor(nx / TILE);
        const right = Math.floor((nx + nw - 1) / TILE);
        const top = Math.floor(ny / TILE);
        const bottom = Math.floor((ny + nh - 1) / TILE);

        for (let r = top; r <= bottom; r++) {
            for (let c = left; c <= right; c++) {
                if (r < 0 || r >= MAP_ROWS || c < 0 || c >= MAP_COLS) return true;
                const t = mapGrid[r][c];
                if (t === 0 || t === 2 || t === 3) return true;
            }
        }

        if (bulkhead.progress < 0.75 &&
            overlap(nx, ny, nw, nh, bulkhead.x, bulkhead.y, bulkhead.w, bulkhead.h)) {
            return true;
        }

        for (const p of props) {
            if (p.solid && overlap(nx, ny, nw, nh, p.x, p.y, p.w, p.h)) return true;
        }

        for (const n of npcs) {
            if (overlap(nx, ny, nw, nh, n.x, n.y + 8, n.w, n.h - 8)) return true;
        }

        return false;
    }

    /* ==========================================================
       13. ZONA
       ========================================================== */
    function zoneName() {
        const cx = (player.x + player.w / 2) / TILE;
        const cy = (player.y + player.h / 2) / TILE;

        if (cx >= 31) return 'Dek Observasi Rahasia (Sektor-B)';
        if (cx >= 21 && cx <= 26 && cy <= 8) return 'Papan Hall of Fame';
        if (cx >= 24 && cy >= 9 && cy <= 17) return 'Sudut Arcade Retro';
        if (cx <= 15 && cy <= 10) return 'Konter Makanan & Bar Robot';
        if (cx <= 12 && cy >= 15) return 'Lounge Pemandangan Nebula';
        return 'Dek Utama Kafetaria';
    }

    function paintZone() {
        const el = document.getElementById('zone-name');
        if (!el) return;
        const name = zoneName();
        if (el.textContent !== name) el.textContent = name;
    }

    /* ==========================================================
       14. GAMBAR: LATAR & TILE
       ========================================================== */
    function drawSpace(time) {
        const grad = ctx.createLinearGradient(0, 0, canvas.width, canvas.height);
        grad.addColorStop(0, '#04050e');
        grad.addColorStop(0.5, '#0b0f29');
        grad.addColorStop(1, '#120924');
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        const nebX = canvas.width * 0.25 - camera.x * 0.12;
        const nebY = canvas.height * 0.2 - camera.y * 0.12;

        ctx.fillStyle = 'rgba(139, 92, 246, 0.14)';
        ctx.beginPath();
        ctx.arc(nebX, nebY, 190, 0, Math.PI * 2);
        ctx.fill();

        ctx.fillStyle = 'rgba(14, 165, 233, 0.12)';
        ctx.beginPath();
        ctx.arc(nebX + 260, nebY + 120, 230, 0, Math.PI * 2);
        ctx.fill();

        // Planet bercincin
        const px = 420 - camera.x * 0.18;
        const py = 120 - camera.y * 0.18;
        ctx.fillStyle = '#f59e0b';
        ctx.beginPath();
        ctx.arc(px, py, 34, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = '#d97706';
        ctx.fillRect(px - 30, py - 6, 60, 8);
        ctx.strokeStyle = '#fde68a';
        ctx.lineWidth = 4;
        ctx.beginPath();
        ctx.ellipse(px, py + 4, 56, 12, -0.25, 0, Math.PI * 2);
        ctx.stroke();

        stars.forEach((s) => {
            const sx = ((s.x - camera.x * s.speed) % canvas.width + canvas.width) % canvas.width;
            const sy = ((s.y - camera.y * s.speed) % canvas.height + canvas.height) % canvas.height;
            ctx.globalAlpha = 0.45 + 0.55 * Math.sin(time * 2.5 + s.twinkle);
            ctx.fillStyle = s.color;
            ctx.fillRect(Math.floor(sx), Math.floor(sy), s.size, s.size);
        });
        ctx.globalAlpha = 1;

        asteroids.forEach((a) => {
            a.x = (a.x + a.vx) % 1600;
            const ax = a.x - camera.x * 0.25;
            const ay = a.y - camera.y * 0.25;
            ctx.fillStyle = '#475569';
            ctx.fillRect(Math.floor(ax), Math.floor(ay), a.r, a.r * 0.8);
            ctx.fillStyle = '#334155';
            ctx.fillRect(Math.floor(ax + 3), Math.floor(ay + 3), a.r * 0.4, a.r * 0.4);
        });
    }

    function drawTiles() {
        for (let r = 0; r < MAP_ROWS; r++) {
            for (let c = 0; c < MAP_COLS; c++) {
                const t = mapGrid[r][c];
                if (t === 0) continue;

                const x = c * TILE;
                const y = r * TILE;

                if (t === 1) {
                    ctx.fillStyle = (r + c) % 2 === 0 ? '#1e2638' : '#1b2233';
                    ctx.fillRect(x, y, TILE, TILE);
                    ctx.strokeStyle = '#28334a';
                    ctx.lineWidth = 1;
                    ctx.strokeRect(x + 0.5, y + 0.5, TILE - 1, TILE - 1);
                    if ((r * 7 + c * 13) % 5 === 0) {
                        ctx.fillStyle = '#334155';
                        ctx.fillRect(x + 4, y + 4, 2, 2);
                        ctx.fillRect(x + TILE - 6, y + TILE - 6, 2, 2);
                    }
                } else if (t === 2) {
                    ctx.fillStyle = '#334155';
                    ctx.fillRect(x, y, TILE, TILE);
                    ctx.fillStyle = '#475569';
                    ctx.fillRect(x + 2, y + 2, TILE - 4, 10);
                    ctx.fillStyle = '#1e293b';
                    ctx.fillRect(x, y + TILE - 6, TILE, 6);
                    if (r < MAP_ROWS - 1 && mapGrid[r + 1][c] !== 0 && mapGrid[r + 1][c] !== 2) {
                        ctx.fillStyle = '#0ea5e9';
                        ctx.fillRect(x, y + TILE - 3, TILE, 2);
                    }
                } else if (t === 3) {
                    ctx.fillStyle = 'rgba(56, 189, 248, 0.14)';
                    ctx.fillRect(x, y, TILE, TILE);
                    ctx.fillStyle = '#475569';
                    ctx.fillRect(x, y, TILE, 4);
                    ctx.fillRect(x, y + TILE - 4, TILE, 4);
                    ctx.fillRect(x, y, 3, TILE);
                    ctx.fillStyle = 'rgba(255, 255, 255, 0.22)';
                    ctx.fillRect(x + 6, y + 6, 3, TILE - 12);
                } else if (t === 4) {
                    const sub = TILE / 2;
                    for (let sr = 0; sr < 2; sr++) {
                        for (let sc = 0; sc < 2; sc++) {
                            ctx.fillStyle = (sr + sc) % 2 === 0 ? '#27233a' : '#181526';
                            ctx.fillRect(x + sc * sub, y + sr * sub, sub, sub);
                        }
                    }
                } else if (t === 5) {
                    ctx.fillStyle = '#251b42';
                    ctx.fillRect(x, y, TILE, TILE);
                    ctx.fillStyle = '#2e2152';
                    ctx.fillRect(x + 2, y + 2, TILE - 4, TILE - 4);
                } else if (t === 6) {
                    ctx.fillStyle = '#1a242b';
                    ctx.fillRect(x, y, TILE, TILE);
                    ctx.strokeStyle = '#2b3b47';
                    ctx.strokeRect(x + 0.5, y + 0.5, TILE - 1, TILE - 1);
                    ctx.fillStyle = '#0f172a';
                    ctx.fillRect(x + 12, y + 12, 8, 8);
                } else if (t === 7) {
                    ctx.fillStyle = '#1e293b';
                    ctx.fillRect(x, y, TILE, TILE);
                    ctx.fillStyle = '#eab308';
                    for (let i = 0; i < TILE; i += 8) {
                        ctx.fillRect(x + i, y + 2, 4, TILE - 4);
                    }
                }
            }
        }
    }

    function drawBulkhead(time) {
        const d = bulkhead;
        const slide = Math.floor((d.h / 2) * d.progress);

        ctx.fillStyle = '#0f172a';
        ctx.fillRect(d.x - 2, d.y - 2, d.w + 4, d.h + 4);

        const topH = d.h / 2 - slide;
        if (topH > 0) {
            ctx.fillStyle = '#64748b';
            ctx.fillRect(d.x + 2, d.y, d.w - 4, topH);
            ctx.fillStyle = '#334155';
            ctx.fillRect(d.x + 6, d.y + 4, d.w - 12, Math.max(0, topH - 6));
        }

        const botH = d.h / 2 - slide;
        if (botH > 0) {
            ctx.fillStyle = '#64748b';
            ctx.fillRect(d.x + 2, d.y + d.h - botH, d.w - 4, botH);
            ctx.fillStyle = '#334155';
            ctx.fillRect(d.x + 6, d.y + d.h - botH + 2, d.w - 12, Math.max(0, botH - 6));
        }

        const status = d.isOpen ? '#34d399' : (Math.sin(time * 5) > 0 ? '#fbbf24' : '#f43f5e');
        ctx.fillStyle = status;
        ctx.fillRect(d.x - 4, d.y + 6, 3, 12);
        ctx.fillRect(d.x + d.w + 1, d.y + 6, 3, 12);
    }

    /* ==========================================================
       15. GAMBAR: PROPS
       ========================================================== */
    function drawProp(p, time) {
        const x = p.x;
        const y = p.y;
        const w = p.w;
        const h = p.h;

        switch (p.type) {
            case 'counter_h':
                ctx.fillStyle = '#334155'; ctx.fillRect(x, y, w, h);
                ctx.fillStyle = '#cbd5e1'; ctx.fillRect(x, y, w, 10);
                ctx.fillStyle = '#f43f5e'; ctx.fillRect(x, y + 14, w, 4);
                break;

            case 'stool':
                ctx.fillStyle = '#0f172a'; ctx.fillRect(x + 4, y + 6, w - 8, h - 6);
                ctx.fillStyle = '#f43f5e'; ctx.fillRect(x + 2, y + 2, w - 4, 10);
                ctx.fillStyle = '#fda4af'; ctx.fillRect(x + 4, y + 3, w - 10, 3);
                break;

            case 'coffee_machine':
                ctx.fillStyle = '#475569'; ctx.fillRect(x + 4, y, w - 8, h);
                ctx.fillStyle = '#38bdf8'; ctx.fillRect(x + 8, y + 4, w - 16, 6);
                break;

            case 'slushie_machine':
                ctx.fillStyle = '#64748b'; ctx.fillRect(x + 3, y, w - 6, h);
                ctx.fillStyle = '#a855f7'; ctx.fillRect(x + 5, y + 2, 9, 12);
                ctx.fillStyle = '#34d399'; ctx.fillRect(x + 16, y + 2, 9, 12);
                break;

            case 'food_dispenser':
            case 'fridge_glow':
                ctx.fillStyle = '#1e293b'; ctx.fillRect(x, y, w, h);
                ctx.strokeStyle = '#64748b'; ctx.strokeRect(x + 1, y + 1, w - 2, h - 2);
                ctx.fillStyle = p.type === 'fridge_glow' ? '#38bdf8' : '#fbbf24';
                ctx.fillRect(x + 6, y + 6, w - 12, h - 16);
                break;

            case 'neon_menu':
                ctx.fillStyle = '#090d16'; ctx.fillRect(x, y, w, h);
                ctx.strokeStyle = '#f43f5e'; ctx.strokeRect(x, y, w, h);
                ctx.fillStyle = '#fde047'; ctx.fillRect(x + 6, y + 5, w - 12, 3);
                ctx.fillStyle = '#38bdf8'; ctx.fillRect(x + 6, y + 11, w - 24, 3);
                break;

            case 'dining_table':
                ctx.fillStyle = '#475569';
                ctx.fillRect(x - 8, y + 10, 8, 22);
                ctx.fillRect(x + w, y + 10, 8, 22);
                ctx.fillStyle = '#1e293b'; ctx.fillRect(x + 4, y + 10, w - 8, h - 6);
                ctx.fillStyle = '#cbd5e1'; ctx.fillRect(x, y, w, h - 10);
                ctx.fillStyle = '#38bdf8'; ctx.fillRect(x + 3, y + h - 12, w - 6, 2);
                ctx.fillStyle = '#ffffff'; ctx.fillRect(x + 14, y + 10, 12, 8);
                ctx.fillStyle = p.food === 'burger' ? '#f59e0b' : (p.food === 'ramen' ? '#f43f5e' : '#34d399');
                ctx.fillRect(x + 16, y + 11, 8, 5);
                break;

            case 'lounge_sofa_v':
            case 'lounge_sofa_h':
                ctx.fillStyle = '#4c1d95'; ctx.fillRect(x, y, w, h);
                ctx.fillStyle = '#7c3aed'; ctx.fillRect(x + 4, y + 4, w - 8, h - 8);
                break;

            case 'holo_table':
                ctx.fillStyle = '#1e293b'; ctx.fillRect(x, y, w, h);
                ctx.strokeStyle = '#38bdf8'; ctx.strokeRect(x + 2, y + 2, w - 4, h - 4);
                ctx.fillStyle = 'rgba(56, 189, 248, 0.5)';
                ctx.beginPath();
                ctx.arc(x + w / 2, y + h / 2, 6 + Math.sin(time * 4) * 2, 0, Math.PI * 2);
                ctx.fill();
                break;

            case 'arcade_cab': {
                ctx.fillStyle = '#1e1b4b'; ctx.fillRect(x, y, w, h);
                ctx.fillStyle = p.color;
                ctx.fillRect(x, y, 4, h);
                ctx.fillRect(x + w - 4, y, 4, h);
                ctx.fillRect(x + 4, y + 2, w - 8, 8);

                ctx.fillStyle = '#020617'; ctx.fillRect(x + 5, y + 12, w - 10, 18);
                ctx.fillStyle = p.screenColor;
                const blink = Math.floor((time * 12) % (w - 16));
                ctx.fillRect(x + 7 + blink, y + 25, 4, 3);
                ctx.fillStyle = '#f43f5e';
                ctx.fillRect(x + 9, y + 15, 4, 3);
                ctx.fillRect(x + 19, y + 17, 4, 3);

                ctx.fillStyle = '#334155'; ctx.fillRect(x + 3, y + 31, w - 6, 10);
                ctx.fillStyle = '#fde047'; ctx.fillRect(x + 8, y + 33, 3, 4);
                ctx.fillStyle = '#f43f5e'; ctx.fillRect(x + 20, y + 34, 3, 3);
                break;
            }

            case 'claw_machine':
                ctx.fillStyle = '#be185d'; ctx.fillRect(x, y, w, h);
                ctx.fillStyle = '#bae6fd'; ctx.fillRect(x + 4, y + 6, w - 8, 22);
                ctx.fillStyle = '#4ade80'; ctx.fillRect(x + 7, y + 20, 7, 7);
                ctx.fillStyle = '#fde047'; ctx.fillRect(x + 18, y + 20, 7, 7);
                break;

            case 'jukebox':
                ctx.fillStyle = '#78350f'; ctx.fillRect(x, y + 6, w, h - 6);
                ctx.fillStyle = Math.sin(time * 6) > 0 ? '#f43f5e' : '#38bdf8';
                ctx.beginPath();
                ctx.arc(x + w / 2, y + 10, w / 2 - 2, Math.PI, 0);
                ctx.fill();
                break;

            case 'door_keypad':
                ctx.fillStyle = '#1e293b'; ctx.fillRect(x, y, w, h);
                ctx.fillStyle = bulkhead.isOpen ? '#34d399' : '#38bdf8';
                ctx.fillRect(x + 4, y + 4, w - 8, 7);
                ctx.fillStyle = '#94a3b8'; ctx.fillRect(x + 4, y + 14, w - 8, 6);
                break;

            case 'star_telescope':
                ctx.fillStyle = '#475569'; ctx.fillRect(x + 14, y + 20, 12, 18);
                ctx.fillStyle = '#e2e8f0'; ctx.fillRect(x + 6, y + 8, 28, 10);
                ctx.fillStyle = '#38bdf8'; ctx.fillRect(x + 30, y + 6, 6, 14);
                break;

            case 'cargo_crate':
                ctx.fillStyle = '#334155'; ctx.fillRect(x, y, w, h);
                ctx.strokeStyle = '#f59e0b'; ctx.strokeRect(x + 2, y + 2, w - 4, h - 4);
                break;

            case 'captain_chair':
                ctx.fillStyle = '#1e293b'; ctx.fillRect(x, y, w, h);
                ctx.fillStyle = '#0284c7'; ctx.fillRect(x + 5, y + 5, w - 10, h - 10);
                break;

            case 'alien_plant':
                ctx.fillStyle = '#475569'; ctx.fillRect(x + 5, y + 16, 16, 12);
                ctx.fillStyle = '#10b981'; ctx.fillRect(x + 3, y + 6, 20, 10);
                ctx.fillStyle = '#a855f7'; ctx.fillRect(x + 9, y + 2, 8, 8);
                break;

            case 'leaderboard_board': {
                // Papan Hall of Fame fisik di dinding
                ctx.fillStyle = '#0a0e19';
                ctx.fillRect(x - 4, y - 4, w + 8, h + 8);

                // Bingkai emas
                ctx.fillStyle = '#f0a500';
                ctx.fillRect(x, y, w, h);
                ctx.fillStyle = '#1b1f2b';
                ctx.fillRect(x + 4, y + 4, w - 8, h - 8);

                // Judul papan
                ctx.fillStyle = '#ffcb45';
                ctx.fillRect(x + 8, y + 8, w - 16, 8);
                ctx.fillStyle = '#0a0e19';
                ctx.fillRect(x + 12, y + 10, w - 24, 4);

                // Baris peringkat Top 5
                const maxRows = 5;
                for (let i = 0; i < maxRows; i++) {
                    const rowY = y + 22 + i * 12;
                    if (rowY + 10 > y + h - 10) break;

                    const entry = TOP[i];
                    const isTop = i === 0;

                    // Nomor peringkat
                    ctx.fillStyle = isTop ? '#ffcb45' : (entry ? '#849495' : '#3b494b');
                    ctx.fillRect(x + 8, rowY, 6, 8);

                    // Nama pemain (blok memanjang, lebarnya mengikuti panjang nama)
                    if (entry) {
                        const len = Math.min(w - 40, 8 + String(entry.name).length * 4);
                        ctx.fillStyle = isTop ? '#dbfcff' : '#b9cacb';
                        ctx.fillRect(x + 18, rowY + 1, len, 6);

                        // Skor
                        ctx.fillStyle = '#00fa64';
                        ctx.fillRect(x + w - 20, rowY + 2, 12, 4);
                    } else {
                        ctx.fillStyle = '#2a2f3d';
                        ctx.fillRect(x + 18, rowY + 1, w - 40, 6);
                    }
                }

                // Cahaya berkedip di bingkai
                const glow = 0.5 + 0.5 * Math.sin(time * 2.2);
                ctx.strokeStyle = 'rgba(255, 203, 69, ' + (0.25 + glow * 0.35) + ')';
                ctx.lineWidth = 2;
                ctx.strokeRect(x + 1, y + 1, w - 2, h - 2);

                // Kaki penyangga
                ctx.fillStyle = '#334155';
                ctx.fillRect(x + 10, y + h, 6, 8);
                ctx.fillRect(x + w - 16, y + h, 6, 8);
                break;
            }

            case 'exit_door': {
                // Pintu keluar
                ctx.fillStyle = '#0f172a'; ctx.fillRect(x - 3, y - 6, w + 6, h + 6);
                ctx.fillStyle = '#334155'; ctx.fillRect(x, y, w, h);

                const pulse = 0.5 + 0.5 * Math.sin(time * 2.4);
                ctx.fillStyle = 'rgba(0, 240, 255, ' + (0.12 + pulse * 0.2) + ')';
                ctx.fillRect(x + 5, y + 6, w - 10, h - 18);

                ctx.fillStyle = '#00f0ff';
                ctx.fillRect(x + w - 14, y + h / 2, 5, 12);

                // Label
                ctx.fillStyle = '#00f0ff';
                ctx.fillRect(x + 4, y - 12, w - 8, 6);
                break;
            }
        }
    }

    /* ==========================================================
       16. GAMBAR: KARAKTER
       ========================================================== */
    function drawCharacter(ent, isPlayer, time) {
        const x = Math.floor(ent.x);
        const y = Math.floor(ent.y);
        const bob = isPlayer
            ? (ent.animFrame % 2 === 1 ? -2 : 0)
            : Math.round(Math.sin(time * 3 + (ent.bob || 0)) * 1.5);

        // Bayangan
        ctx.fillStyle = 'rgba(0, 0, 0, 0.38)';
        ctx.fillRect(x + 3, y + ent.h - 3, ent.w - 6, 5);

        // Kaki
        const step = isPlayer && ent.animFrame % 2 === 1 ? 2 : 0;
        ctx.fillStyle = '#1e293b';
        ctx.fillRect(x + 5, y + ent.h - 7 - step, 4, 7);
        ctx.fillRect(x + ent.w - 9, y + ent.h - 7 + (step ? -2 : 0), 4, 7);

        // Badan
        const suit = isPlayer ? '#f8fafc' : ent.color;
        ctx.fillStyle = suit;
        ctx.fillRect(x + 3, y + 9 + bob, ent.w - 6, 12);

        if (isPlayer) {
            ctx.fillStyle = '#94a3b8';
            ctx.fillRect(x + 1, y + 10 + bob, 3, 9);
        }

        // Helm
        ctx.fillStyle = suit;
        ctx.fillRect(x + 4, y + bob, ent.w - 8, 10);

        // Antena
        if (!isPlayer && ent.type === 'alien') {
            ctx.fillStyle = '#4ade80';
            ctx.fillRect(x + 6, y - 4 + bob, 2, 4);
            ctx.fillRect(x + ent.w - 8, y - 4 + bob, 2, 4);
        } else if (!isPlayer && ent.type === 'robot') {
            ctx.fillStyle = '#fbbf24';
            ctx.fillRect(x + 10, y - 4 + bob, 2, 4);
        }

        // Visor
        ctx.fillStyle = isPlayer ? '#38bdf8' : ent.visor;
        if (!isPlayer || ent.dir === 'down') {
            ctx.fillRect(x + 6, y + 3 + bob, ent.w - 12, 4);
        } else if (ent.dir === 'left') {
            ctx.fillRect(x + 4, y + 3 + bob, 7, 4);
        } else if (ent.dir === 'right') {
            ctx.fillRect(x + ent.w - 11, y + 3 + bob, 7, 4);
        }
    }

    function drawShards(time) {
        shards.forEach((s) => {
            if (s.taken) return;
            const float = Math.sin(time * 3 + s.x) * 3;
            ctx.fillStyle = 'rgba(253, 224, 71, 0.22)';
            ctx.beginPath();
            ctx.arc(s.x, s.y + float, 10, 0, Math.PI * 2);
            ctx.fill();
            ctx.fillStyle = '#fde047';
            ctx.fillRect(s.x - 3, s.y - 4 + float, 6, 8);
            ctx.fillStyle = '#fef9c3';
            ctx.fillRect(s.x - 1, s.y - 2 + float, 2, 3);
        });
    }

    /* ==========================================================
       17. MINIMAP
       ========================================================== */
    function drawMinimap() {
        if (!miniCtx) return;

        miniCtx.fillStyle = '#050714';
        miniCtx.fillRect(0, 0, miniCanvas.width, miniCanvas.height);

        const sx = miniCanvas.width / MAP_COLS;
        const sy = miniCanvas.height / MAP_ROWS;

        for (let r = 0; r < MAP_ROWS; r++) {
            for (let c = 0; c < MAP_COLS; c++) {
                const t = mapGrid[r][c];
                if (t === 0) continue;
                if (t === 2) miniCtx.fillStyle = '#475569';
                else if (t === 3) miniCtx.fillStyle = '#38bdf8';
                else if (t === 4) miniCtx.fillStyle = '#4c1d95';
                else if (t === 6) miniCtx.fillStyle = '#0f766e';
                else miniCtx.fillStyle = '#1e293b';
                miniCtx.fillRect(c * sx, r * sy, Math.ceil(sx), Math.ceil(sy));
            }
        }

        // Pintu
        miniCtx.fillStyle = bulkhead.isOpen ? '#34d399' : '#f43f5e';
        miniCtx.fillRect((bulkhead.x / TILE) * sx, (bulkhead.y / TILE) * sy, sx + 1, sy * 2);

        // Papan leaderboard
        const board = props.find((p) => p.type === 'leaderboard_board');
        if (board) {
            miniCtx.fillStyle = '#ffcb45';
            miniCtx.fillRect((board.x / TILE) * sx, (board.y / TILE) * sy, 4, 3);
        }

        // Kristal
        miniCtx.fillStyle = '#fde047';
        shards.forEach((s) => {
            if (s.taken) return;
            miniCtx.fillRect((s.x / TILE) * sx - 1, (s.y / TILE) * sy - 1, 2, 2);
        });

        // NPC
        miniCtx.fillStyle = '#fbbf24';
        npcs.forEach((n) => {
            miniCtx.fillRect((n.x / TILE) * sx, (n.y / TILE) * sy, 3, 3);
        });

        // Pemain
        miniCtx.fillStyle = '#ffffff';
        miniCtx.fillRect((player.x / TILE) * sx - 1, (player.y / TILE) * sy - 1, 4, 4);
    }

    /* ==========================================================
       18. UPDATE
       ========================================================== */
    function update(dt) {
        // Animasi pintu
        const target = bulkhead.isOpen ? 1 : 0;
        bulkhead.progress += (target - bulkhead.progress) * Math.min(1, dt * 7);

        if (arcadeOpen) {
            drawArcade(dt);
            return;
        }

        if (!dialogueOpen) {
            let vx = 0;
            let vy = 0;

            if (keys.KeyW || keys.ArrowUp) { vy -= 1; player.dir = 'up'; }
            if (keys.KeyS || keys.ArrowDown) { vy += 1; player.dir = 'down'; }
            if (keys.KeyA || keys.ArrowLeft) { vx -= 1; player.dir = 'left'; }
            if (keys.KeyD || keys.ArrowRight) { vx += 1; player.dir = 'right'; }

            if (vx !== 0 || vy !== 0) {
                const len = Math.hypot(vx, vy);
                vx = (vx / len) * player.speed * dt;
                vy = (vy / len) * player.speed * dt;

                if (!collides(player.x + vx, player.y, player.w, player.h)) player.x += vx;
                if (!collides(player.x, player.y + vy, player.w, player.h)) player.y += vy;

                player.moving = true;
                player.animTimer += dt;
                if (player.animTimer > 0.16) {
                    player.animTimer = 0;
                    player.animFrame++;
                    RetroAudio.step();
                }
            } else {
                player.moving = false;
                player.animFrame = 0;
            }
        }

        // Kamera mengikuti
        const camX = player.x + player.w / 2 - canvas.width / (2 * camera.zoom);
        const camY = player.y + player.h / 2 - canvas.height / (2 * camera.zoom);
        camera.x += (camX - camera.x) * Math.min(1, dt * 8);
        camera.y += (camY - camera.y) * Math.min(1, dt * 8);

        // Interaksi terdekat
        const list = getInteractables();
        activeInteractable = null;
        for (const item of list) {
            if (overlap(player.x - 6, player.y - 6, player.w + 12, player.h + 12,
                item.x, item.y, item.w, item.h)) {
                activeInteractable = item;
                break;
            }
        }

        if (activeInteractable && !dialogueOpen) {
            promptEl.style.display = 'flex';
            document.getElementById('interact-prompt-text').textContent = activeInteractable.label;
            announce(activeInteractable.label + ' — tekan E');
        } else {
            promptEl.style.display = 'none';
            if (!dialogueOpen) announce('');
        }

        paintZone();
    }

    /* ==========================================================
       19. RENDER
       ========================================================== */
    function render(time) {
        drawSpace(time);

        ctx.save();
        ctx.scale(camera.zoom, camera.zoom);
        ctx.translate(-Math.floor(camera.x), -Math.floor(camera.y));

        drawTiles();
        drawBulkhead(time);
        drawShards(time);

        const list = [];
        props.forEach((p) => list.push({ kind: 'prop', sort: p.y + p.h, data: p }));
        npcs.forEach((n) => list.push({ kind: 'npc', sort: n.y + n.h, data: n }));
        list.push({ kind: 'player', sort: player.y + player.h, data: player });
        list.sort((a, b) => a.sort - b.sort);

        list.forEach((item) => {
            if (item.kind === 'prop') drawProp(item.data, time);
            else drawCharacter(item.data, item.kind === 'player', time);
        });

        ctx.restore();
        drawMinimap();
    }

    /* ==========================================================
       20. LOOP
       ========================================================== */
    function loop(now) {
        const dt = Math.min(0.05, (now - lastTime) / 1000);
        lastTime = now;

        if (!paused) {
            update(dt);
            render(now / 1000);
        }

        requestAnimationFrame(loop);
    }

    document.addEventListener('visibilitychange', () => {
        paused = document.hidden;
        if (!paused) lastTime = performance.now();
    });

    paintCredits();
    requestAnimationFrame(loop);
})();
