<?php
declare(strict_types=1);

/**
 * Dekorasi pixel-art.
 *
 * Sprite statis:
 * - pixel_sprite_rocket(): roket dengan api & asap berkedip.
 * - pixel_sprite_astro():  astronot dengan tangan melambai.
 * - pixel_sprite_coin():   koin emas piksel.
 * - pixel_sprite_planet(): planet bercincin.
 *
 * Sprite animasi frame-by-frame (gaya GIF):
 * - pixel_gif_astro():  empat frame astronot; CSS menggeser strip.
 * - pixel_gif_rocket(): empat frame roket, api berkedip.
 *
 * Scene:
 * - pixel_decor_layer():      lapisan latar semua halaman.
 * - pixel_countdown_scene():  isi layar hitung mundur saat mulai kuis.
 *
 * Semua elemen dekoratif memakai aria-hidden + pointer-events: none.
 */

if (!defined('APP_ROOT')) {
    return;
}

/** Roket piksel, api & asap berkedip. */
function pixel_sprite_rocket(string $class = ''): string
{
    $cls = $class !== '' ? ' class="' . e($class) . '"' : '';

    return <<<SVG
<svg{$cls} viewBox="0 0 12 21" shape-rendering="crispEdges" focusable="false" role="presentation">
    <g class="px-trail">
        <rect x="5" y="18" width="2" height="1" fill="#ff4a8d" opacity=".65"/>
        <rect x="5" y="19" width="2" height="1" fill="#ff4a8d" opacity=".4"/>
        <rect x="5" y="20" width="2" height="1" fill="#ff4a8d" opacity=".2"/>
    </g>
    <g class="px-flame">
        <rect x="4" y="13" width="4" height="2" fill="#ffcb45"/>
        <rect x="5" y="15" width="2" height="2" fill="#ffcb45"/>
        <rect x="5" y="17" width="2" height="1" fill="#ff4a8d"/>
    </g>
    <rect x="5" y="0" width="2" height="2" fill="#ff4a8d"/>
    <rect x="4" y="2" width="4" height="2" fill="#ff4a8d"/>
    <rect x="3" y="4" width="6" height="9" fill="#dbfcff"/>
    <rect x="3" y="4" width="1" height="9" fill="#7df4ff"/>
    <rect x="8" y="4" width="1" height="9" fill="#9fe8ef"/>
    <rect x="5" y="6" width="2" height="3" fill="#0a0e19"/>
    <rect x="5" y="7" width="2" height="1" fill="#00f0ff"/>
    <rect x="5" y="6" width="1" height="1" fill="#7df4ff"/>
    <rect x="1" y="9" width="2" height="4" fill="#ff4a8d"/>
    <rect x="9" y="9" width="2" height="4" fill="#ff4a8d"/>
    <rect x="4" y="12" width="4" height="1" fill="#ff4a8d"/>
</svg>
SVG;
}

/** Astronot piksel yang melambai. */
function pixel_sprite_astro(string $class = ''): string
{
    $cls = $class !== '' ? ' class="' . e($class) . '"' : '';

    return <<<SVG
<svg{$cls} viewBox="0 0 12 14" shape-rendering="crispEdges" focusable="false" role="presentation">
    <rect x="6" y="0" width="1" height="1" fill="#ffcb45"/>
    <rect x="3" y="1" width="6" height="5" fill="#dfe2f2"/>
    <rect x="4" y="2" width="4" height="3" fill="#0a0e19"/>
    <rect x="4" y="2" width="2" height="1" fill="#00f0ff"/>
    <rect x="4" y="3" width="1" height="1" fill="#7df4ff"/>
    <rect x="1" y="6" width="2" height="4" fill="#ff4a8d"/>
    <rect x="3" y="6" width="6" height="5" fill="#dfe2f2"/>
    <rect x="5" y="7" width="2" height="2" fill="#00f0ff"/>
    <g class="px-arm">
        <rect x="9" y="6" width="2" height="2" fill="#dfe2f2"/>
        <rect x="9" y="8" width="2" height="1" fill="#b9cacb"/>
    </g>
    <rect x="3" y="11" width="2" height="2" fill="#dfe2f2"/>
    <rect x="7" y="11" width="2" height="2" fill="#dfe2f2"/>
    <rect x="3" y="13" width="2" height="1" fill="#ff4a8d"/>
    <rect x="7" y="13" width="2" height="1" fill="#ff4a8d"/>
</svg>
SVG;
}

