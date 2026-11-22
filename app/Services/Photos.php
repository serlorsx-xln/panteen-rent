<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Upload;

/**
 * Condition photos of a rental: before it leaves, when the customer opens the parcel, the post-office receipt
 * of the return parcel, at the return inspection and with a dispute. Kept outside the web folder; only the
 * booking's customer and staff can open them (/condition/{id}).
 */
final class Photos
{
    public const MAX_PER_UPLOAD = 6;

    /**
     * The files of a multiple file input, one $_FILES-style entry each (empty slots dropped).
     * @return list<array<string, mixed>>
     */
    public static function fromRequest(string $field): array
    {
        $f = $_FILES[$field] ?? null;
        if (!is_array($f) || !isset($f['name'])) {
            return [];
        }
        if (!is_array($f['name'])) {
            return ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$f];
        }
        $out = [];
        foreach (array_keys($f['name']) as $i) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = ['name' => $f['name'][$i], 'type' => $f['type'][$i] ?? '', 'tmp_name' => $f['tmp_name'][$i],
                'error' => $f['error'][$i], 'size' => $f['size'][$i] ?? 0];
        }
        return $out;
    }

    /**
     * Problem with a set of files before anything is saved, or null.
     * @param list<array<string, mixed>> $files
     */
    public static function tooMany(array $files): ?string
    {
        return count($files) > self::MAX_PER_UPLOAD ? 'แนบรูปได้ครั้งละไม่เกิน ' . self::MAX_PER_UPLOAD . ' รูป' : null;
    }

    /**
     * Save photos for a booking. Bad files are skipped and reported; good ones are kept.
     * @param list<array<string, mixed>> $files
     * @return array{saved: int, errors: list<string>}
     */
    public static function store(int $bookingId, string $stage, array $files, ?int $actorId): array
    {
        if (!isset(Status::PHOTO_STAGE[$stage])) {
            throw new \InvalidArgumentException('Unknown photo stage');
        }
        $saved = 0;
        $errors = [];
        foreach (array_slice($files, 0, self::MAX_PER_UPLOAD) as $file) {
            $up = Upload::image($file, STORAGE_PATH . '/condition', 1600);
            if (!$up['ok']) {
                $errors[] = ($file['name'] ?? 'รูป') . ': ' . $up['error'];
                continue;
            }
            DB::insert('booking_photos', ['booking_id' => $bookingId, 'stage' => $stage, 'path' => $up['path'], 'created_by' => $actorId]);
            $saved++;
        }
        return ['saved' => $saved, 'errors' => $errors];
    }

    /** @return array<string, list<array<string, mixed>>> stage => photos, in the order of the rental */
    public static function forBooking(int $bookingId): array
    {
        $out = [];
        foreach (DB::all('SELECT * FROM booking_photos WHERE booking_id = ? ORDER BY id', [$bookingId]) as $p) {
            $out[$p['stage']][] = $p;
        }
        return array_replace(array_intersect_key(array_fill_keys(array_keys(Status::PHOTO_STAGE), []), $out), $out);
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return DB::one('SELECT p.*, b.user_id FROM booking_photos p JOIN bookings b ON b.id = p.booking_id WHERE p.id = ?', [$id]);
    }
}
