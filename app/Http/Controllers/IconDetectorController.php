<?php

namespace App\Http\Controllers;


use App\Http\Data\ProcessImageData;
use App\Http\Requests\ProcessImageRequest;
use App\Services\IconDetector;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * IconDetectorController
 *
 * Handles HTTP requests for vector icon detection and download operations
 *
 * @OA\Tag(
 *     name="Icon Detection",
 *     description="Operations related to detecting and downloading Figma icons"
 * )
 */
class IconDetectorController extends Controller
{
    use ApiResponse;

    /**
     * IconDetector service instance
     *
     * @var IconDetector
     */
    private IconDetector $service;

    /**
     * Constructor
     *
     * @param IconDetector $service
     */
    public function __construct(IconDetector $service)
    {
        $this->service = $service;
    }

    /**
     * Detect and download vector icons from Figma
     *
     * This endpoint accepts a Figma file key and node ID, detects all vector icons
     * and icon groups within the node tree, downloads them as SVG files, and returns
     * a list of publicly accessible URLs for each icon.
     *
     * @OA\Post(
     *     path="/api/v1/detect-icons",
     *     operationId="detectIcons",
     *     tags={"Icon Detection"},
     *     summary="Detect and download vector icons from Figma",
     *     description="Detects vector icons and icon groups from a Figma design, downloads them as SVG files, and returns public URLs",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"file_key", "node_id"},
     *             @OA\Property(
     *                 property="file_key",
     *                 type="string",
     *                 description="The Figma file key",
     *                 example="6uZ8mS1F4Oi7aL0VJLau49"
     *             ),
     *             @OA\Property(
     *                 property="node_id",
     *                 type="string",
     *                 description="The Figma node ID",
     *                 example="13191:107467"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Icons detected and downloaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Icons detected and downloaded successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="assets",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="name", type="string", example="icon_home_a1b2c3d4.svg"),
     *                         @OA\Property(property="url", type="string", example="https://yourapp.com/storage/figma_icons/icon_home_a1b2c3d4.svg")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="timestamp", type="string", example="2024-01-15, 14:30:45"),
     *             @OA\Property(property="execution_time", type="string", example="2150.45 ms")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or processing failure",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to detect icons"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="error", type="string", example="No node found for ID: 13191:107467")
     *             ),
     *             @OA\Property(property="timestamp", type="string", example="2024-01-15, 14:30:45"),
     *             @OA\Property(property="execution_time", type="string", example="150.23 ms")
     *         )
     *     )
     * )
     *
     * @param DetectIconsRequest $request
     * @return JsonResponse
     */
       public function detectIcons(ProcessImageRequest $request): JsonResponse
    {

          // Map request data to DTO
            $data = ProcessImageData::from([
                'fileKey' => $request->input('file_key'),
                'nodeId'  => $request->input('node_id')
            ]);
            
        try {


            // Call service
            $result = $this->service->detectAndDownloadIcons($data->fileKey, $data->nodeId);

            // Success response using trait
            return $this->successfulResponse(
                $result,
                __('Icons detected and downloaded successfully')
            );

        } catch (Exception $e) {
            // Error response using trait
            return $this->errorResponse(
                __('Failed to detect icons'),
                ['error' => $e->getMessage()]
            );
        }
    }
}
