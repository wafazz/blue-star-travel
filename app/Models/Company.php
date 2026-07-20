<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $guarded = [];

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], ['name' => 'Blue Star Travel And Tours Sdn Bhd']);
    }

    /**
     * Absolute path to the logo for PDF rendering — DomPDF needs a filesystem path,
     * not a URL. DomPDF decodes PNG alpha into memory uncompressed, so a large
     * transparent upload (a 3200x3200 logo needs ~40MB) kills every PDF in the app.
     * We therefore render from a small flattened JPEG, built once per upload and
     * cached beside the original. Falls back to the bundled Blue Star logo.
     */
    public function logoPath(): string
    {
        $source = $this->logo ? storage_path('app/public/' . $this->logo) : null;
        $fallback = public_path('images/logo-print.jpg');

        if (! $source || ! file_exists($source)) {
            return $fallback;
        }

        $cache = storage_path('app/public/company/print/' . pathinfo($this->logo, PATHINFO_FILENAME) . '.jpg');
        if (file_exists($cache) && filemtime($cache) >= filemtime($source)) {
            return $cache;
        }

        return $this->buildPrintLogo($source, $cache) ? $cache : $fallback;
    }

    /** Downscale + flatten onto white so DomPDF can embed it cheaply. */
    private function buildPrintLogo(string $source, string $cache): bool
    {
        if (! function_exists('imagecreatefromstring')) {
            return false;
        }

        // Decoding is the expensive step (width * height * 4 bytes — a 3200px logo needs
        // ~41MB), so lift the cap just for this one-off conversion and restore it after.
        $previousLimit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        $image = @imagecreatefromstring((string) file_get_contents($source));
        if (! $image) {
            ini_set('memory_limit', $previousLimit);

            return false;
        }

        $max = 420;
        $w = imagesx($image);
        $h = imagesy($image);
        $scale = min(1, $max / max($w, $h));
        $tw = max(1, (int) round($w * $scale));
        $th = max(1, (int) round($h * $scale));

        $canvas = imagecreatetruecolor($tw, $th);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $tw, $th, $w, $h);

        @mkdir(dirname($cache), 0775, true);
        $ok = imagejpeg($canvas, $cache, 90);

        unset($image, $canvas);
        ini_set('memory_limit', $previousLimit);

        return (bool) $ok;
    }
}
