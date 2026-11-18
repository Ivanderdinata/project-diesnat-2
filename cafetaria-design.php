<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Astro-Diner 77 — Kafetaria Ruang Angkasa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=VT323&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #070812;
            --panel-bg: rgba(13, 17, 38, 0.92);
            --border-neon: #38bdf8;
            --accent-pink: #f43f5e;
            --accent-amber: #fbbf24;
            --accent-green: #34d399;
            --text-main: #f1f5f9;
        }

        * {
            box-sizing: border-box;
            user-select: none;
            -webkit-user-select: none;
        }

        body,
        html {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            background-color: var(--bg-dark);
            color: var(--text-main);
            font-family: 'VT323', monospace;
            overflow: hidden;
        }

        #game-container {
            position: relative;
            width: 100vw;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        canvas#worldCanvas {
            display: block;
            width: 100%;
            height: 100%;
            image-rendering: pixelated;
            image-rendering: crisp-edges;
        }

        /* Scanline CRT overlay subtle effect */
        .crt-overlay {
            position: fixed;
            inset: 0;
            pointer-events: none;
            background: linear-gradient(rgba(18, 16, 16, 0) 50%,
                    rgba(0, 0, 0, 0.14) 50%);
            background-size: 100% 4px;
            z-index: 5;
        }

        /* HUD Top Bar */
        #hud-top {
            position: fixed;
            top: 14px;
            left: 14px;
            right: 14px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            pointer-events: none;
            z-index: 10;
            gap: 10px;
        }

        .pixel-box {
            background: var(--panel-bg);
            border: 3px solid var(--border-neon);
            box-shadow: 0 0 0 3px #050714, 0 6px 20px rgba(0, 0, 0, 0.7);
            border-radius: 6px;
            padding: 10px 14px;
            pointer-events: auto;
        }

        .title-badge {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .game-title {
            font-family: 'Press Start 2P', monospace;
            font-size: 12px;
            color: var(--accent-amber);
            text-shadow: 2px 2px 0 #78350f;
            letter-spacing: 0.5px;
        }

        .zone-label {
            font-size: 20px;
            color: #7dd3fc;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .zone-dot {
            width: 10px;
            height: 10px;
            background: var(--accent-green);
            display: inline-block;
            box-shadow: 0 0 8px var(--accent-green);
        }

        .hud-controls-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pixel-btn {
            font-family: 'Press Start 2P', monospace;
            font-size: 10px;
            background: #1e293b;
            color: #f8fafc;
            border: 3px solid #64748b;
            border-radius: 6px;
            padding: 10px 12px;
            cursor: pointer;
            box-shadow: 0 4px 0 #0f172a;
            transition: transform 0.08s, background 0.15s, border-color 0.15s;
        }

        .pixel-btn:hover {
            background: #334155;
            border-color: var(--border-neon);
        }

        .pixel-btn:active {
            transform: translateY(3px);
            box-shadow: 0 1px 0 #0f172a;
        }

        .pixel-btn.active {
            border-color: var(--accent-green);
            color: var(--accent-green);
        }

        /* Minimap */
        #minimap-wrapper {
            position: fixed;
            bottom: 16px;
            right: 16px;
            z-index: 10;
            padding: 6px;
            background: var(--panel-bg);
            border: 3px solid #475569;
            border-radius: 6px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.75);
        }

        #minimapCanvas {
            display: block;
            image-rendering: pixelated;
            border: 1px solid #1e293b;
        }

        .minimap-caption {
            font-size: 14px;
            text-align: center;
            color: #94a3b8;
            margin-top: 3px;
            letter-spacing: 1px;
        }

        /* Controls Hint Bottom Left */
        #controls-hint {
            position: fixed;
            bottom: 16px;
            left: 16px;
            z-index: 10;
            font-size: 18px;
            color: #cbd5e1;
            line-height: 1.3;
        }

        .key-cap {
            display: inline-block;
            background: #1e293b;
            border: 2px solid #64748b;
            border-bottom-width: 4px;
            border-radius: 4px;
            padding: 0 6px;
            font-family: 'Press Start 2P', monospace;
            font-size: 9px;
            color: var(--accent-amber);
            margin: 0 2px;
        }

        /* Interaction Prompt Banner */
        #interact-prompt {
            position: fixed;
            bottom: 92px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 15;
            display: none;
            align-items: center;
            gap: 10px;
            background: rgba(15, 23, 42, 0.95);
            border: 3px solid var(--accent-amber);
            border-radius: 8px;
            padding: 10px 18px;
            box-shadow: 0 0 20px rgba(251, 191, 36, 0.35);
            cursor: pointer;
            animation: floatPrompt 1.2s ease-in-out infinite;
        }

        @keyframes floatPrompt {

            0%,
            100% {
                transform: translate(-50%, 0);
            }

            50% {
                transform: translate(-50%, -5px);
            }
        }

        #interact-prompt-text {
            font-size: 22px;
            color: #fef3c7;
            white-space: nowrap;
        }

        /* Dialogue Box */
        #dialogue-box {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            width: min(680px, 92vw);
            background: rgba(10, 14, 32, 0.97);
            border: 4px solid var(--border-neon);
            box-shadow: 0 0 0 4px #050714, 0 12px 35px rgba(0, 0, 0, 0.85);
            border-radius: 8px;
            padding: 16px 20px;
            z-index: 30;
            display: none;
            flex-direction: column;
            gap: 10px;
        }

        .dialogue-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #1e293b;
            padding-bottom: 6px;
        }

        #dialogue-speaker {
            font-family: 'Press Start 2P', monospace;
            font-size: 12px;
            color: var(--accent-amber);
        }

        #dialogue-body {
            font-size: 24px;
            line-height: 1.35;
            color: #f8fafc;
            min-height: 64px;
        }

        .dialogue-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 4px;
        }

        #dialogue-choices {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .choice-btn {
            font-family: 'VT323', monospace;
            font-size: 21px;
            background: #1e293b;
            color: #7dd3fc;
            border: 2px solid #38bdf8;
            border-radius: 5px;
            padding: 4px 12px;
            cursor: pointer;
        }

        .choice-btn:hover {
            background: #0284c7;
            color: #ffffff;
        }

        /* Arcade Modal Overlay */
        #arcade-modal {
            position: fixed;
            inset: 0;
            background: rgba(3, 5, 14, 0.86);
            z-index: 40;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .arcade-cabinet-frame {
            background: #18182b;
            border: 6px solid #f43f5e;
            box-shadow: 0 0 35px rgba(244, 63, 94, 0.45), inset 0 0 20px rgba(0, 0, 0, 0.8);
            border-radius: 14px;
            padding: 16px;
            width: min(460px, 95vw);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .arcade-marquee {
            font-family: 'Press Start 2P', monospace;
            font-size: 13px;
            color: #fde047;
            text-align: center;
            text-shadow: 0 0 8px #f59e0b;
            background: #2e1065;
            border: 3px solid #a855f7;
            border-radius: 6px;
            padding: 10px;
            width: 100%;
        }

        #arcadeCanvas {
            background: #050510;
            border: 4px solid #334155;
            border-radius: 6px;
            width: 100%;
            max-width: 380px;
            height: auto;
            aspect-ratio: 4 / 3;
            image-rendering: pixelated;
        }

        .arcade-controls-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            gap: 8px;
        }

        /* Mobile Touch Controls */
        #touch-controls {
            position: fixed;
            bottom: 18px;
            left: 18px;
            z-index: 20;
            display: none;
            gap: 16px;
            align-items: flex-end;
        }

        .dpad {
            display: grid;
            grid-template-columns: repeat(3, 46px);
            grid-template-rows: repeat(3, 46px);
            gap: 4px;
        }

        .dpad-btn {
            background: rgba(30, 41, 59, 0.85);
            border: 2px solid #64748b;
            border-radius: 8px;
            color: #f8fafc;
            font-family: 'Press Start 2P', monospace;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            touch-action: manipulation;
        }

        .dpad-btn:active {
            background: #38bdf8;
            color: #0f172a;
        }

        @media (max-width: 768px) {
            #controls-hint {
                display: none;
            }

            #touch-controls {
                display: flex;
            }

            #minimap-wrapper {
                top: 76px;
                bottom: auto;
                right: 14px;
            }

            .game-title {
                font-size: 10px;
            }
        }
    </style>
</head>

