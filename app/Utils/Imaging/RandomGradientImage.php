<?php
/** @noinspection NonSecureShuffleUsageInspection */
declare(strict_types=1);

namespace App\Utils\Imaging;

/**
 * Generates a random mesh-gradient PNG image
 *
 * Picks 2–3 colors, places them as radial blobs near image edges, and blends
 * with inverse-square-distance weighting to produce organic, smooth gradients
 * similar to those seen in modern design tools.
 */
class RandomGradientImage implements \Stringable
{
    private const int WORK_SIZE = 128;

    public function __construct(
        private readonly int   $width,
        private readonly int   $height,
        // Colors should be in hex format, e.g. #FF0000
        private readonly array $colors = []
    )
    {
    }

    public function __toString(): string
    {
        $image = $this->render();
        ob_start();
        imagepng($image);
        $data = (string)ob_get_clean();
        imagedestroy($image);

        return $data;
    }

    private function render(): \GdImage
    {
        $colors = $this->selectColors();
        $blobs = $this->placeBlobsFor($colors);

        $workW = self::WORK_SIZE;
        $workH = max(1, (int)round((self::WORK_SIZE * $this->height) / $this->width));
        $ar = $this->width / $this->height;

        $work = imagecreatetruecolor($workW, $workH);

        for ($y = 0; $y < $workH; $y++) {
            $ny = $y / $workH;
            for ($x = 0; $x < $workW; $x++) {
                [$r, $g, $b] = $this->blendAt($x / $workW, $ny, $blobs, $ar);
                imagesetpixel($work, $x, $y, ($r << 16) | ($g << 8) | $b);
            }
        }

        if ($workW === $this->width && $workH === $this->height) {
            return $work;
        }

        $final = imagescale($work, $this->width, $this->height, IMG_BICUBIC);
        imagedestroy($work);

        return $final ?: imagecreatetruecolor($this->width, $this->height);
    }

    /**
     * @return list<array{r: int, g: int, b: int}>
     */
    private function selectColors(): array
    {
        $hex = !empty($this->colors) ? $this->colors : ['#6366f1', '#8b5cf6'];
        $parsed = array_map($this->parseHex(...), $hex);

        if (count($parsed) > 3) {
            shuffle($parsed);
            $parsed = array_slice($parsed, 0, random_int(2, 3));
        }

        if (count($parsed) === 1) {
            $parsed[] = $parsed[0];
        }

        return array_map($this->varyColor(...), $parsed);
    }

    /**
     * @param list<array{r: int, g: int, b: int}> $colors
     * @return list<array{cx: float, cy: float, r: int, g: int, b: int}>
     */
    private function placeBlobsFor(array $colors): array
    {
        $positions = $this->randomEdgePositions(count($colors));

        // @phpstan-ignore arrayValues.list
        return array_values(array_map(
            static fn(array $color, array $pos): array => [...$color, 'cx' => $pos[0], 'cy' => $pos[1]],
            $colors,
            $positions
        ));
    }

    /**
     * Returns $n normalized [0..1] positions distributed around image edges.
     *
     * @return list<array{float, float}>
     */
    private function randomEdgePositions(int $n): array
    {
        $candidates = [
            [-0.15, -0.15],
            [1.15, -0.15],
            [-0.15, 1.15],
            [1.15, 1.15],
            [0.50, -0.25],
            [0.50, 1.25],
            [-0.25, 0.50],
            [1.25, 0.50],
        ];

        shuffle($candidates);

        // @phpstan-ignore arrayValues.list
        return array_values(array_map(
            static fn(array $p): array => [
                $p[0] + (random_int(-8, 8) / 100.0),
                $p[1] + (random_int(-8, 8) / 100.0),
            ],
            array_slice($candidates, 0, $n)
        ));
    }