/**
 * Astronot piksel bergaya GIF.
 * Empat frame ditumpuk vertikal; CSS menggeser strip sehingga
 * tampil sebagai animasi frame-by-frame (seperti GIF).
 */
function pixel_gif_astro(string $class = ''): string
{
    $cls = e(trim('px-gif ' . $class));

    $frame = static function (int $y, int $armY, int $armH, bool $blink): string {
        $svg  = '<g transform="translate(0,' . $y . ')">';
        $svg .= '<rect x="6" y="0" width="1" height="1" fill="#ffcb45"/>';
        $svg .= '<rect x="3" y="1" width="6" height="5" fill="#dfe2f2"/>';
        $svg .= '<rect x="4" y="2" width="4" height="3" fill="#0a0e19"/>';
        if (!$blink) {
            $svg .= '<rect x="4" y="2" width="2" height="1" fill="#00f0ff"/>';
            $svg .= '<rect x="4" y="3" width="1" height="1" fill="#7df4ff"/>';
        }
        $svg .= '<rect x="1" y="6" width="2" height="4" fill="#ff4a8d"/>';
        $svg .= '<rect x="3" y="6" width="6" height="5" fill="#dfe2f2"/>';
        $svg .= '<rect x="5" y="7" width="2" height="2" fill="#00f0ff"/>';
        $svg .= '<rect x="9" y="' . $armY . '" width="2" height="' . $armH . '" fill="#dfe2f2"/>';
        $svg .= '<rect x="9" y="' . ($armY + $armH) . '" width="2" height="1" fill="#b9cacb"/>';
        $svg .= '<rect x="3" y="11" width="2" height="2" fill="#dfe2f2"/>';
        $svg .= '<rect x="7" y="11" width="2" height="2" fill="#dfe2f2"/>';
        $svg .= '<rect x="3" y="13" width="2" height="1" fill="#ff4a8d"/>';
        $svg .= '<rect x="7" y="13" width="2" height="1" fill="#ff4a8d"/>';
        $svg .= '</g>';

        return $svg;
    };

    $frames = $frame(0, 6, 2, false)
        . $frame(14, 4, 3, false)
        . $frame(28, 3, 3, true)
        . $frame(42, 5, 2, false);

    return <<<SVG
<svg class="{$cls}" viewBox="0 0 12 14" shape-rendering="crispEdges" focusable="false" role="presentation">
    <g class="px-gif-track">{$frames}</g>
</svg>
SVG;
}

/**
 * Roket piksel bergaya GIF.
 * Empat frame (api berbeda panjang) ditumpuk vertikal; CSS menggeser
 * strip sehingga api tampak berkedip seperti animasi GIF.
 */
function pixel_gif_rocket(string $class = ''): string
{
    $cls = e(trim('px-gif px-gif--rocket ' . $class));

    $frame = static function (int $y, int $flameHeight): string {
        $svg  = '<g transform="translate(0,' . $y . ')">';
        $svg .= '<rect x="4" y="13" width="4" height="2" fill="#ffcb45"/>';
        $svg .= '<rect x="5" y="15" width="2" height="' . $flameHeight . '" fill="#ffcb45"/>';
        $svg .= '<rect x="5" y="' . (15 + $flameHeight) . '" width="2" height="1" fill="#ff4a8d"/>';
        $svg .= '<rect x="5" y="0" width="2" height="2" fill="#ff4a8d"/>';
        $svg .= '<rect x="4" y="2" width="4" height="2" fill="#ff4a8d"/>';
        $svg .= '<rect x="3" y="4" width="6" height="9" fill="#dbfcff"/>';
        $svg .= '<rect x="3" y="4" width="1" height="9" fill="#7df4ff"/>';
        $svg .= '<rect x="8" y="4" width="1" height="9" fill="#9fe8ef"/>';
        $svg .= '<rect x="5" y="6" width="2" height="3" fill="#0a0e19"/>';
        $svg .= '<rect x="5" y="7" width="2" height="1" fill="#00f0ff"/>';
        $svg .= '<rect x="5" y="6" width="1" height="1" fill="#7df4ff"/>';
        $svg .= '<rect x="1" y="9" width="2" height="4" fill="#ff4a8d"/>';
        $svg .= '<rect x="9" y="9" width="2" height="4" fill="#ff4a8d"/>';
        $svg .= '<rect x="4" y="12" width="4" height="1" fill="#ff4a8d"/>';
        $svg .= '</g>';

        return $svg;
    };

    $frames = $frame(0, 3)
        . $frame(21, 4)
        . $frame(42, 2)
        . $frame(63, 4);

    return <<<SVG
<svg class="{$cls}" viewBox="0 0 12 21" shape-rendering="crispEdges" focusable="false" role="presentation">
    <g class="px-gif-track">{$frames}</g>
</svg>
SVG;
}