<body>

    <div id="game-container">
        <canvas id="worldCanvas"></canvas>
        <div class="crt-overlay"></div>

        <!-- Top HUD -->
        <div id="hud-top">
            <div class="pixel-box title-badge">
                <div class="game-title">ASTRO-DINER 77</div>
                <div class="zone-label">
                    <span class="zone-dot"></span>
                    <span id="zone-name">Dek Utama Kafetaria</span>
                </div>
            </div>

            <div class="hud-controls-right">
                <div class="pixel-box" style="padding: 8px 12px; font-size: 20px; color: #fde047;">
                    KREDIT: <span id="credit-count">50</span> ◈
                </div>
                <button id="btn-audio" class="pixel-btn" title="Suara Retro">🔊 SFX: ON</button>
            </div>
        </div>

        <!-- Minimap -->
        <div id="minimap-wrapper">
            <canvas id="minimapCanvas" width="168" height="120"></canvas>
            <div class="minimap-caption">RADAR STASIUN</div>
        </div>

        <!-- Desktop Controls Hint -->
        <div id="controls-hint" class="pixel-box">
            <div>Gerak: <span class="key-cap">W</span><span class="key-cap">A</span><span class="key-cap">S</span><span class="key-cap">D</span> / <span class="key-cap">PANAH</span></div>
            <div style="margin-top: 4px;">Interaksi / Buka Pintu / Main Arcade: <span class="key-cap">E</span> / <span class="key-cap">SPASI</span></div>
        </div>

        <!-- Touch D-Pad for Mobile -->
        <div id="touch-controls">
            <div class="dpad">
                <div></div>
                <button class="dpad-btn" data-dir="up">▲</button>
                <div></div>
                <button class="dpad-btn" data-dir="left">◄</button>
                <div></div>
                <button class="dpad-btn" data-dir="right">►</button>
                <div></div>
                <button class="dpad-btn" data-dir="down">▼</button>
                <div></div>
            </div>
            <button id="touch-action-btn" class="pixel-btn" style="padding: 16px 18px; border-color: #fbbf24; color: #fbbf24;">AKSI [E]</button>
        </div>

        <!-- Floating Interaction Prompt -->
        <div id="interact-prompt">
            <span class="key-cap">E</span>
            <span id="interact-prompt-text">Interaksi</span>
        </div>

        <!-- Retro Dialogue Box -->
        <div id="dialogue-box">
            <div class="dialogue-header">
                <span id="dialogue-speaker">UNIT-01</span>
                <span style="font-size: 18px; color: #94a3b8;">[E / Klik untuk lanjut]</span>
            </div>
            <div id="dialogue-body">Halo penjelajah ruang angkasa!</div>
            <div class="dialogue-footer">
                <div id="dialogue-choices"></div>
                <button id="dialogue-close-btn" class="pixel-btn">TUTUP [ESC]</button>
            </div>
        </div>

        <!-- Playable Arcade Mini-Game Modal -->
        <div id="arcade-modal">
            <div class="arcade-cabinet-frame">
                <div class="arcade-marquee" id="arcade-title">★ STAR BLASTER 1984 ★</div>
                <canvas id="arcadeCanvas" width="320" height="240"></canvas>
                <div style="font-size: 20px; color: #cbd5e1; text-align: center;">
                    Gunakan <span class="key-cap">◄</span> <span class="key-cap">►</span> untuk geser & <span class="key-cap">SPASI</span> untuk menembak!
                </div>
                <div class="arcade-controls-bar">
                    <div style="display:flex; gap:6px;">
                        <button class="pixel-btn" id="arc-left">◄</button>
                        <button class="pixel-btn" id="arc-fire" style="border-color:#f43f5e; color:#fda4af;">TEMBAK</button>
                        <button class="pixel-btn" id="arc-right">►</button>
                    </div>
                    <button class="pixel-btn" id="arcade-exit-btn" style="border-color:#fbbf24; color:#fbbf24;">KELUAR</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        /**
         * ASTRO-DINER 77 - Space Cafeteria Pixel Exploration Game
         * Single-file self-contained pixel engine with procedural pixel sprites,
         * spacious multi-zone cafeteria, interactive arcade cabinets, and a working
         * bulkhead sliding door right beside the arcade corner leading to a secret airlock!
         */

        // ==========================================
        // 1. WEB AUDIO RETRO SYNTHESIZER (SFX & AMBIENT)
        // ==========================================
        class RetroAudio {
            constructor() {
                this.ctx = null;
                this.enabled = true;
                this.stepTimer = 0;
            }

            init() {
                if (!this.ctx) {
                    const AudioCtx = window.AudioContext || window.webkitAudioContext;
                    if (AudioCtx) this.ctx = new AudioCtx();
                }
                if (this.ctx && this.ctx.state === 'suspended') {
                    this.ctx.resume();
                }
            }

            playTone(freq, type = 'square', duration = 0.08, vol = 0.06, slideTo = null) {
                if (!this.enabled) return;
                this.init();
                if (!this.ctx) return;

                const now = this.ctx.currentTime;
                const osc = this.ctx.createOscillator();
                const gain = this.ctx.createGain();

                osc.type = type;
                osc.frequency.setValueAtTime(freq, now);
                if (slideTo) {
                    osc.frequency.exponentialRampToValueAtTime(slideTo, now + duration);
                }

                gain.gain.setValueAtTime(vol, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + duration);

                osc.connect(gain);
                gain.connect(this.ctx.destination);

                osc.start(now);
                osc.stop(now + duration);
            }

            playStep() {
                this.playTone(130 + Math.random() * 25, 'triangle', 0.04, 0.025);
            }

            playBlip() {
                this.playTone(540, 'square', 0.06, 0.05, 720);
            }

            playDoorSlide(opening = true) {
                if (opening) {
                    this.playTone(160, 'sawtooth', 0.35, 0.06, 380);
                    setTimeout(() => this.playTone(620, 'square', 0.1, 0.05, 880), 260);
                } else {
                    this.playTone(340, 'sawtooth', 0.35, 0.06, 120);
                }
            }

            playLaser() {
                this.playTone(880, 'square', 0.1, 0.05, 220);
            }

            playExplosion() {
                this.playTone(140, 'sawtooth', 0.18, 0.07, 42);
            }

            playCoin() {
                this.playTone(987, 'square', 0.07, 0.05);
                setTimeout(() => this.playTone(1318, 'square', 0.14, 0.05), 70);
            }
        }

        const audio = new RetroAudio();

        // ==========================================
        // 2. GAME CONFIGURATION & WORLD MAP LAYOUT
        // ==========================================
        const TILE = 32; // Base logical pixel tile size
        const MAP_COLS = 42;
        const MAP_ROWS = 30;
        const WORLD_W = MAP_COLS * TILE;
        const WORLD_H = MAP_ROWS * TILE;

        // Tile IDs:
        // 0: Deep Space Void (outside hull, visible through panoramic windows)
        // 1: Cafeteria Main Metallic Floor
        // 2: Hull Wall (Solid)
        // 3: Panoramic Space Window (Solid, transparent to space background)
        // 4: Checkerboard Diner Floor (Food Counter & Arcade Zone)
        // 5: Observation Lounge Carpet (Plush Indigo/Purple)
        // 6: Airlock / Secret Room Hazard Steel Floor
        // 7: Caution Stripe Floor Threshold
        const mapGrid = Array.from({
            length: MAP_ROWS
        }, () => Array(MAP_COLS).fill(0));

        function buildStationMap() {
            // 1. Main Cafeteria Hull (cols 2..31, rows 3..26)
            for (let r = 3; r <= 26; r++) {
                for (let c = 2; c <= 31; c++) {
                    if (r === 3 || r === 26 || c === 2 || c === 31) {
                        mapGrid[r][c] = 2; // Exterior hull wall
                    } else {
                        mapGrid[r][c] = 1; // Standard deck floor
                    }
                }
            }

            // 2. Panoramic Space Windows along the North Wall (row 3) & West Lounge Wall (col 2)
            for (let c = 5; c <= 13; c++) mapGrid[3][c] = 3;
            for (let c = 18; c <= 26; c++) mapGrid[3][c] = 3;
            for (let r = 16; r <= 23; r++) mapGrid[r][2] = 3;

            // 3. Observation Lounge Carpet (Bottom-Left: cols 3..12, rows 15..25)
            for (let r = 15; r <= 25; r++) {
                for (let c = 3; c <= 12; c++) {
                    mapGrid[r][c] = 5;
                }
            }

            // 4. Food Counter & Kitchen Checkerboard Floor (Top-Left/Center: cols 3..15, rows 4..10)
            for (let r = 4; r <= 10; r++) {
                for (let c = 3; c <= 15; c++) {
                    mapGrid[r][c] = 4;
                }
            }

            // 5. Small Arcade Corner Floor (Right-Center: cols 24..30, rows 9..17)
            for (let r = 9; r <= 17; r++) {
                for (let c = 24; c <= 30; c++) {
                    mapGrid[r][c] = 4;
                }
            }

            // 6. Secret Airlock / Star-Gazing Alcove East of the Arcade (cols 31..39, rows 11..19)
            for (let r = 11; r <= 19; r++) {
                for (let c = 31; c <= 39; c++) {
                    if (r === 11 || r === 19 || c === 39 || c === 31) {
                        mapGrid[r][c] = 2;
                    } else {
                        mapGrid[r][c] = 6; // Airlock steel deck
                    }
                }
            }
            // Panoramic Eastern Space Window in the Secret Room!
            for (let r = 13; r <= 17; r++) {
                mapGrid[r][39] = 3;
            }

            // 7. DOORWAY RIGHT NEXT TO THE ARCADE (col 31, rows 14 & 15)
            // Positioned right next to the row of arcade cabinets (which sit along row 10-12 / col 29-30)
            mapGrid[14][31] = 7;
            mapGrid[15][31] = 7;
            mapGrid[14][30] = 7;
            mapGrid[15][30] = 7;
            mapGrid[14][32] = 7;
            mapGrid[15][32] = 7;
        }

        buildStationMap();

        // ==========================================
        // 3. SPACE PARALLAX BACKGROUND ENTITIES
        // ==========================================
        const stars = Array.from({
            length: 160
        }, () => ({
            x: Math.random() * 1800,
            y: Math.random() * 1200,
            size: Math.random() < 0.75 ? 2 : 3,
            twinkleOffset: Math.random() * Math.PI * 2,
            speed: 0.05 + Math.random() * 0.15,
            color: ['#ffffff', '#bae6fd', '#fde68a', '#f5d0fe'][Math.floor(Math.random() * 4)]
        }));

        const asteroids = Array.from({
            length: 7
        }, (_, i) => ({
            x: 150 + i * 240,
            y: 80 + (i % 4) * 260,
            r: 10 + (i % 3) * 6,
            vx: 0.25 + (i % 3) * 0.12,
            vy: 0.06 * (i % 2 === 0 ? 1 : -1),
            rot: Math.random() * Math.PI * 2
        }));

        // ==========================================
        // 4. FURNITURE, PROPS, DOORS & INTERACTIVES
        // ==========================================
        // Arcade Bulkhead Door State (Located at col 31, rows 14..15, right beside the arcade cabinets)
        const arcadeDoor = {
            id: 'arcade_bulkhead_door',
            x: 31 * TILE,
            y: 14 * TILE,
            w: TILE,
            h: TILE * 2,
            isOpen: false,
            openProgress: 0, // 0 = closed, 1 = fully open
            lockedOnce: false
        };

        // Solid Collision Rectangles (walls handled by grid, furniture here)
        const props = [
            // --- FOOD COUNTER & BAR (Top-Left) ---
            {
                type: 'counter_h',
                x: 4 * TILE,
                y: 7 * TILE,
                w: 9 * TILE,
                h: TILE,
                solid: true
            },
            {
                type: 'coffee_machine',
                x: 5 * TILE,
                y: 6 * TILE + 8,
                w: TILE,
                h: TILE - 8,
                solid: false
            },
            {
                type: 'slushie_machine',
                x: 7 * TILE,
                y: 6 * TILE + 8,
                w: TILE,
                h: TILE - 8,
                solid: false
            },
            {
                type: 'food_dispenser',
                x: 10 * TILE,
                y: 4 * TILE,
                w: 2 * TILE,
                h: TILE + 8,
                solid: true
            },
            {
                type: 'fridge_glow',
                x: 13 * TILE,
                y: 4 * TILE,
                w: 2 * TILE,
                h: TILE + 8,
                solid: true
            },
            {
                type: 'neon_menu',
                x: 6 * TILE,
                y: 3 * TILE + 6,
                w: 3 * TILE,
                h: 18,
                solid: false
            },

            // Bar stools in front of counter
            {
                type: 'stool',
                x: 5 * TILE + 6,
                y: 8 * TILE + 8,
                w: 20,
                h: 20,
                solid: true
            },
            {
                type: 'stool',
                x: 7 * TILE + 6,
                y: 8 * TILE + 8,
                w: 20,
                h: 20,
                solid: true
            },
            {
                type: 'stool',
                x: 9 * TILE + 6,
                y: 8 * TILE + 8,
                w: 20,
                h: 20,
                solid: true
            },
            {
                type: 'stool',
                x: 11 * TILE + 6,
                y: 8 * TILE + 8,
                w: 20,
                h: 20,
                solid: true
            },

            // --- MAIN DINING TABLES & BOOTHS (Center) ---
            {
                type: 'dining_table',
                x: 16 * TILE,
                y: 7 * TILE,
                w: 2 * TILE,
                h: TILE + 12,
                solid: true,
                food: 'burger'
            },
            {
                type: 'dining_table',
                x: 20 * TILE,
                y: 7 * TILE,
                w: 2 * TILE,
                h: TILE + 12,
                solid: true,
                food: 'ramen'
            },
            {
                type: 'dining_table',
                x: 16 * TILE,
                y: 12 * TILE,
                w: 2 * TILE,
                h: TILE + 12,
                solid: true,
                food: 'pie'
            },
            {
                type: 'dining_table',
                x: 20 * TILE,
                y: 12 * TILE,
                w: 2 * TILE,
                h: TILE + 12,
                solid: true,
                food: 'drink'
            },
            {
                type: 'dining_table',
                x: 16 * TILE,
                y: 18 * TILE,
                w: 2 * TILE,
                h: TILE + 12,
                solid: true,
                food: 'ramen'
            },
            {
                type: 'dining_table',
                x: 21 * TILE,
                y: 19 * TILE,
                w: 2 * TILE,
                h: TILE + 12,
                solid: true,
                food: 'burger'
            },

            // --- OBSERVATION LOUNGE (Bottom-Left near West Space Windows) ---
            {
                type: 'lounge_sofa_v',
                x: 4 * TILE,
                y: 17 * TILE,
                w: TILE + 6,
                h: 3 * TILE,
                solid: true
            },
            {
                type: 'holo_table',
                x: 6 * TILE + 8,
                y: 18 * TILE,
                w: TILE + 16,
                h: TILE + 16,
                solid: true
            },
            {
                type: 'lounge_sofa_h',
                x: 5 * TILE,
                y: 23 * TILE,
                w: 3 * TILE,
                h: TILE + 6,
                solid: true
            },

            // --- SMALL ARCADE CORNER (Right side, right beside the bulkhead door!) ---
            // Arcade Cabinets lined up along the top of the arcade zone (cols 25..28, row 9)
            {
                type: 'arcade_cab',
                id: 'arc1',
                name: 'STAR BLASTER 1984',
                color: '#f43f5e',
                screenColor: '#38bdf8',
                x: 25 * TILE,
                y: 9 * TILE,
                w: TILE + 4,
                h: TILE + 18,
                solid: true
            },
            {
                type: 'arcade_cab',
                id: 'arc2',
                name: 'NEBULA INVADERS',
                color: '#a855f7',
                screenColor: '#4ade80',
                x: 27 * TILE,
                y: 9 * TILE,
                w: TILE + 4,
                h: TILE + 18,
                solid: true
            },
            {
                type: 'arcade_cab',
                id: 'arc3',
                name: 'ASTEROID DASH DX',
                color: '#f59e0b',
                screenColor: '#facc15',
                x: 29 * TILE,
                y: 9 * TILE,
                w: TILE + 4,
                h: TILE + 18,
                solid: true
            },
            // Claw Machine & Jukebox in Arcade Corner
            {
                type: 'claw_machine',
                x: 25 * TILE,
                y: 15 * TILE + 8,
                w: TILE + 6,
                h: TILE + 16,
                solid: true
            },
            {
                type: 'jukebox',
                x: 27 * TILE + 8,
                y: 15 * TILE + 10,
                w: TILE + 4,
                h: TILE + 14,
                solid: true
            },
            // Glowing keypad terminal right beside the door (col 30, row 13)
            {
                type: 'door_keypad',
                x: 30 * TILE + 6,
                y: 13 * TILE + 6,
                w: 20,
                h: 24,
                solid: true
            },

            // --- SECRET AIRLOCK / VIP STAR DECK (Behind the door next to arcade!) ---
            {
                type: 'star_telescope',
                x: 36 * TILE + 8,
                y: 14 * TILE + 8,
                w: TILE + 12,
                h: TILE + 12,
                solid: true
            },
            {
                type: 'cargo_crate',
                x: 33 * TILE,
                y: 12 * TILE,
                w: TILE + 8,
                h: TILE,
                solid: true
            },
            {
                type: 'cargo_crate',
                x: 33 * TILE,
                y: 17 * TILE + 8,
                w: TILE + 8,
                h: TILE,
                solid: true
            },
            {
                type: 'captain_chair',
                x: 35 * TILE,
                y: 12 * TILE + 6,
                w: TILE + 6,
                h: TILE + 6,
                solid: true
            },

            // --- ALIEN POTTED PLANTS (Decoration around cafeteria) ---
            {
                type: 'alien_plant',
                x: 14 * TILE,
                y: 4 * TILE + 12,
                w: 26,
                h: 28,
                solid: true
            },
            {
                type: 'alien_plant',
                x: 29 * TILE + 4,
                y: 4 * TILE + 12,
                w: 26,
                h: 28,
                solid: true
            },
            {
                type: 'alien_plant',
                x: 3 * TILE + 6,
                y: 14 * TILE,
                w: 26,
                h: 28,
                solid: true
            },
            {
                type: 'alien_plant',
                x: 12 * TILE,
                y: 24 * TILE + 4,
                w: 26,
                h: 28,
                solid: true
            },
            {
                type: 'alien_plant',
                x: 29 * TILE + 4,
                y: 24 * TILE + 4,
                w: 26,
                h: 28,
                solid: true
            },
            {
                type: 'alien_plant',
                x: 37 * TILE + 6,
                y: 12 * TILE,
                w: 26,
                h: 28,
                solid: true
            }
        ];

        // ==========================================
        // 5. NPCs & PLAYER CHARACTER
        // ==========================================
        const player = {
            x: 14 * TILE,
            y: 15 * TILE,
            w: 22,
            h: 24,
            speed: 155,
            dir: 'down', // 'down', 'up', 'left', 'right'
            animTimer: 0,
            animFrame: 0,
            credits: 50,
            highScore: 0
        };

        const npcs = [{
                id: 'chef_bot',
                name: 'CHEF BEEP-9 (Robot Koki)',
                x: 8 * TILE,
                y: 5 * TILE + 12,
                w: 24,
                h: 26,
                type: 'robot',
                color: '#38bdf8',
                visor: '#fbbf24',
                bobOffset: 0,
                dialogue: [
                    "BZZT! Selamat datang di Astro-Diner 77, Kapten! Hari ini spesial ada Ramen Nebula Pedas dan Burger Meteor.",
                    "Oh, kamu melirik sudut Arcade di sebelah timur? Mesin Star Blaster-nya baru saja kuperbaiki!",
                    "Dan pintu baja di sebelah mesin arcade itu... katanya mengarah ke Dek Observasi Bintang Rahasia. Coba saja buka panelnya!"
                ],
                shopItem: {
                    name: 'Pesan Kopi Gravitasi Nol (5 ◈)',
                    cost: 5,
                    reply: 'SLURP! Kamu menyeruput Kopi Gravitasi Nol. Rasanya melayang di lidah! (+Energi kosmik)'
                }
            },
            {
                id: 'alien_zorp',
                name: 'ZORP (Pelancong Andromeda)',
                x: 15 * TILE + 4,
                y: 7 * TILE + 8,
                w: 22,
                h: 24,
                type: 'alien',
                color: '#4ade80',
                visor: '#f43f5e',
                bobOffset: 1.5,
                dialogue: [
                    "Glork-glork! Perjalanan dari Galaksi Andromeda bikin tiga perutku keroncongan.",
                    "Aku baru saja kalah skor di mesin Nebula Invaders. Bisakah kamu memecahkan rekor di sudut arcade?"
                ]
            },
            {
                id: 'astro_lyra',
                name: 'KADET LYRA (Teknisi Stasiun)',
                x: 8 * TILE,
                y: 19 * TILE + 8,
                w: 22,
                h: 24,
                type: 'human',
                color: '#f472b6',
                visor: '#38bdf8',
                bobOffset: 3.1,
                dialogue: [
                    "Pemandangan nebula dari jendela dek lounge ini selalu bikin tenang setelah shift memperbaiki panel surya.",
                    "Psst... aku barusan membuka kunci pengaman Pintu Sekat (Bulkhead) di sebelah mesin arcade. Kamu bisa masuk ke ruang teleskop di baliknya!"
                ]
            },
            {
                id: 'gamer_kid',
                name: 'REX-7 (Jagoan Arcade)',
                x: 26 * TILE + 4,
                y: 11 * TILE + 6,
                w: 22,
                h: 24,
                type: 'robot',
                color: '#fbbf24',
                visor: '#a855f7',
                bobOffset: 4.2,
                dialogue: [
                    "WOOAH! Hampir saja aku menembus gelombang ke-10 di Star Blaster!",
                    "Tekan [E] di depan salah satu mesin arcade ini kalau kamu berani menantang skor tertinggiku!"
                ]
            }
        ];

        // ==========================================
        // 6. INTERACTIVE ZONES / TRIGGERS
        // ==========================================
        function getInteractables() {
            const list = [];

            // 1. The Door Next to the Arcade!
            list.push({
                id: 'arcade_door_trigger',
                x: arcadeDoor.x - 18,
                y: arcadeDoor.y - 10,
                w: arcadeDoor.w + 36,
                h: arcadeDoor.h + 20,
                label: arcadeDoor.isOpen ? 'Tutup Pintu Dek Rahasia' : 'Buka Pintu Sebelah Arcade',
                onInteract: () => {
                    arcadeDoor.isOpen = !arcadeDoor.isOpen;
                    audio.playDoorSlide(arcadeDoor.isOpen);
                    if (arcadeDoor.isOpen && !arcadeDoor.lockedOnce) {
                        arcadeDoor.lockedOnce = true;
                        openDialogue(
                            'SISTEM PINTU SEKTOR-B',
                            'KLIK-HISS! Pintu geser hidrolik di sebelah mesin arcade terbuka! Jalur menuju Dek Observasi Rahasia kini bisa dilewati.'
                        );
                    }
                }
            });

            // 2. Arcade Machines
            props.filter(p => p.type === 'arcade_cab').forEach(cab => {
                list.push({
                    id: cab.id,
                    x: cab.x - 6,
                    y: cab.y,
                    w: cab.w + 12,
                    h: cab.h + 24,
                    label: `Mainkan ${cab.name}`,
                    onInteract: () => {
                        audio.playCoin();
                        startArcadeGame(cab.name, cab.screenColor);
                    }
                });
            });

            // 3. Claw Machine & Jukebox
            const claw = props.find(p => p.type === 'claw_machine');
            if (claw) {
                list.push({
                    id: 'claw_trigger',
                    x: claw.x - 8,
                    y: claw.y - 8,
                    w: claw.w + 16,
                    h: claw.h + 24,
                    label: 'Mesin Capit Boneka Alien (5 ◈)',
                    onInteract: () => {
                        if (player.credits >= 5) {
                            player.credits -= 5;
                            updateHUDCredits();
                            audio.playCoin();
                            const prizes = [
                                'Boneka Mini-Alien Bermata Tiga!',
                                'Gantungan Kunci Roket Retro!',
                                'Plushie Kucing Astronot!',
                                'Yah, capitnya meleset sedikit! Coba lagi lain kali.'
                            ];
                            const pick = prizes[Math.floor(Math.random() * prizes.length)];
                            openDialogue('MESIN CAPIT KOSMIK', `BZZZT... CLANK! Capit turun perlahan... Hasilnya: ${pick}`);
                        } else {
                            audio.playBlip();
                            openDialogue('MESIN CAPIT KOSMIK', 'Kredit tidak cukup! Mainkan game arcade untuk mendapatkan kredit tambahan.');
                        }
                    }
                });
            }

            const juke = props.find(p => p.type === 'jukebox');
            if (juke) {
                list.push({
                    id: 'jukebox_trigger',
                    x: juke.x - 8,
                    y: juke.y - 8,
                    w: juke.w + 16,
                    h: juke.h + 24,
                    label: 'Putar Jukebox Synthwave',
                    onInteract: () => {
                        audio.playTone(440, 'triangle', 0.12, 0.06);
                        setTimeout(() => audio.playTone(554, 'triangle', 0.12, 0.06), 110);
                        setTimeout(() => audio.playTone(659, 'triangle', 0.2, 0.06), 220);
                        openDialogue('JUKEBOX GALAKSI', '♪ Memutar lagu: "Midnight Cruise Over Saturn Rings (8-Bit Remix)" ♪ Suasana kafetaria terasa semakin hangat!');
                    }
                });
            }

            // 4. Secret Telescope inside the room behind the arcade door
            const tele = props.find(p => p.type === 'star_telescope');
            if (tele) {
                list.push({
                    id: 'tele_trigger',
                    x: tele.x - 14,
                    y: tele.y - 14,
                    w: tele.w + 28,
                    h: tele.h + 28,
                    label: 'Intip Teleskop Kuantum',
                    onInteract: () => {
                        audio.playBlip();
                        openDialogue(
                            'TELESKOP KUANTUM',
                            'Kamu mengintip melalui lensa kuantum... Terlihat gugus bintang Orion, cincin es raksasa, dan sebuah komet berkilau melintas di kejauhan!'
                        );
                    }
                });
            }

            // 5. Food Counter Interaction
            list.push({
                id: 'counter_trigger',
                x: 4 * TILE,
                y: 8 * TILE,
                w: 9 * TILE,
                h: 28,
                label: 'Bicara dengan Chef Beep-9',
                onInteract: () => {
                    const chef = npcs.find(n => n.id === 'chef_bot');
                    if (chef) triggerNPCDialogue(chef);
                }
            });

            // 6. All NPCs
            npcs.forEach(npc => {
                list.push({
                    id: npc.id,
                    x: npc.x - 18,
                    y: npc.y - 18,
                    w: npc.w + 36,
                    h: npc.h + 36,
                    label: `Bicara: ${npc.name.split(' ')[0]}`,
                    onInteract: () => triggerNPCDialogue(npc)
                });
            });

            return list;
        }

        // ==========================================
        // 7. INPUT HANDLING (KEYBOARD & TOUCH)
        // ==========================================
        const keys = {};
        let activeInteractable = null;
        let isDialogueOpen = false;
        let isArcadeOpen = false;

        window.addEventListener('keydown', (e) => {
            keys[e.code] = true;

            if (isArcadeOpen) {
                if (e.code === 'Escape') closeArcadeGame();
                if (e.code === 'Space') {
                    e.preventDefault();
                    arcadeFire();
                }
                return;
            }

            if (isDialogueOpen) {
                if (e.code === 'Escape' || e.code === 'KeyE' || e.code === 'Space') {
                    e.preventDefault();
                    closeDialogue();
                }
                return;
            }

            if (e.code === 'KeyE' || e.code === 'Space') {
                if (activeInteractable) {
                    e.preventDefault();
                    activeInteractable.onInteract();
                }
            }
        });

        window.addEventListener('keyup', (e) => {
            keys[e.code] = false;
        });

        // Touch D-Pad listeners
        document.querySelectorAll('.dpad-btn').forEach(btn => {
            const dir = btn.dataset.dir;
            const mapCode = {
                up: 'ArrowUp',
                down: 'ArrowDown',
                left: 'ArrowLeft',
                right: 'ArrowRight'
            } [dir];
            btn.addEventListener('touchstart', (e) => {
                e.preventDefault();
                keys[mapCode] = true;
            });
            btn.addEventListener('touchend', (e) => {
                e.preventDefault();
                keys[mapCode] = false;
            });
            btn.addEventListener('mousedown', () => {
                keys[mapCode] = true;
            });
            btn.addEventListener('mouseup', () => {
                keys[mapCode] = false;
            });
        });

        document.getElementById('touch-action-btn').addEventListener('click', () => {
            if (isDialogueOpen) closeDialogue();
            else if (activeInteractable) activeInteractable.onInteract();
        });

        document.getElementById('interact-prompt').addEventListener('click', () => {
            if (activeInteractable && !isDialogueOpen && !isArcadeOpen) {
                activeInteractable.onInteract();
            }
        });

        document.getElementById('btn-audio').addEventListener('click', (e) => {
            audio.enabled = !audio.enabled;
            e.target.textContent = audio.enabled ? '🔊 SFX: ON' : '🔇 SFX: OFF';
            if (audio.enabled) audio.playBlip();
        });

        // ==========================================
        // 8. DIALOGUE SYSTEM
        // ==========================================
        const dialogueBox = document.getElementById('dialogue-box');
        const dialogueSpeaker = document.getElementById('dialogue-speaker');
        const dialogueBody = document.getElementById('dialogue-body');
        const dialogueChoices = document.getElementById('dialogue-choices');

        function openDialogue(speaker, text, choices = []) {
            isDialogueOpen = true;
            dialogueSpeaker.textContent = speaker;
            dialogueBody.textContent = text;
            dialogueChoices.innerHTML = '';

            choices.forEach(ch => {
                const btn = document.createElement('button');
                btn.className = 'choice-btn';
                btn.textContent = ch.label;
                btn.onclick = () => {
                    ch.action();
                };
                dialogueChoices.appendChild(btn);
            });

            dialogueBox.style.display = 'flex';
        }

        function closeDialogue() {
            isDialogueOpen = false;
            dialogueBox.style.display = 'none';
            audio.playTone(320, 'square', 0.05, 0.03);
        }

        document.getElementById('dialogue-close-btn').addEventListener('click', closeDialogue);

        function triggerNPCDialogue(npc) {
            audio.playBlip();
            if (npc.lineIdx === undefined) npc.lineIdx = 0;
            const text = npc.dialogue[npc.lineIdx % npc.dialogue.length];
            npc.lineIdx++;

            const choices = [];
            if (npc.shopItem) {
                choices.push({
                    label: npc.shopItem.name,
                    action: () => {
                        if (player.credits >= npc.shopItem.cost) {
                            player.credits -= npc.shopItem.cost;
                            updateHUDCredits();
                            audio.playCoin();
                            dialogueBody.textContent = npc.shopItem.reply;
                            dialogueChoices.innerHTML = '';
                        } else {
                            dialogueBody.textContent = 'BZZT! Kreditmu tidak cukup, Kapten. Coba mainkan mesin arcade untuk hadiah kredit!';
                            dialogueChoices.innerHTML = '';
                        }
                    }
                });
            }

            openDialogue(npc.name, text, choices);
        }

        function updateHUDCredits() {
            document.getElementById('credit-count').textContent = player.credits;
        }

        // ==========================================
        // 9. PLAYABLE ARCADE MINI-GAME ("STAR BLASTER")
        // ==========================================
        const arcadeModal = document.getElementById('arcade-modal');
        const arcCanvas = document.getElementById('arcadeCanvas');
        const arcCtx = arcCanvas.getContext('2d');
        let arcState = null;

        function startArcadeGame(title, themeColor) {
            isArcadeOpen = true;
            document.getElementById('arcade-title').textContent = `★ ${title} ★`;
            arcadeModal.style.display = 'flex';

            arcState = {
                shipX: 150,
                bullets: [],
                enemies: [],
                score: 0,
                gameOver: false,
                spawnTimer: 0,
                themeColor: themeColor || '#38bdf8'
            };
        }

        function closeArcadeGame() {
            isArcadeOpen = false;
            arcadeModal.style.display = 'none';
            if (arcState && arcState.score > 0) {
                const reward = Math.max(5, Math.floor(arcState.score / 20));
                player.credits += reward;
                updateHUDCredits();
                openDialogue(
                    'MESIN ARCADE',
                    `Permainan selesai! Skor akhir: ${arcState.score}. Kamu mendapatkan hadiah +${reward} Kredit Kafetaria! ◈`
                );
            }
        }

        document.getElementById('arcade-exit-btn').addEventListener('click', closeArcadeGame);

        function arcadeFire() {
            if (!isArcadeOpen || !arcState) return;
            if (arcState.gameOver) {
                // Restart
                arcState.shipX = 150;
                arcState.bullets = [];
                arcState.enemies = [];
                arcState.score = 0;
                arcState.gameOver = false;
                return;
            }
            if (arcState.bullets.length < 4) {
                arcState.bullets.push({
                    x: arcState.shipX + 8,
                    y: 204
                });
                audio.playLaser();
            }
        }

        // On-screen Arcade Buttons
        let arcHoldLeft = false;
        let arcHoldRight = false;
        const btnArcL = document.getElementById('arc-left');
        const btnArcR = document.getElementById('arc-right');
        btnArcL.addEventListener('mousedown', () => arcHoldLeft = true);
        btnArcL.addEventListener('mouseup', () => arcHoldLeft = false);
        btnArcL.addEventListener('touchstart', (e) => {
            e.preventDefault();
            arcHoldLeft = true;
        });
        btnArcL.addEventListener('touchend', (e) => {
            e.preventDefault();
            arcHoldLeft = false;
        });

        btnArcR.addEventListener('mousedown', () => arcHoldRight = true);
        btnArcR.addEventListener('mouseup', () => arcHoldRight = false);
        btnArcR.addEventListener('touchstart', (e) => {
            e.preventDefault();
            arcHoldRight = true;
        });
        btnArcR.addEventListener('touchend', (e) => {
            e.preventDefault();
            arcHoldRight = false;
        });

        document.getElementById('arc-fire').addEventListener('click', arcadeFire);

        function updateAndDrawArcade(dt) {
            if (!isArcadeOpen || !arcState) return;

            arcCtx.fillStyle = '#050510';
            arcCtx.fillRect(0, 0, 320, 240);

            // Starfield inside arcade screen
            arcCtx.fillStyle = '#475569';
            for (let i = 0; i < 20; i++) {
                const sx = (i * 53) % 320;
                const sy = ((i * 37) + performance.now() * 0.05) % 240;
                arcCtx.fillRect(sx, sy, 2, 2);
            }

            if (!arcState.gameOver) {
                if (keys['ArrowLeft'] || keys['KeyA'] || arcHoldLeft) arcState.shipX -= 180 * dt;
                if (keys['ArrowRight'] || keys['KeyD'] || arcHoldRight) arcState.shipX += 180 * dt;
                arcState.shipX = Math.max(8, Math.min(292, arcState.shipX));

                // Update bullets
                arcState.bullets.forEach(b => b.y -= 280 * dt);
                arcState.bullets = arcState.bullets.filter(b => b.y > -10);

                // Spawn alien invaders
                arcState.spawnTimer -= dt;
                if (arcState.spawnTimer <= 0) {
                    arcState.enemies.push({
                        x: 16 + Math.random() * 270,
                        y: -16,
                        speed: 55 + Math.min(90, arcState.score * 0.4)
                    });
                    arcState.spawnTimer = Math.max(0.35, 1.05 - arcState.score * 0.004);
                }

                // Update enemies & collisions
                arcState.enemies.forEach(en => {
                    en.y += en.speed * dt;
                    if (en.y > 205) {
                        arcState.gameOver = true;
                        audio.playExplosion();
                    }
                    arcState.bullets.forEach(b => {
                        if (b.x > en.x - 4 && b.x < en.x + 18 && b.y > en.y && b.y < en.y + 16) {
                            en.dead = true;
                            b.y = -99;
                            arcState.score += 10;
                            audio.playExplosion();
                        }
                    });
                });
                arcState.enemies = arcState.enemies.filter(en => !en.dead);
            }

            // Draw Player Pixel Ship
            const sx = Math.floor(arcState.shipX);
            arcCtx.fillStyle = arcState.themeColor;
            arcCtx.fillRect(sx + 8, 206, 4, 6);
            arcCtx.fillRect(sx + 4, 212, 12, 6);
            arcCtx.fillRect(sx, 218, 20, 6);

            // Draw Bullets
            arcCtx.fillStyle = '#fde047';
            arcState.bullets.forEach(b => arcCtx.fillRect(Math.floor(b.x), Math.floor(b.y), 3, 8));

            // Draw Alien Enemies
            arcCtx.fillStyle = '#f43f5e';
            arcState.enemies.forEach(en => {
                const ex = Math.floor(en.x);
                const ey = Math.floor(en.y);
                arcCtx.fillRect(ex + 3, ey, 10, 4);
                arcCtx.fillRect(ex, ey + 4, 16, 6);
                arcCtx.fillRect(ex + 2, ey + 10, 3, 4);
                arcCtx.fillRect(ex + 11, ey + 10, 3, 4);
            });

            // Score UI
            arcCtx.fillStyle = '#ffffff';
            arcCtx.font = '12px "Press Start 2P", monospace';
            arcCtx.fillText(`SKOR: ${arcState.score}`, 10, 20);

            if (arcState.gameOver) {
                arcCtx.fillStyle = 'rgba(0,0,0,0.72)';
                arcCtx.fillRect(30, 85, 260, 75);
                arcCtx.fillStyle = '#f43f5e';
                arcCtx.fillText('GAME OVER!', 95, 114);
                arcCtx.fillStyle = '#fde047';
                arcCtx.font = '9px "Press Start 2P", monospace';
                arcCtx.fillText('TEKAN SPASI / TEMBAK ULANG', 45, 140);
            }
        }

        // ==========================================
        // 10. COLLISION DETECTION & MOVEMENT
        // ==========================================
        function rectOverlap(ax, ay, aw, ah, bx, by, bw, bh) {
            return ax < bx + bw && ax + aw > bx && ay < by + bh && ay + ah > by;
        }

        function isCollidingWorld(nx, ny, nw, nh) {
            // Check tile grid boundaries
            const leftTile = Math.floor(nx / TILE);
            const rightTile = Math.floor((nx + nw - 1) / TILE);
            const topTile = Math.floor(ny / TILE);
            const bottomTile = Math.floor((ny + nh - 1) / TILE);

            for (let r = topTile; r <= bottomTile; r++) {
                for (let c = leftTile; c <= rightTile; c++) {
                    if (r < 0 || r >= MAP_ROWS || c < 0 || c >= MAP_COLS) return true;
                    const t = mapGrid[r][c];
                    // Walls (2), Panoramic Windows (3), and Deep Space Void (0) are solid
                    if (t === 0 || t === 2 || t === 3) return true;
                }
            }

            // Check Bulkhead Door next to Arcade (solid when not fully open)
            if (arcadeDoor.openProgress < 0.75) {
                if (rectOverlap(nx, ny, nw, nh, arcadeDoor.x, arcadeDoor.y, arcadeDoor.w, arcadeDoor.h)) {
                    return true;
                }
            }

            // Check Solid Furniture Props
            for (const p of props) {
                if (p.solid && rectOverlap(nx, ny, nw, nh, p.x, p.y, p.w, p.h)) {
                    return true;
                }
            }

            // Check NPCs
            for (const n of npcs) {
                if (rectOverlap(nx, ny, nw, nh, n.x, n.y + 8, n.w, n.h - 8)) {
                    return true;
                }
            }

            return false;
        }

        function updateZoneIndicator() {
            const cx = Math.floor((player.x + player.w / 2) / TILE);
            const cy = Math.floor((player.y + player.h / 2) / TILE);
            let zone = 'Dek Utama Kafetaria';

            if (cx >= 31) {
                zone = 'Dek Observasi Rahasia (Sektor-B)';
            } else if (cx >= 24 && cy >= 9 && cy <= 17) {
                zone = 'Sudut Arcade Retro';
            } else if (cx <= 15 && cy <= 10) {
                zone = 'Konter Makanan & Bar Robot';
            } else if (cx <= 12 && cy >= 15) {
                zone = 'Lounge Pemandangan Nebula';
            }

            const label = document.getElementById('zone-name');
            if (label.textContent !== zone) {
                label.textContent = zone;
            }
        }

        // ==========================================
        // 11. PIXEL ART RENDERING ENGINE
        // ==========================================
        const canvas = document.getElementById('worldCanvas');
        const ctx = canvas.getContext('2d');
        const miniCanvas = document.getElementById('minimapCanvas');
        const miniCtx = miniCanvas.getContext('2d');

        // Camera state with smooth tracking and pixel zoom
        const camera = {
            x: 0,
            y: 0,
            zoom: 2 // Crisp 2x pixel magnification!
        };

        function resizeCanvas() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            ctx.imageSmoothingEnabled = false;
            camera.zoom = window.innerWidth < 640 ? 1.6 : 2.1;
        }
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        function drawSpaceBackdrop(time) {
            // Deep cosmic gradient
            const grad = ctx.createLinearGradient(0, 0, canvas.width, canvas.height);
            grad.addColorStop(0, '#04050e');
            grad.addColorStop(0.5, '#0b0f29');
            grad.addColorStop(1, '#120924');
            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            // Colorful Pixel Nebulae clouds (parallax)
            const nebX = (canvas.width * 0.25) - camera.x * 0.12;
            const nebY = (canvas.height * 0.2) - camera.y * 0.12;
            ctx.fillStyle = 'rgba(139, 92, 246, 0.14)';
            ctx.beginPath();
            ctx.arc(nebX, nebY, 190, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = 'rgba(14, 165, 233, 0.12)';
            ctx.beginPath();
            ctx.arc(nebX + 260, nebY + 120, 230, 0, Math.PI * 2);
            ctx.fill();

            // Distant Ringed Planet visible through windows
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

            // Twinkling Stars
            stars.forEach(s => {
                const sx = ((s.x - camera.x * s.speed) % canvas.width + canvas.width) % canvas.width;
                const sy = ((s.y - camera.y * s.speed) % canvas.height + canvas.height) % canvas.height;
                const alpha = 0.45 + 0.55 * Math.sin(time * 2.5 + s.twinkleOffset);
                ctx.globalAlpha = alpha;
                ctx.fillStyle = s.color;
                ctx.fillRect(Math.floor(sx), Math.floor(sy), s.size, s.size);
            });
            ctx.globalAlpha = 1;

            // Drifting Pixel Asteroids
            asteroids.forEach(a => {
                a.x = (a.x + a.vx) % 1600;
                const ax = a.x - camera.x * 0.25;
                const ay = a.y - camera.y * 0.25;
                ctx.fillStyle = '#475569';
                ctx.fillRect(Math.floor(ax), Math.floor(ay), a.r, a.r * 0.8);
                ctx.fillStyle = '#334155';
                ctx.fillRect(Math.floor(ax + 3), Math.floor(ay + 3), a.r * 0.4, a.r * 0.4);
            });
        }

        function drawTileMap() {
            for (let r = 0; r < MAP_ROWS; r++) {
                for (let c = 0; c < MAP_COLS; c++) {
                    const t = mapGrid[r][c];
                    if (t === 0) continue; // Transparent void

                    const x = c * TILE;
                    const y = r * TILE;

                    if (t === 1) {
                        // Main Metallic Sci-Fi Floor
                        ctx.fillStyle = (r + c) % 2 === 0 ? '#1e2638' : '#1b2233';
                        ctx.fillRect(x, y, TILE, TILE);
                        ctx.strokeStyle = '#28334a';
                        ctx.lineWidth = 1;
                        ctx.strokeRect(x + 0.5, y + 0.5, TILE - 1, TILE - 1);
                        // Subtle rivet dots
                        if ((r * 7 + c * 13) % 5 === 0) {
                            ctx.fillStyle = '#334155';
                            ctx.fillRect(x + 4, y + 4, 2, 2);
                            ctx.fillRect(x + TILE - 6, y + TILE - 6, 2, 2);
                        }
                    } else if (t === 2) {
                        // Heavy Spaceship Hull Wall
                        ctx.fillStyle = '#334155';
                        ctx.fillRect(x, y, TILE, TILE);
                        ctx.fillStyle = '#475569';
                        ctx.fillRect(x + 2, y + 2, TILE - 4, 10);
                        ctx.fillStyle = '#1e293b';
                        ctx.fillRect(x, y + TILE - 6, TILE, 6);
                        // Neon accent trim along interior bottom edge
                        if (r < MAP_ROWS - 1 && mapGrid[r + 1][c] !== 0 && mapGrid[r + 1][c] !== 2) {
                            ctx.fillStyle = '#0ea5e9';
                            ctx.fillRect(x, y + TILE - 3, TILE, 2);
                        }
                    } else if (t === 3) {
                        // Panoramic Space Viewport Window
                        ctx.fillStyle = 'rgba(56, 189, 248, 0.14)';
                        ctx.fillRect(x, y, TILE, TILE);
                        // Window frame bars
                        ctx.fillStyle = '#475569';
                        ctx.fillRect(x, y, TILE, 4);
                        ctx.fillRect(x, y + TILE - 4, TILE, 4);
                        ctx.fillRect(x, y, 3, TILE);
                        // Glass reflection streak
                        ctx.fillStyle = 'rgba(255, 255, 255, 0.22)';
                        ctx.fillRect(x + 6, y + 6, 3, TILE - 12);
                    } else if (t === 4) {
                        // Retro Diner Checkerboard Floor (Food Bar & Arcade Zone)
                        const sub = TILE / 2;
                        for (let sr = 0; sr < 2; sr++) {
                            for (let sc = 0; sc < 2; sc++) {
                                ctx.fillStyle = (sr + sc) % 2 === 0 ? '#27233a' : '#181526';
                                ctx.fillRect(x + sc * sub, y + sr * sub, sub, sub);
                            }
                        }
                    } else if (t === 5) {
                        // Observation Lounge Plush Carpet
                        ctx.fillStyle = '#251b42';
                        ctx.fillRect(x, y, TILE, TILE);
                        ctx.fillStyle = '#2e2152';
                        ctx.fillRect(x + 2, y + 2, TILE - 4, TILE - 4);
                    } else if (t === 6) {
                        // Secret Airlock Steel Deck
                        ctx.fillStyle = '#1a242b';
                        ctx.fillRect(x, y, TILE, TILE);
                        ctx.strokeStyle = '#2b3b47';
                        ctx.strokeRect(x + 0.5, y + 0.5, TILE - 1, TILE - 1);
                        ctx.fillStyle = '#0f172a';
                        ctx.fillRect(x + 12, y + 12, 8, 8);
                    } else if (t === 7) {
                        // Caution Hazard Stripes Threshold (around the Door next to Arcade!)
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

        function drawBulkheadDoor(time) {
            const d = arcadeDoor;
            const slideOffset = Math.floor((d.h / 2) * d.openProgress);

            // Door Frame Housing
            ctx.fillStyle = '#0f172a';
            ctx.fillRect(d.x - 2, d.y - 2, d.w + 4, d.h + 4);

            // Top sliding leaf
            const topH = (d.h / 2) - slideOffset;
            if (topH > 0) {
                ctx.fillStyle = '#64748b';
                ctx.fillRect(d.x + 2, d.y, d.w - 4, topH);
                ctx.fillStyle = '#334155';
                ctx.fillRect(d.x + 6, d.y + 4, d.w - 12, Math.max(0, topH - 6));
            }

            // Bottom sliding leaf
            const botH = (d.h / 2) - slideOffset;
            if (botH > 0) {
                ctx.fillStyle = '#64748b';
                ctx.fillRect(d.x + 2, d.y + d.h - botH, d.w - 4, botH);
                ctx.fillStyle = '#334155';
                ctx.fillRect(d.x + 6, d.y + d.h - botH + 2, d.w - 12, Math.max(0, botH - 6));
            }

            // Glowing Status Light Strip (Green if open, Amber/Red pulse if closed)
            const statusColor = d.isOpen ? '#34d399' : (Math.sin(time * 5) > 0 ? '#fbbf24' : '#f43f5e');
            ctx.fillStyle = statusColor;
            ctx.fillRect(d.x - 4, d.y + 6, 3, 12);
            ctx.fillRect(d.x + d.w + 1, d.y + 6, 3, 12);
        }

        function drawProp(p, time) {
            const {
                x,
                y,
                w,
                h,
                type
            } = p;

            if (type === 'counter_h') {
                ctx.fillStyle = '#334155';
                ctx.fillRect(x, y, w, h);
                ctx.fillStyle = '#cbd5e1'; // Metallic countertop
                ctx.fillRect(x, y, w, 10);
                ctx.fillStyle = '#f43f5e'; // Neon pink diner stripe
                ctx.fillRect(x, y + 14, w, 4);
            } else if (type === 'stool') {
                ctx.fillStyle = '#0f172a';
                ctx.fillRect(x + 4, y + 6, w - 8, h - 6);
                ctx.fillStyle = '#f43f5e';
                ctx.fillRect(x + 2, y + 2, w - 4, 10);
                ctx.fillStyle = '#fda4af';
                ctx.fillRect(x + 4, y + 3, w - 10, 3);
            } else if (type === 'coffee_machine') {
                ctx.fillStyle = '#475569';
                ctx.fillRect(x + 4, y, w - 8, h);
                ctx.fillStyle = '#38bdf8';
                ctx.fillRect(x + 8, y + 4, w - 16, 6);
            } else if (type === 'slushie_machine') {
                ctx.fillStyle = '#64748b';
                ctx.fillRect(x + 3, y, w - 6, h);
                ctx.fillStyle = '#a855f7';
                ctx.fillRect(x + 5, y + 2, 9, 12);
                ctx.fillStyle = '#34d399';
                ctx.fillRect(x + 16, y + 2, 9, 12);
            } else if (type === 'food_dispenser' || type === 'fridge_glow') {
                ctx.fillStyle = '#1e293b';
                ctx.fillRect(x, y, w, h);
                ctx.strokeStyle = '#64748b';
                ctx.strokeRect(x + 1, y + 1, w - 2, h - 2);
                ctx.fillStyle = type === 'fridge_glow' ? '#38bdf8' : '#fbbf24';
                ctx.fillRect(x + 6, y + 6, w - 12, h - 16);
            } else if (type === 'neon_menu') {
                ctx.fillStyle = '#090d16';
                ctx.fillRect(x, y, w, h);
                ctx.strokeStyle = '#f43f5e';
                ctx.strokeRect(x, y, w, h);
                ctx.fillStyle = '#fde047';
                ctx.fillRect(x + 6, y + 5, w - 12, 3);
                ctx.fillStyle = '#38bdf8';
                ctx.fillRect(x + 6, y + 11, w - 24, 3);
            } else if (type === 'dining_table') {
                // Chairs around table
                ctx.fillStyle = '#475569';
                ctx.fillRect(x - 8, y + 10, 8, 22);
                ctx.fillRect(x + w, y + 10, 8, 22);
                // Table base & glowing sci-fi tabletop
                ctx.fillStyle = '#1e293b';
                ctx.fillRect(x + 4, y + 10, w - 8, h - 6);
                ctx.fillStyle = '#cbd5e1';
                ctx.fillRect(x, y, w, h - 10);
                ctx.fillStyle = '#38bdf8';
                ctx.fillRect(x + 3, y + h - 12, w - 6, 2);
                // Space food plate on table
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(x + 14, y + 10, 12, 8);
                ctx.fillStyle = p.food === 'burger' ? '#f59e0b' : (p.food === 'ramen' ? '#f43f5e' : '#34d399');
                ctx.fillRect(x + 16, y + 11, 8, 5);
            } else if (type === 'lounge_sofa_v' || type === 'lounge_sofa_h') {
                ctx.fillStyle = '#4c1d95';
                ctx.fillRect(x, y, w, h);
                ctx.fillStyle = '#7c3aed';
                ctx.fillRect(x + 4, y + 4, w - 8, h - 8);
            } else if (type === 'holo_table') {
                ctx.fillStyle = '#1e293b';
                ctx.fillRect(x, y, w, h);
                ctx.strokeStyle = '#38bdf8';
                ctx.strokeRect(x + 2, y + 2, w - 4, h - 4);
                // Hologram projection pulse
                const r = 6 + Math.sin(time * 4) * 2;
                ctx.fillStyle = 'rgba(56, 189, 248, 0.5)';
                ctx.beginPath();
                ctx.arc(x + w / 2, y + h / 2, r, 0, Math.PI * 2);
                ctx.fill();
            } else if (type === 'arcade_cab') {
                // Retro Arcade Cabinet Body
                ctx.fillStyle = '#1e1b4b';
                ctx.fillRect(x, y, w, h);
                // Side T-Molding trim
                ctx.fillStyle = p.color;
                ctx.fillRect(x, y, 4, h);
                ctx.fillRect(x + w - 4, y, 4, h);
                // Top glowing marquee
                ctx.fillStyle = p.color;
                ctx.fillRect(x + 4, y + 2, w - 8, 8);
                // CRT Screen with animated pixel game demo!
                ctx.fillStyle = '#020617';
                ctx.fillRect(x + 5, y + 12, w - 10, 18);
                ctx.fillStyle = p.screenColor;
                const shipBlink = Math.floor((time * 12) % (w - 16));
                ctx.fillRect(x + 7 + shipBlink, y + 25, 4, 3);
                ctx.fillStyle = '#f43f5e';
                ctx.fillRect(x + 9, y + 15, 4, 3);
                ctx.fillRect(x + 19, y + 17, 4, 3);
                // Control deck & joystick
                ctx.fillStyle = '#334155';
                ctx.fillRect(x + 3, y + 31, w - 6, 10);
                ctx.fillStyle = '#fde047';
                ctx.fillRect(x + 8, y + 33, 3, 4);
                ctx.fillStyle = '#f43f5e';
                ctx.fillRect(x + 20, y + 34, 3, 3);
            } else if (type === 'claw_machine') {
                ctx.fillStyle = '#be185d';
                ctx.fillRect(x, y, w, h);
                ctx.fillStyle = '#bae6fd';
                ctx.fillRect(x + 4, y + 6, w - 8, 22);
                // Cute plushies inside
                ctx.fillStyle = '#4ade80';
                ctx.fillRect(x + 7, y + 20, 7, 7);
                ctx.fillStyle = '#fde047';
                ctx.fillRect(x + 18, y + 20, 7, 7);
            } else if (type === 'jukebox') {
                ctx.fillStyle = '#78350f';
                ctx.fillRect(x, y + 6, w, h - 6);
                ctx.fillStyle = Math.sin(time * 6) > 0 ? '#f43f5e' : '#38bdf8';
                ctx.beginPath();
                ctx.arc(x + w / 2, y + 10, w / 2 - 2, Math.PI, 0);
                ctx.fill();
            } else if (type === 'door_keypad') {
                // Wall-mounted security keypad right next to the arcade door
                ctx.fillStyle = '#1e293b';
                ctx.fillRect(x, y, w, h);
                ctx.fillStyle = arcadeDoor.isOpen ? '#34d399' : '#38bdf8';
                ctx.fillRect(x + 4, y + 4, w - 8, 7);
                ctx.fillStyle = '#94a3b8';
                ctx.fillRect(x + 4, y + 14, w - 8, 6);
            } else if (type === 'star_telescope') {
                ctx.fillStyle = '#475569';
                ctx.fillRect(x + 14, y + 20, 12, 18);
                ctx.fillStyle = '#e2e8f0';
                ctx.fillRect(x + 6, y + 8, 28, 10);
                ctx.fillStyle = '#38bdf8';
                ctx.fillRect(x + 30, y + 6, 6, 14);
            } else if (type === 'cargo_crate') {
                ctx.fillStyle = '#334155';
                ctx.fillRect(x, y, w, h);
                ctx.strokeStyle = '#f59e0b';
                ctx.strokeRect(x + 2, y + 2, w - 4, h - 4);
            } else if (type === 'captain_chair') {
                ctx.fillStyle = '#1e293b';
                ctx.fillRect(x, y, w, h);
                ctx.fillStyle = '#0284c7';
                ctx.fillRect(x + 5, y + 5, w - 10, h - 10);
            } else if (type === 'alien_plant') {
                // Glowing pot
                ctx.fillStyle = '#475569';
                ctx.fillRect(x + 5, y + 16, 16, 12);
                // Alien bioluminescent leaves
                ctx.fillStyle = '#10b981';
                ctx.fillRect(x + 3, y + 6, 20, 10);
                ctx.fillStyle = '#a855f7';
                ctx.fillRect(x + 9, y + 2, 8, 8);
            }
        }

        function drawCharacter(ent, isPlayer = false, time = 0) {
            const x = Math.floor(ent.x);
            const y = Math.floor(ent.y);
            const bob = isPlayer ?
                (ent.animFrame % 2 === 1 ? -2 : 0) :
                Math.round(Math.sin(time * 3 + (ent.bobOffset || 0)) * 1.5);

            // Ground shadow
            ctx.fillStyle = 'rgba(0, 0, 0, 0.38)';
            ctx.fillRect(x + 3, y + ent.h - 3, ent.w - 6, 5);

            // Legs / Boots
            const stepOffset = isPlayer && ent.animFrame % 2 === 1 ? 2 : 0;
            ctx.fillStyle = '#1e293b';
            ctx.fillRect(x + 5, y + ent.h - 7 - stepOffset, 4, 7);
            ctx.fillRect(x + ent.w - 9, y + ent.h - 7 + (stepOffset ? -2 : 0), 4, 7);

            // Spacesuit / Body
            const suitColor = isPlayer ? '#f8fafc' : ent.color;
            ctx.fillStyle = suitColor;
            ctx.fillRect(x + 3, y + 9 + bob, ent.w - 6, 12);

            // Backpack (for player astronaut)
            if (isPlayer) {
                ctx.fillStyle = '#94a3b8';
                ctx.fillRect(x + 1, y + 10 + bob, 3, 9);
            }

            // Head / Helmet
            ctx.fillStyle = suitColor;
            ctx.fillRect(x + 4, y + bob, ent.w - 8, 10);

            // Alien antennae or Robot antenna
            if (!isPlayer && ent.type === 'alien') {
                ctx.fillStyle = '#4ade80';
                ctx.fillRect(x + 6, y - 4 + bob, 2, 4);
                ctx.fillRect(x + ent.w - 8, y - 4 + bob, 2, 4);
            } else if (!isPlayer && ent.type === 'robot') {
                ctx.fillStyle = '#fbbf24';
                ctx.fillRect(x + 10, y - 4 + bob, 2, 4);
            }

            // Glowing Visor / Eyes
            ctx.fillStyle = isPlayer ? '#38bdf8' : ent.visor;
            if (!isPlayer || ent.dir === 'down') {
                ctx.fillRect(x + 6, y + 3 + bob, ent.w - 12, 4);
            } else if (ent.dir === 'left') {
                ctx.fillRect(x + 4, y + 3 + bob, 7, 4);
            } else if (ent.dir === 'right') {
                ctx.fillRect(x + ent.w - 11, y + 3 + bob, 7, 4);
            }
        }

        function drawMinimap() {
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

            // Highlight Arcade Door on Minimap
            miniCtx.fillStyle = arcadeDoor.isOpen ? '#34d399' : '#f43f5e';
            miniCtx.fillRect((arcadeDoor.x / TILE) * sx, (arcadeDoor.y / TILE) * sy, sx + 1, sy * 2);

            // Highlight NPCs
            miniCtx.fillStyle = '#fbbf24';
            npcs.forEach(n => {
                miniCtx.fillRect((n.x / TILE) * sx, (n.y / TILE) * sy, 3, 3);
            });

            // Highlight Player
            miniCtx.fillStyle = '#ffffff';
            miniCtx.fillRect((player.x / TILE) * sx - 1, (player.y / TILE) * sy - 1, 4, 4);
        }

        // ==========================================
        // 12. MAIN GAME LOOP
        // ==========================================
        let lastTime = performance.now();

        function update(dt, time) {
            // Animate bulkhead door sliding smoothly
            const targetDoor = arcadeDoor.isOpen ? 1 : 0;
            arcadeDoor.openProgress += (targetDoor - arcadeDoor.openProgress) * Math.min(1, dt * 7);

            if (isArcadeOpen) {
                updateAndDrawArcade(dt);
                return;
            }

            if (!isDialogueOpen) {
                let vx = 0;
                let vy = 0;
                if (keys['KeyW'] || keys['ArrowUp']) {
                    vy -= 1;
                    player.dir = 'up';
                }
                if (keys['KeyS'] || keys['ArrowDown']) {
                    vy += 1;
                    player.dir = 'down';
                }
                if (keys['KeyA'] || keys['ArrowLeft']) {
                    vx -= 1;
                    player.dir = 'left';
                }
                if (keys['KeyD'] || keys['ArrowRight']) {
                    vx += 1;
                    player.dir = 'right';
                }

                if (vx !== 0 || vy !== 0) {
                    const len = Math.hypot(vx, vy);
                    vx = (vx / len) * player.speed * dt;
                    vy = (vy / len) * player.speed * dt;

                    if (!isCollidingWorld(player.x + vx, player.y, player.w, player.h)) {
                        player.x += vx;
                    }
                    if (!isCollidingWorld(player.x, player.y + vy, player.w, player.h)) {
                        player.y += vy;
                    }

                    player.animTimer += dt;
                    if (player.animTimer > 0.16) {
                        player.animTimer = 0;
                        player.animFrame++;
                        audio.playStep();
                    }
                } else {
                    player.animFrame = 0;
                }
            }

            // Smooth Camera Follow centered on player
            const targetCamX = (player.x + player.w / 2) - (canvas.width / (2 * camera.zoom));
            const targetCamY = (player.y + player.h / 2) - (canvas.height / (2 * camera.zoom));
            camera.x += (targetCamX - camera.x) * Math.min(1, dt * 8);
            camera.y += (targetCamY - camera.y) * Math.min(1, dt * 8);

            // Check nearby interactive objects
            const interactables = getInteractables();
            activeInteractable = null;
            for (const item of interactables) {
                if (rectOverlap(player.x - 6, player.y - 6, player.w + 12, player.h + 12, item.x, item.y, item.w, item.h)) {
                    activeInteractable = item;
                    break;
                }
            }

            const promptEl = document.getElementById('interact-prompt');
            if (activeInteractable && !isDialogueOpen) {
                promptEl.style.display = 'flex';
                document.getElementById('interact-prompt-text').textContent = activeInteractable.label;
            } else {
                promptEl.style.display = 'none';
            }

            updateZoneIndicator();
        }

        function render(time) {
            drawSpaceBackdrop(time);

            ctx.save();
            ctx.scale(camera.zoom, camera.zoom);
            ctx.translate(-Math.floor(camera.x), -Math.floor(camera.y));

            drawTileMap();
            drawBulkheadDoor(time);

            // Depth-sort props, NPCs, and player by Y position
            const renderList = [
                ...props.map(p => ({
                    kind: 'prop',
                    ySort: p.y + p.h,
                    data: p
                })),
                ...npcs.map(n => ({
                    kind: 'npc',
                    ySort: n.y + n.h,
                    data: n
                })),
                {
                    kind: 'player',
                    ySort: player.y + player.h,
                    data: player
                }
            ];

            renderList.sort((a, b) => a.ySort - b.ySort);

            for (const item of renderList) {
                if (item.kind === 'prop') drawProp(item.data, time);
                else if (item.kind === 'npc') drawCharacter(item.data, false, time);
                else if (item.kind === 'player') drawCharacter(item.data, true, time);
            }

            ctx.restore();

            drawMinimap();
        }

        function gameLoop(now) {
            const dt = Math.min(0.05, (now - lastTime) / 1000);
            lastTime = now;
            const timeSec = now / 1000;

            update(dt, timeSec);
            render(timeSec);

            requestAnimationFrame(gameLoop);
        }

        window.onload = () => {
            requestAnimationFrame(gameLoop);
        };
    </script>
</body>

</html>