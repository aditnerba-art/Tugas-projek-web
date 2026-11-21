<?php

namespace App\Services;

use GdImage;
use RuntimeException;

class ServicePhotoImageProcessor
{
    /**
     * @return array{original: string, thumbnail: string}
     */
    public function process(string $contents): array
    {
        $source = $this->decode($contents);
        $flattened = null;
        $thumbnail = null;

        try {
            $flattened = $this->flatten($source);
            $width = imagesx($flattened);
            $height = imagesy($flattened);
            $scale = min(1, 640 / max($width, $height));
            $thumbnailWidth = max(1, (int) round($width * $scale));
            $thumbnailHeight = max(1, (int) round($height * $scale));
            $thumbnail = imagecreatetruecolor($thumbnailWidth, $thumbnailHeight);
            if ($thumbnail === false) {
                throw new RuntimeException('Thumbnail foto gagal dibuat.');
            }

            $white = imagecolorallocate($thumbnail, 255, 255, 255);
            imagefill($thumbnail, 0, 0, $white);
            if (! imagecopyresampled(
                $thumbnail,
                $flattened,
                0,
                0,
                0,
                0,
                $thumbnailWidth,
                $thumbnailHeight,
                $width,
                $height,
            )) {
                throw new RuntimeException('Thumbnail foto gagal diproses.');
            }

            return [
                'original' => $this->encodeJpeg($flattened, 90),
                'thumbnail' => $this->encodeJpeg($thumbnail, 82),
            ];
        } finally {
            if ($thumbnail instanceof GdImage) {
                imagedestroy($thumbnail);
            }
            if ($flattened instanceof GdImage) {
                imagedestroy($flattened);
            }
            imagedestroy($source);
        }
    }

    public function reencode(string $contents, int $quality): string
    {
        $source = $this->decode($contents);
        $flattened = null;

        try {
            $flattened = $this->flatten($source);

            return $this->encodeJpeg($flattened, $quality);
        } finally {
            if ($flattened instanceof GdImage) {
                imagedestroy($flattened);
            }
            imagedestroy($source);
        }
    }

    private function decode(string $contents): GdImage
    {
        $image = @imagecreatefromstring($contents);
        if (! $image instanceof GdImage) {
            throw new RuntimeException('File foto tidak dapat dibaca sebagai gambar yang valid.');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 100 || $height < 100 || $width > 3000 || $height > 3000) {
            imagedestroy($image);
            throw new RuntimeException('Dimensi foto berada di luar batas yang diizinkan.');
        }

        return $image;
    }

    private function flatten(GdImage $source): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            throw new RuntimeException('Foto tidak dapat diproses dengan aman.');
        }

        imagealphablending($image, true);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $white);
        if (! imagecopy($image, $source, 0, 0, 0, 0, $width, $height)) {
            imagedestroy($image);
            throw new RuntimeException('Foto tidak dapat diproses dengan aman.');
        }

        return $image;
    }

    private function encodeJpeg(GdImage $image, int $quality): string
    {
        ob_start();
        $encoded = imagejpeg($image, null, $quality);
        $contents = ob_get_clean();

        if (! $encoded || ! is_string($contents) || $contents === '') {
            throw new RuntimeException('Foto tidak dapat diproses dengan aman.');
        }

        return $contents;
    }
}
