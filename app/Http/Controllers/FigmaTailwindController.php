<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateTailwindConfigRequest;
use App\Services\FigmaTailwindBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;



/**
 * @OA\Post(
 *     path="/api/figma/tailwind-config",
 *     summary="Generate Tailwind config from Figma variables",
 *     tags={"Figma"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"file_key"},
 *             @OA\Property(property="file_key", type="string", example="uL0bIRuhx1tH0b6INwWDXq"),
 *             @OA\Property(property="format", type="string", enum={"js","json"}, example="js")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Generated Tailwind config (JS string or JSON)",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean"),
 *             @OA\Property(property="tailwind_config", type="string", description="tailwind.config.js as a JS string or JSON")
 *         )
 *     ),
 *     @OA\Response(response=422, description="Validation error"),
 *     @OA\Response(response=500, description="Internal server error")
 * ),
 *  * @OA\Info(
 *     title="Figma → Tailwind Config API",
 *     version="1.0.0",
 *     description="API that reads Figma variables and generates Tailwind config"
 * )
 */
class FigmaTailwindController extends Controller
{
    public function __construct(private FigmaTailwindBuilder $builder) {}

    public function generate(GenerateTailwindConfigRequest $request, FigmaTailwindBuilder $builder): JsonResponse
    {
       // $fileKey = $request->get('file_key');
        $format = $request->get('format', 'js');

        try {

            if ($request->has('file_key')) {
            // API mode
            $fileKey = $request->input('file_key');
            $config = $builder->build($fileKey, $format);
        } else {
            // JSON mode
            $variablesJson = $request->input('variables_json');
            $config = $builder->buildFromJson($variablesJson, $format);
        }

        return response()->json([
            'success' => true,
            'tailwind_config' => $config,
        ]);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            Log::error('Figma API error: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch variables from Figma.',
            ], 502);
        } catch (\Throwable $e) {
            Log::error('Tailwind build error: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate tailwind config.',
            ], 500);
        }
    }
}
