<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    public function __construct()
    {
        if (class_exists('Cloudinary')) {
            \Cloudinary::config([
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME', 'a25figdq'),
                'api_key'    => env('CLOUDINARY_API_KEY', '486489222592878'),
                'api_secret' => env('CLOUDINARY_API_SECRET', 'tfP-acwImBL5HN1AJSdkHV0bqc0'),
                'secure'     => true,
            ]);
        }
    }

    /**
     * Upload an uploaded file (Image or PDF) to Cloudinary.
     *
     * @param UploadedFile|string $file
     * @param string $folder Subfolder name under keysclub/
     * @return string Secure HTTPS URL of the uploaded asset
     */
    public function upload($file, string $folder = 'uploads'): string
    {
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        try {
            if (!class_exists('Cloudinary') || !class_exists('Cloudinary\Uploader')) {
                // Direct HTTPS upload via Laravel Http Client if SDK autoload issue
                return $this->directHttpUpload($file, $folder);
            }

            \Cloudinary::config([
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME', 'a25figdq'),
                'api_key'    => env('CLOUDINARY_API_KEY', '486489222592878'),
                'api_secret' => env('CLOUDINARY_API_SECRET', 'tfP-acwImBL5HN1AJSdkHV0bqc0'),
                'secure'     => true,
            ]);

            $response = \Cloudinary\Uploader::upload($filePath, [
                'folder' => 'keysclub/' . $folder,
                'resource_type' => 'auto',
            ]);

            if (isset($response['secure_url'])) {
                return $response['secure_url'];
            }

            throw new \RuntimeException('Cloudinary did not return a secure URL.');
        } catch (\Exception $e) {
            Log::error('Cloudinary Upload SDK Error, attempting HTTP fallback: ' . $e->getMessage());
            return $this->directHttpUpload($file, $folder);
        }
    }

    /**
     * Fallback Direct Cloudinary Upload via HTTP API
     */
    protected function directHttpUpload($file, string $folder): string
    {
        $cloudName = env('CLOUDINARY_CLOUD_NAME', 'a25figdq');
        $apiKey = env('CLOUDINARY_API_KEY', '486489222592878');
        $apiSecret = env('CLOUDINARY_API_SECRET', 'tfP-acwImBL5HN1AJSdkHV0bqc0');

        $timestamp = time();
        $folderPath = 'keysclub/' . $folder;

        // Signature params must be sorted alphabetically
        $paramsToSign = "folder={$folderPath}&timestamp={$timestamp}";
        $signature = sha1($paramsToSign . $apiSecret);

        $response = \Illuminate\Support\Facades\Http::asMultipart()->post(
            "https://api.cloudinary.com/v1_1/{$cloudName}/auto/upload",
            [
                [
                    'name' => 'file',
                    'contents' => $file instanceof UploadedFile ? fopen($file->getRealPath(), 'r') : fopen($file, 'r'),
                    'filename' => $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($file),
                ],
                [
                    'name' => 'api_key',
                    'contents' => $apiKey,
                ],
                [
                    'name' => 'timestamp',
                    'contents' => $timestamp,
                ],
                [
                    'name' => 'folder',
                    'contents' => $folderPath,
                ],
                [
                    'name' => 'signature',
                    'contents' => $signature,
                ],
            ]
        );

        if ($response->successful() && isset($response->json()['secure_url'])) {
            return $response->json()['secure_url'];
        }

        Log::error('Direct Cloudinary HTTP Upload failed: ' . $response->body());
        throw new \RuntimeException('Failed to upload file to Cloudinary HTTP API: ' . $response->body());
    }
}