/**
 * Astronot piksel dengan siklus jalan 4 frame.
 * Dipakai sebagai karakter yang digerakkan pemain di Cafeteria.
 */
function pixel_walk_astro(string $class = ''): string
{
    $cls = e(trim('px-gif px-gif--walk ' . $class));

    $frame = static function (int $y, int $leftY, int $leftH, int $rightY, int $rightH): string {
        $svg  = '<g transform="translate(0,' . $y . ')">';
        $svg .= '<rect x="6" y="0" width="1" height="1" fill="#ffcb45"/>';
        $svg .= '<rect x="3" y="1" width="6" height="5" fill="#dfe2f2"/>';
        $svg .= '<rect x="4" y="2" width="4" height="3" fill="#0a0e19"/>';
        $svg .= '<rect x="4" y="2" width="2" height="1" fill="#00f0ff"/>';
        $svg .= '<rect x="4" y="3" width="1" height="1" fill="#7df4ff"/>';
        $svg .= '<rect x="1" y="6" width="2" height="4" fill="#ff4a8d"/>';
        $svg .= '<rect x="3" y="6" width="6" height="5" fill="#dfe2f2"/>';
        $svg .= '<rect x="5" y="7" width="2" height="2" fill="#00f0ff"/>';
        $svg .= '<rect x="9" y="6" width="2" height="3" fill="#dfe2f2"/>';
        $svg .= '<rect x="9" y="9" width="2" height="1" fill="#b9cacb"/>';
        $svg .= '<rect x="3" y="' . $leftY . '" width="2" height="' . $leftH . '" fill="#dfe2f2"/>';
        $svg .= '<rect x="7" y="' . $rightY . '" width="2" height="' . $rightH . '" fill="#dfe2f2"/>';
        $svg .= '<rect x="3" y="13" width="2" height="1" fill="#ff4a8d"/>';
        $svg .= '<rect x="7" y="13" width="2" height="1" fill="#ff4a8d"/>';
        $svg .= '</g>';

        return $svg;
    };

    $frames = $frame(0, 11, 2, 11, 2)
        . $frame(14, 10, 3, 12, 1)
        . $frame(28, 11, 2, 11, 2)
        . $frame(42, 12, 1, 10, 3);

    return <<<SVG
<svg class="{$cls}" viewBox="0 0 12 14" shape-rendering="crispEdges" focusable="false" role="presentation">
    <g class="px-gif-track">{$frames}</g>
</svg>
SVG;
}

/** Koin piksel yang melayang di latar. */
function pixel_sprite_coin(string $class = ''): string
{
    $cls = $class !== '' ? ' class="' . e($class) . '"' : '';

    return <<<SVG
<svg{$cls} viewBox="0 0 8 8" shape-rendering="crispEdges" focusable="false" role="presentation">
    <rect x="2" y="1" width="4" height="1" fill="#ffcb45"/>
    <rect x="1" y="2" width="6" height="1" fill="#ffcb45"/>
    <rect x="1" y="3" width="6" height="2" fill="#ffcb45"/>
    <rect x="1" y="5" width="6" height="1" fill="#ffcb45"/>
    <rect x="2" y="6" width="4" height="1" fill="#ffcb45"/>
    <rect x="2" y="3" width="2" height="2" fill="#fff3c4"/>
    <rect x="5" y="3" width="1" height="2" fill="#f0a500"/>
</svg>
SVG;
}

/** Planet bercincin untuk layar hitung mundur. */
function pixel_sprite_planet(string $class = ''): string
{
    $cls = $class !== '' ? ' class="' . e($class) . '"' : '';

    return <<<SVG
<svg{$cls} viewBox="0 0 16 16" shape-rendering="crispEdges" focusable="false" role="presentation">
    <rect x="5" y="3" width="6" height="1" fill="#ff4a8d"/>
    <rect x="3" y="4" width="10" height="1" fill="#ff4a8d"/>
    <rect x="2" y="5" width="12" height="5" fill="#ff4a8d"/>
    <rect x="3" y="10" width="10" height="1" fill="#ff4a8d"/>
    <rect x="5" y="11" width="6" height="1" fill="#ff4a8d"/>
    <rect x="4" y="5" width="3" height="2" fill="#ffb1c4"/>
    <rect x="3" y="7" width="2" height="2" fill="#ffb1c4"/>
    <rect x="9" y="9" width="3" height="2" fill="#8f0044"/>
    <rect x="0" y="8" width="16" height="1" fill="#ffcb45"/>
    <rect x="1" y="9" width="14" height="1" fill="#ffcb45" opacity=".55"/>
</svg>
SVG;
}

