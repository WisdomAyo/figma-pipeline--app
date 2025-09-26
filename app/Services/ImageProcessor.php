<?php

namespace App\Services;

use App\Enums\ImageFormatEnum;
use App\Http\Data\ProcessImageData;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Str;

/**
 * ImageProcessor Service
 *
 * This service handles the complete image optimization pipeline for Figma designs.
 * It fetches screenshots from the Figma API, optimizes them by resizing and converting
 * to WebP format, and returns a Base64-encoded version for AI processing.
 */
class ImageProcessor
{
    /**
     * Process an image from Figma by fetching, optimizing, and converting to Base64
     *
     * @param ProcessImageData $data
     * @return array
     * @throws Exception
     */
    public function processImage(ProcessImageData $data): array
    {
        try {
            // Step 1: Fetch image URL from Figma API
            $imageUrl = $this->fetchImageUrlFromFigma($data->fileKey, $data->nodeId);

            // Step 2: Download the image
            $originalImagePath = $this->downloadImage($imageUrl, $data->fileKey, $data->nodeId);

            // Step 3: Get original file size
            $originalSize = $this->getFileSizeInKb($originalImagePath);

            // Step 4: Optimize the image
            $optimizedImagePath = $this->optimizeImage($originalImagePath, $data->fileKey, $data->nodeId);

            // Step 5: Get optimized file size
            $optimizedSize = $this->getFileSizeInKb($optimizedImagePath);

            // Step 6: Convert to Base64
            $base64String = $this->convertToBase64($optimizedImagePath);

            // Step 7: Clean up temporary files
            $this->cleanupTempFiles([$originalImagePath, $optimizedImagePath]);

            // Step 8: Return response data
            return [
                'original_size'   => round($originalSize, 2),
                'optimized_size'  => round($optimizedSize, 2),
                'compression_ratio' => round((($originalSize - $optimizedSize) / $originalSize) * 100, 2) . '%',
                'base64_preview'  => substr($base64String, 0, 100),
                'base64_full'     => $base64String
            ];

        } catch (Exception $e) {
            Log::error('Image processing failed', [
                'message'  => $e->getMessage(),
                'file_key' => $data->fileKey,
                'node_id'  => $data->nodeId,
                'line'     => $e->getLine(),
                'trace'    => $e->getTrace(),
                'timestamp' => now()
            ]);

            throw $e;
        }
    }

    /**
     * Fetch image URL from Figma API
     *
     * @param string $fileKey
     * @param string $nodeId
     * @return string
     * @throws Exception
     */
    private function fetchImageUrlFromFigma(string $fileKey, string $nodeId): string
    {
        $url = config('figma.api.base_url') . "/images/{$fileKey}";

        $response = Http::withHeaders([
            'X-Figma-Token' => config('figma.api.token')
        ])
        ->timeout(config('figma.api.timeout'))
        ->get($url, [
            'ids'    => $nodeId,
            'format' => 'png',
            'scale'  => 2
        ]);

        if (!$response->successful()) {
            throw new Exception("Failed to fetch image from Figma API: " . $response->body());
        }

        $data = $response->json();

        if (empty($data['images'][$nodeId])) {
            throw new Exception("No image found for node ID: {$nodeId}");
        }

        return $data['images'][$nodeId];
    }

    /**
     * Download image from URL
     *
     * @param string $imageUrl
     * @param string $fileKey
     * @param string $nodeId
     * @return string
     * @throws Exception
     */
    private function downloadImage(string $imageUrl, string $fileKey, string $nodeId): string
    {
        $response = Http::timeout(30)->get($imageUrl);

        if (!$response->successful()) {
            throw new Exception("Failed to download image from URL: {$imageUrl}");
        }

        // Sanitize filename for Windows compatibility (replace invalid characters)
        $safeNodeId = $this->sanitizeFilename($nodeId);
        $safeFileKey = $this->sanitizeFilename($fileKey);

        $fileName = "{$safeFileKey}_{$safeNodeId}_original_" . Str::random(10) . ".png";
        $tempPath = config('figma.image.temp_directory') . '/' . $fileName;

        Storage::disk('local')->put($tempPath, $response->body());

        return Storage::disk('local')->path($tempPath);
    }

    /**
     * Optimize image by resizing and converting to WebP
     *
     * @param string $originalPath
     * @param string $fileKey
     * @param string $nodeId
     * @return string
     */
    private function optimizeImage(string $originalPath, string $fileKey, string $nodeId): string
    {
        $maxWidth = config('figma.image.max_width');
        $quality = config('figma.image.webp_quality');

        $image = Image::make($originalPath);

        // Resize if width exceeds maximum, maintaining aspect ratio
        if ($image->width() > $maxWidth) {
            $image->resize($maxWidth, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }

        // Sanitize filename for Windows compatibility
        $safeNodeId = $this->sanitizeFilename($nodeId);
        $safeFileKey = $this->sanitizeFilename($fileKey);

        // Generate output filename
        $fileName = "{$safeFileKey}_{$safeNodeId}_optimized_" . Str::random(10) . ".webp";
        $tempPath = config('figma.image.temp_directory') . '/' . $fileName;
        $fullPath = Storage::disk('local')->path($tempPath);

        // Save as WebP with specified quality
        $image->encode(ImageFormatEnum::WEBP->value, $quality)->save($fullPath);

        return $fullPath;
    }

    /**
     * Convert image to Base64 string
     *
     * @param string $imagePath
     * @return string
     */
    private function convertToBase64(string $imagePath): string
    {
        $imageData = file_get_contents($imagePath);
        $mimeType = mime_content_type($imagePath);

        return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
    }

    /**
     * Get file size in KB
     *
     * @param string $filePath
     * @return float
     */
    private function getFileSizeInKb(string $filePath): float
    {
        return filesize($filePath) / 1024;
    }

    /**
     * Clean up temporary files
     *
     * @param array $filePaths
     * @return void
     */
    private function cleanupTempFiles(array $filePaths): void
    {
        foreach ($filePaths as $filePath) {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }

    /**
     * Sanitize filename for Windows compatibility
     *
     * @param string $filename
     * @return string
     */
    private function sanitizeFilename(string $filename): string
    {
        // Replace invalid Windows filename characters
        $invalidChars = ['<', '>', ':', '"', '/', '\\', '|', '?', '*'];
        $replacementChar = '-';

        return str_replace($invalidChars, $replacementChar, $filename);
    }
}
