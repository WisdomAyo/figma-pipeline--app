<?php

namespace App\Http\Controllers;

use App\Http\Data\ProcessImageData;
use App\Http\Requests\ProcessImageRequest;
use App\Services\ImageProcessor;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * ImageProcessorController
 *
 * Handles HTTP requests for image processing operations
 */
class ImageProcessorController extends Controller
{
    use ApiResponse;

    /**
     * ImageProcessor service instance
     *
     * @var ImageProcessor
     */
    private ImageProcessor $service;

    /**
     * Constructor
     *
     * @param ImageProcessor $service
     */
    public function __construct(ImageProcessor $service)
    {
        $this->service = $service;
    }

    /**
     * Process an image from Figma
     *
     * This endpoint accepts a Figma file key and node ID, fetches the corresponding
     * image from Figma API, optimizes it, and returns the Base64-encoded result
     *
     * @param ProcessImageRequest $request
     * @return JsonResponse
     */
    public function processImage(ProcessImageRequest $request): JsonResponse
    {
        try {
            // Convert validated request data to DTO
                    $data = ProcessImageData::from([
                'fileKey' => $request->validated('file_key'),
                'nodeId'  => $request->validated('node_id'),
            ]);

            // Process the image through the service
            $result = $this->service->processImage($data);

            // Return successful response
            return $this->successfulResponse(
                $result,
                __('Image processed successfully')
            );

        } catch (Exception $e) {
            // Return error response
            return $this->errorResponse(
                __('Failed to process image'),
                ['error' => $e->getMessage()]
            );
        }
    }
}
