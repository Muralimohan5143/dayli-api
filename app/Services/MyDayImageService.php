<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MyDayImageService
{
    /**
     * Root folder inside:
     *
     * storage/app/public/img/myday/
     */
    private const ROOT = 'img/myday';

    /**
     * Supported image extensions.
     */
    private const IMAGE_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'webp',
    ];

    /**
     * Return the greeting image according to the current time.
     *
     * Expected folders:
     *
     * storage/app/public/img/myday/greetings/morning/
     * storage/app/public/img/myday/greetings/afternoon/
     * storage/app/public/img/myday/greetings/evening/
     * storage/app/public/img/myday/greetings/night/
     */
    public function greetingImage(?Carbon $now = null): array
    {
        $now ??= Carbon::now('Asia/Kolkata');

        $period = $this->greetingPeriod($now->hour);

        $image = $this->dailyImage(
            folder: "greetings/{$period}",
            seed: "greeting|{$period}|{$now->toDateString()}",
        );

        return [
            'period' => $period,
            'image_path' => $image['path'],
            'image_url' => $image['url'],
        ];
    }

    /**
     * Return a background image for a My Day feed item.
     *
     * Examples:
     *
     * feedImage('gita')
     * feedImage('health')
     * feedImage('weather', 'cloudy')
     */
    public function feedImage(
        string $key,
        ?string $variant = null,
        ?string $seed = null,
    ): array {
        $key = $this->normalizeFolderName($key);

        if ($key === '') {
            return $this->emptyImage();
        }

        $today = Carbon::now('Asia/Kolkata')->toDateString();

        if ($key === 'weather') {
            return $this->weatherImage(
                variant: $variant,
                seed: $seed ?? "weather|{$variant}|{$today}",
            );
        }

        return $this->dailyImageRecursive(
            folder: $key,
            seed: $seed ?? "{$key}|{$today}",
        );
    }

    /**
     * Return a weather image based on the normalized condition.
     *
     * Expected folders:
     *
     * weather/clear/
     * weather/cloudy/
     * weather/rain/
     * weather/fog/
     * weather/thunderstorm/
     * weather/default/
     */
    public function weatherImage(
        ?string $variant = null,
        ?string $seed = null,
    ): array {
        $variant = $this->normalizeWeatherVariant($variant);

        $today = Carbon::now('Asia/Kolkata')->toDateString();
        $seed ??= "weather|{$variant}|{$today}";

        $image = $this->dailyImage(
            folder: "weather/{$variant}",
            seed: $seed,
        );

        if ($image['path'] !== null) {
            return $image;
        }

        // First fallback: weather/default
        $image = $this->dailyImage(
            folder: 'weather/default',
            seed: "weather|default|{$today}",
        );

        if ($image['path'] !== null) {
            return $image;
        }

        // Second fallback: files directly inside weather/
        return $this->dailyImage(
            folder: 'weather',
            seed: "weather|root|{$today}",
        );
    }

    /**
     * Return a stable image for a Like card.
     *
     * Unlike feed images, Like-card covers should not change every day.
     *
     * Expected folders:
     *
     * likes/weather/
     * likes/gita/
     * likes/health/
     * likes/news/
     * etc.
     */
    public function likeCoverImage(string $key): array
    {
        $key = $this->normalizeFolderName($key);

        if ($key === '') {
            return $this->emptyImage();
        }

        $image = $this->stableImage(
            folder: "likes/{$key}",
            seed: "like-cover|{$key}",
        );

        if ($image['path'] !== null) {
            return $image;
        }

        /*
         * Optional fallback:
         * use the category's normal feed-image folder.
         */
        return $this->stableImage(
            folder: $key,
            seed: "like-cover-fallback|{$key}",
        );
    }

    /**
     * Choose one image deterministically for the supplied seed.
     *
     * The same seed always chooses the same image unless the folder contents
     * change.
     */
    public function dailyImage(string $folder, string $seed): array
    {
        return $this->selectImage(
            folder: $folder,
            seed: $seed,
        );
    }

    public function dailyImageRecursive(string $folder, string $seed): array
    {
        return $this->selectImageRecursive(
            folder: $folder,
            seed: $seed,
        );
    }

    /**
     * Stable selection helper.
     *
     * This currently uses the same deterministic selection logic as
     * dailyImage(), but the caller provides a seed without the date.
     */
    public function stableImage(string $folder, string $seed): array
    {
        return $this->selectImage(
            folder: $folder,
            seed: $seed,
        );
    }

    /**
     * Select an image from a folder using a deterministic hash.
     */
    private function selectImage(string $folder, string $seed): array
    {
        try {
            $folder = trim($folder, '/');

            if ($folder === '') {
                return $this->emptyImage();
            }

            $storageFolder = self::ROOT . '/' . $folder;

            if (!Storage::disk('public')->exists($storageFolder)) {
                return $this->emptyImage();
            }

            $files = collect(
                Storage::disk('public')->files($storageFolder)
            )
                ->filter(fn(string $file) => $this->isSupportedImage($file))
                ->sort()
                ->values();

            if ($files->isEmpty()) {
                return $this->emptyImage();
            }

            $index = $this->indexFromSeed(
                seed: $seed,
                count: $files->count(),
            );

            $storagePath = $files->get($index);

            if (!is_string($storagePath) || $storagePath === '') {
                return $this->emptyImage();
            }

            /*
             * Remove "img/myday/" from the stored path.
             *
             * Returned image_path example:
             *
             * greetings/morning/abc123.jpg
             */
            $relativePath = $this->relativePath($storagePath);

            return [
                'path' => $relativePath,
                'url' => Storage::disk('public')->url($storagePath),
            ];
        } catch (Throwable $e) {
            report($e);

            return $this->emptyImage();
        }
    }

    private function selectImageRecursive(string $folder, string $seed): array
    {
        try {
            $folder = trim($folder, '/');

            if ($folder === '') {
                return $this->emptyImage();
            }

            $storageFolder = self::ROOT . '/' . $folder;

            if (!Storage::disk('public')->exists($storageFolder)) {
                return $this->emptyImage();
            }

            $files = collect(
                Storage::disk('public')->allFiles($storageFolder)
            )
                ->filter(fn(string $file) => $this->isSupportedImage($file))
                ->sort()
                ->values();

            if ($files->isEmpty()) {
                return $this->emptyImage();
            }

            $index = $this->indexFromSeed(
                seed: $seed,
                count: $files->count(),
            );

            $storagePath = $files->get($index);

            if (!is_string($storagePath) || $storagePath === '') {
                return $this->emptyImage();
            }

            return [
                'path' => $this->relativePath($storagePath),
                'url' => Storage::disk('public')->url($storagePath),
            ];
        } catch (Throwable $e) {
            report($e);

            return $this->emptyImage();
        }
    }

    /**
     * Convert a seed into an image-list index.
     */
    private function indexFromSeed(string $seed, int $count): int
    {
        if ($count <= 1) {
            return 0;
        }

        $hash = hash('sha256', $seed);

        /*
         * Use the first 8 hexadecimal characters.
         * This remains safely manageable as an integer.
         */
        $number = hexdec(substr($hash, 0, 8));

        return $number % $count;
    }

    /**
     * Determine greeting period from hour.
     *
     * 05:00–11:59 => morning
     * 12:00–15:59 => afternoon
     * 16:00–18:59 => evening
     * 19:00–04:59 => night
     */
    private function greetingPeriod(int $hour): string
    {
        if ($hour >= 5 && $hour < 12) {
            return 'morning';
        }

        if ($hour >= 12 && $hour < 16) {
            return 'afternoon';
        }

        if ($hour >= 16 && $hour < 19) {
            return 'evening';
        }

        return 'night';
    }

    /**
     * Normalize weather conditions into supported folder names.
     */
    public function normalizeWeatherVariant(?string $condition): string
    {
        $condition = strtolower(trim((string) $condition));

        if ($condition === '') {
            return 'default';
        }

        if (
            str_contains($condition, 'clear') ||
            str_contains($condition, 'sunny')
        ) {
            return 'clear';
        }

        if (
            str_contains($condition, 'cloud') ||
            str_contains($condition, 'overcast')
        ) {
            return 'cloudy';
        }

        if (
            str_contains($condition, 'thunder') ||
            str_contains($condition, 'storm')
        ) {
            return 'thunderstorm';
        }

        if (
            str_contains($condition, 'rain') ||
            str_contains($condition, 'drizzle') ||
            str_contains($condition, 'shower')
        ) {
            return 'rain';
        }

        if (
            str_contains($condition, 'fog') ||
            str_contains($condition, 'mist') ||
            str_contains($condition, 'haze')
        ) {
            return 'fog';
        }

        return 'default';
    }

    /**
     * Sanitize a feed key or folder name.
     */
    private function normalizeFolderName(string $value): string
    {
        $value = strtolower(trim($value));

        $value = preg_replace(
            '/[^a-z0-9_-]+/',
            '_',
            $value,
        );

        return trim((string) $value, '_-');
    }

    /**
     * Check supported image extension.
     */
    private function isSupportedImage(string $file): bool
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        return in_array(
            $extension,
            self::IMAGE_EXTENSIONS,
            true,
        );
    }

    /**
     * Convert full public-disk path into path relative to img/myday.
     */
    private function relativePath(string $storagePath): string
    {
        $prefix = self::ROOT . '/';

        if (str_starts_with($storagePath, $prefix)) {
            return substr($storagePath, strlen($prefix));
        }

        return $storagePath;
    }

    /**
     * Standard empty result.
     */
    private function emptyImage(): array
    {
        return [
            'path' => null,
            'url' => null,
        ];
    }
}
