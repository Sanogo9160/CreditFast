<?php

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Process;
use Imagick;
use ImagickException;
use Throwable;

class PdfPageRasterizer
{
    /**
     * Rasterize the first pages of a PDF to PNG files (deleted by the caller).
     * Ghostscript is the PDF delegate used by Imagick on Windows; when it is
     * available we call it directly with a timeout so OCR never hangs.
     *
     * @return list<string>
     */
    public function rasterize(string $pdfPath, int $maxPages): array
    {
        $maxPages = max(1, $maxPages);
        $this->prepareEnvironment();

        $ghostscript = $this->ghostscriptBinary();
        if ($ghostscript !== null) {
            $pages = $this->rasterizeWithGhostscript($ghostscript, $pdfPath, $maxPages);
            if ($pages !== []) {
                return $pages;
            }

            return $this->rasterizeWithImagick($pdfPath, $maxPages);
        }

        return [];
    }

    public function imagickIsAvailable(): bool
    {
        return extension_loaded('imagick') && class_exists(Imagick::class);
    }

    /**
     * @return list<string>
     */
    protected function rasterizeWithGhostscript(string $binary, string $pdfPath, int $maxPages): array
    {
        $prefix = sys_get_temp_dir().DIRECTORY_SEPARATOR.'creditfast-ocr-'.uniqid('', true);
        $outputPattern = $prefix.'-%d.png';
        $density = (int) config('ocr.pdf_density', 200);

        $result = Process::timeout((int) config('ocr.timeout', 60))->run([
            $binary,
            '-dSAFER',
            '-dBATCH',
            '-dNOPAUSE',
            '-dQUIET',
            '-sDEVICE=png16m',
            '-r'.$density,
            '-dFirstPage=1',
            '-dLastPage='.$maxPages,
            '-sOutputFile='.$outputPattern,
            $pdfPath,
        ]);

        if (! $result->successful()) {
            return [];
        }

        $paths = [];
        for ($page = 1; $page <= $maxPages; $page++) {
            $candidate = $prefix.'-'.$page.'.png';
            if (is_file($candidate)) {
                $paths[] = $candidate;
            }
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    protected function rasterizeWithImagick(string $pdfPath, int $maxPages): array
    {
        if (! $this->imagickIsAvailable()) {
            return [];
        }

        $paths = [];

        try {
            $image = new Imagick;
            $density = (int) config('ocr.pdf_density', 200);
            $image->setResolution($density, $density);
            $lastIndex = max(0, $maxPages - 1);
            $image->readImage($pdfPath.'[0-'.$lastIndex.']');
            $image->setImageFormat('png');

            $index = 0;
            foreach ($image as $page) {
                if ($index >= $maxPages) {
                    break;
                }

                $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'creditfast-ocr-'.uniqid('', true).'-'.$index.'.png';
                if (defined(Imagick::class.'::ALPHACHANNEL_REMOVE')) {
                    $page->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
                }
                $page->writeImage($tempPath);
                $paths[] = $tempPath;
                $index++;
            }

            $image->clear();
            $image->destroy();
        } catch (ImagickException|Throwable) {
            foreach ($paths as $path) {
                @unlink($path);
            }

            return [];
        }

        return $paths;
    }

    protected function prepareEnvironment(): void
    {
        $configurePath = $this->imagickConfigurePath();
        if ($configurePath !== null) {
            putenv('MAGICK_CONFIGURE_PATH='.$configurePath);
        }

        $ghostscript = $this->ghostscriptBinary();
        if ($ghostscript !== null) {
            $directory = dirname($ghostscript);
            $path = getenv('PATH') ?: '';
            if ($directory !== '' && ! str_contains(strtolower($path), strtolower($directory))) {
                putenv('PATH='.$directory.PATH_SEPARATOR.$path);
            }
        }
    }

    protected function imagickConfigurePath(): ?string
    {
        $configured = config('ocr.imagick.configure_path');
        if (is_string($configured) && $configured !== '' && is_dir($configured)) {
            return $configured;
        }

        $besidePhp = dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'imagick-config';
        if (is_dir($besidePhp)) {
            return $besidePhp;
        }

        return null;
    }

    protected function ghostscriptBinary(): ?string
    {
        $configured = config('ocr.ghostscript.binary');
        if (is_string($configured) && $configured !== '') {
            return is_file($configured) ? $configured : null;
        }

        $root = 'C:\\Program Files\\gs';
        if (is_dir($root)) {
            $matches = glob($root.'\\*\\bin\\gswin64c.exe') ?: [];
            if ($matches !== []) {
                rsort($matches);

                return $matches[0];
            }
        }

        return null;
    }
}