    /**
     * Blends blob colors at normalized position ($nx, $ny) using inverse-
     * square-distance weighting. The aspect ratio scales the x-axis so that
     * each blob appears as a circle rather than an ellipse in the output image.
     *
     * @param list<array{cx: float, cy: float, r: int, g: int, b: int}> $blobs
     * @return array{int, int, int}
     */
    private function blendAt(float $nx, float $ny, array $blobs, float $ar): array
    {
        $sumW = 0.0;
        $tr = $tg = $tb = 0.0;

        foreach ($blobs as $blob) {
            $dx = ($nx - $blob['cx']) * $ar;
            $dy = $ny - $blob['cy'];
            $w = 1.0 / (($dx * $dx) + ($dy * $dy) + 1e-4);
            $sumW += $w;
            $tr += $w * $blob['r'];
            $tg += $w * $blob['g'];
            $tb += $w * $blob['b'];
        }

        return [
            (int)round($tr / $sumW),
            (int)round($tg / $sumW),
            (int)round($tb / $sumW),
        ];
    }

    /** @return array{r: int, g: int, b: int} */
    private function parseHex(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [
            'r' => (int)hexdec(substr($hex, 0, 2)),
            'g' => (int)hexdec(substr($hex, 2, 2)),
            'b' => (int)hexdec(substr($hex, 4, 2)),
        ];
    }

    /** @return array{r: int, g: int, b: int} */
    private function varyColor(array $rgb): array
    {
        [$h, $s, $l] = $this->rgbToHsl($rgb['r'], $rgb['g'], $rgb['b']);

        $h = fmod($h + (random_int(-8, 8) / 360.0) + 1.0, 1.0);
        $s = max(0.3, min(1.0, $s + (random_int(-8, 8) / 100.0)));
        $l = max(0.05, min(0.85, $l + (random_int(-10, 10) / 100.0)));

        return $this->hslToRgb($h, $s, $l);
    }

    /** @return array{float, float, float} */
    private function rgbToHsl(int $r, int $g, int $b): array
    {
        $rf = $r / 255.0;
        $gf = $g / 255.0;
        $bf = $b / 255.0;

        $max = max($rf, $gf, $bf);
        $min = min($rf, $gf, $bf);
        $l = ($max + $min) / 2.0;

        if ($max === $min) {
            return [0.0, 0.0, $l];
        }

        $d = $max - $min;
        $s = $l > 0.5
            ? $d / (2.0 - $max - $min)
            : $d / ($max + $min);

        $h = match (true) {
            $max === $rf => ((($gf - $bf) / $d) + ($gf < $bf ? 6.0 : 0.0)) / 6.0,
            $max === $gf => ((($bf - $rf) / $d) + 2.0) / 6.0,
            default => ((($rf - $gf) / $d) + 4.0) / 6.0,
        };

        return [$h, $s, $l];
    }

    /** @return array{r: int, g: int, b: int} */
    private function hslToRgb(float $h, float $s, float $l): array
    {
        if ($s === 0.0) {
            $v = (int)round($l * 255);
            return ['r' => $v, 'g' => $v, 'b' => $v];
        }

        $q = $l < 0.5 ? $l * (1.0 + $s) : $l + $s - ($l * $s);
        $p = (2.0 * $l) - $q;

        return [
            'r' => (int)round($this->hslChannel($p, $q, $h + (1.0 / 3.0)) * 255),
            'g' => (int)round($this->hslChannel($p, $q, $h) * 255),
            'b' => (int)round($this->hslChannel($p, $q, $h - (1.0 / 3.0)) * 255),
        ];
    }

    private function hslChannel(float $p, float $q, float $t): float
    {
        if ($t < 0.0) {
            $t += 1.0;
        }
        if ($t > 1.0) {
            $t -= 1.0;
        }
        if ($t < (1.0 / 6.0)) {
            return $p + (($q - $p) * 6.0 * $t);
        }
        if ($t < 0.5) {
            return $q;
        }
        if ($t < (2.0 / 3.0)) {
            return $p + ((($q - $p) * ((2.0 / 3.0) - $t)) * 6.0);
        }

        return $p;
    }
}