/** Isi layar hitung mundur: bintang, planet, dan astronot. */
function pixel_countdown_scene(): void
{
    $stars = [];
    for ($i = 0; $i < 30; $i++) {
        $stars[] = [
            'x'  => mt_rand(0, 1000) / 10,
            'y'  => mt_rand(0, 1000) / 10,
            'sz' => mt_rand(1, 10) > 7 ? 3 : 2,
            'du' => mt_rand(12, 36) / 10,
            'de' => mt_rand(0, 30) / 10,
        ];
    }
    ?>
    <div class="cd-scene" aria-hidden="true">
        <?php foreach ($stars as $star): ?>
            <span class="cd-star"
                  style="left:<?= $star['x'] ?>%;top:<?= $star['y'] ?>%;width:<?= $star['sz'] ?>px;height:<?= $star['sz'] ?>px;animation-duration:<?= $star['du'] ?>s;animation-delay:-<?= $star['de'] ?>s"></span>
        <?php endforeach; ?>
        <span class="cd-comet"></span>
        <div class="cd-planet"><?= pixel_sprite_planet() ?></div>
        <div class="cd-astro"><?= pixel_gif_astro() ?></div>
        <div class="cd-coin cd-coin--1"><?= pixel_sprite_coin() ?></div>
        <div class="cd-coin cd-coin--2"><?= pixel_sprite_coin() ?></div>
    </div>
    <?php
}

/** Lapisan latar: bintang berkelip, bintang jatuh, dan bulan piksel. */
function pixel_decor_layer(): void
{
    $stars = [];
    for ($i = 0; $i < 54; $i++) {
        $big   = mt_rand(1, 10) > 8;
        $stars[] = [
            'x'  => mt_rand(0, 1000) / 10,
            'y'  => mt_rand(0, 1000) / 10,
            'sz' => $big ? 3 : 2,
            'du' => mt_rand(28, 76) / 10,
            'de' => mt_rand(0, 95) / 10,
        ];
    }
    ?>
    <div class="pixel-decor" aria-hidden="true">
        <div class="px-stars">
            <?php foreach ($stars as $star): ?>
                <span class="px-star"
                      style="left:<?= $star['x'] ?>%;top:<?= $star['y'] ?>%;width:<?= $star['sz'] ?>px;height:<?= $star['sz'] ?>px;animation-duration:<?= $star['du'] ?>s;animation-delay:-<?= $star['de'] ?>s"></span>
            <?php endforeach; ?>
        </div>

        <span class="px-shooting"></span>
        <span class="px-shooting px-shooting--2"></span>

        <div class="px-drift-rocket"><?= pixel_sprite_rocket() ?></div>

        <div class="px-coin px-coin--1"><?= pixel_sprite_coin() ?></div>
        <div class="px-coin px-coin--2"><?= pixel_sprite_coin() ?></div>
        <div class="px-coin px-coin--3"><?= pixel_sprite_coin() ?></div>

        <div class="px-moon">
            <svg viewBox="0 0 12 12" shape-rendering="crispEdges" focusable="false" role="presentation">
                <rect x="4" y="1" width="4" height="1" fill="#dfe2f2"/>
                <rect x="3" y="2" width="6" height="1" fill="#dfe2f2"/>
                <rect x="2" y="3" width="8" height="1" fill="#dfe2f2"/>
                <rect x="2" y="4" width="8" height="1" fill="#dfe2f2"/>
                <rect x="1" y="5" width="10" height="2" fill="#dfe2f2"/>
                <rect x="2" y="7" width="8" height="1" fill="#dfe2f2"/>
                <rect x="2" y="8" width="8" height="1" fill="#dfe2f2"/>
                <rect x="3" y="9" width="6" height="1" fill="#dfe2f2"/>
                <rect x="4" y="10" width="4" height="1" fill="#dfe2f2"/>
                <rect x="3" y="3" width="2" height="1" fill="#b9cacb"/>
                <rect x="6" y="4" width="2" height="2" fill="#b9cacb"/>
                <rect x="4" y="7" width="2" height="1" fill="#b9cacb"/>
                <rect x="7" y="8" width="1" height="1" fill="#b9cacb"/>
                <rect x="2" y="5" width="1" height="1" fill="#b9cacb"/>
            </svg>
        </div>
    </div>
    <?php
}
