<?php

// GÃ©nÃ¨re les icÃ´nes PWA : cÅ“ur rouge avec "Dâ™¥J"
function makeIcon(int $size, string $path): void
{
    $img = imagecreatetruecolor($size, $size);
    imagesavealpha($img, true);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);

    // Fond arrondi sombre
    $bg = imagecolorallocate($img, 21, 21, 26); // #15151A
    $radius = (int) ($size * 0.22);
    imagefilledrectangle($img, 0, 0, $size, $size, $bg);
    // arrondir les coins en dÃ©coupant (approximation simple par rÃ©duction)
    // Dessiner un lÃ©ger contour
    $glow = imagecolorallocatealpha($img, 230, 57, 70, 30);
    for ($i = 0; $i < (int) ($size * 0.06); $i++) {
        imagesetthickness($img, $i);
        imagerectangle($img, $i, $i, $size - 1 - $i, $size - 1 - $i, $glow);
    }

    // CÅ“ur
    $cx = $size / 2;
    $cy = $size / 2;
    $w = $size * 0.55;
    $h = $size * 0.52;
    $red = imagecolorallocate($img, 230, 57, 70);
    $redLight = imagecolorallocate($img, 255, 107, 107);

    // Points de contrÃ´le du cÅ“ur via la formule paramÃ©trique
    for ($t = 0; $t <= 360; $t += 0.2) {
        $rad = deg2rad($t);
        $px = $cx + ($w * 0.48) * 16 * pow(sin($rad), 3);
        $py = $cy - $h * (13 * cos($rad) - 5 * cos(2 * $rad) - 2 * cos(3 * $rad) - cos(4 * $rad)) / 16;
        imagefilledellipse($img, (int) $px, (int) $py, 3, 3, $red);
    }

    // Reflet
    for ($t = 0; $t <= 360; $t += 0.4) {
        $rad = deg2rad($t);
        $px = $cx + ($w * 0.40) * 16 * pow(sin($rad), 3) - ($size * 0.06);
        $py = $cy - $h * (13 * cos($rad) - 5 * cos(2 * $rad) - 2 * cos(3 * $rad) - cos(4 * $rad)) / 16 - ($size * 0.10);
        if ($px > $cx) {
            imagefilledellipse($img, (int) $px, (int) $py, 2, 2, $redLight);
        }
    }

    // Texte blanc "DJ"
    putenv('GDFONTPATH='.__DIR__.'/fonts');
    $font = null;
    $candidates = [
        'C:/Windows/Fonts/arialbd.ttf',
        'C:/Windows/Fonts/segoeuib.ttf',
        'C:/Windows/Fonts/arial.ttf',
    ];
    foreach ($candidates as $c) {
        if (file_exists($c)) {
            $font = $c;
            break;
        }
    }

    $white = imagecolorallocate($img, 255, 255, 255);
    if ($font) {
        $txt = 'DJ';
        $fs = (int) ($size * 0.30);
        $box = imagettfbbox($fs, 0, $font, $txt);
        $tw = abs($box[2] - $box[0]);
        $th = abs($box[5] - $box[1]);
        $tx = $cx - $tw / 2;
        $ty = $cy + $th / 2 - $size * 0.02;
        imagettftext($img, $fs, 0, (int) $tx, (int) $ty, $white, $font, $txt);
    }

    imagepng($img, $path);
    imagedestroy($img);
    echo 'created '.$path.PHP_EOL;
}

makeIcon(192, dirname(__DIR__).'/public/icons/icon-192.png');
makeIcon(512, dirname(__DIR__).'/public/icons/icon-512.png');
makeIcon(96, dirname(__DIR__).'/public/icons/icon-96.png');
