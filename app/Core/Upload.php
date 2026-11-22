<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Image upload handling. Files are checked by their real content (finfo + getimagesize),
 * re-encoded through GD when available (strips metadata and anything hidden after the image),
 * and stored with a random name.
 */
final class Upload
{
    public const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    public const MAX_PIXELS = 40_000_000;

    /**
     * @param array<string, mixed>|null $file one entry of $_FILES
     * @param array<string, int> $copies smaller copies to write beside the file, by folder: ['thumb' => 900, 'mini' => 320]
     * @return array{ok:bool, error?:string, path?:string}
     */
    public static function image(?array $file, string $dir, int $maxSide = 1800, array $copies = []): array
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'กรุณาเลือกไฟล์รูปภาพ'];
        }
        $max = (int) Config::get('uploads.max_bytes', 5 * 1024 * 1024);
        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($file['size'] ?? 0) > $max) {
            return ['ok' => false, 'error' => 'ไฟล์ใหญ่เกินไป (สูงสุด ' . round($max / 1048576) . ' MB) ลองถ่ายภาพหน้าจอสลิปแทนไฟล์ต้นฉบับ'];
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'อัปโหลดไม่สำเร็จ กรุณาลองใหม่อีกครั้ง'];
        }
        $tmp = $file['tmp_name'];
        if (!is_uploaded_file($tmp) && !defined('PANTEEN_TESTING')) {
            return ['ok' => false, 'error' => 'ไฟล์ไม่ถูกต้อง'];
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
        $dims = @getimagesize($tmp);
        if (!isset(self::IMAGE_TYPES[$mime]) || $dims === false) {
            return ['ok' => false, 'error' => 'รองรับเฉพาะไฟล์รูป JPG, PNG หรือ WEBP'];
        }
        // a small file can declare a huge canvas; decoding it would exhaust memory
        if ($dims[0] * $dims[1] > self::MAX_PIXELS) {
            return ['ok' => false, 'error' => 'รูปใหญ่เกินไป (เกิน ' . (self::MAX_PIXELS / 1000000) . ' ล้านพิกเซล) ลองย่อรูปหรือถ่ายภาพหน้าจอแทน'];
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $ext = self::IMAGE_TYPES[$mime];
        $name = date('Ymd') . '-' . bin2hex(random_bytes(10)) . '.' . $ext;
        $dest = rtrim($dir, '/') . '/' . $name;

        if (!self::reencode($tmp, $dest, $mime, $maxSide)) {
            if (!@copy($tmp, $dest)) {
                return ['ok' => false, 'error' => 'บันทึกไฟล์ไม่สำเร็จ'];
            }
        }
        foreach ($copies as $folder => $side) {
            if (!is_dir($dir . '/' . $folder)) {
                mkdir($dir . '/' . $folder, 0775, true);
            }
            self::reencode($dest, $dir . '/' . $folder . '/' . $name, $mime, $side);
        }
        return ['ok' => true, 'path' => $name];
    }

    private static function reencode(string $src, string $dest, string $mime, int $maxSide): bool
    {
        if (!function_exists('imagecreatefromjpeg')) {
            return false;
        }
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($src),
            'image/png'  => @imagecreatefrompng($src),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
            default      => false,
        };
        if (!$img) {
            return false;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, $maxSide / max($w, $h));
        if ($scale < 1) {
            $nw = (int) round($w * $scale);
            $nh = (int) round($h * $scale);
            $res = imagecreatetruecolor($nw, $nh);
            imagealphablending($res, false);
            imagesavealpha($res, true);
            imagecopyresampled($res, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);
            $img = $res;
        }
        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($img, $dest, 85),
            'image/png'  => imagepng($img, $dest, 6),
            'image/webp' => imagewebp($img, $dest, 85),
        };
        imagedestroy($img);
        return $ok;
    }
}
