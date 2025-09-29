<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="Figma Pipeline API",
 *     version="1.0.0",
 *     description="API for processing Figma designs, optimizing images, and detecting vector icons",
 *     @OA\Contact(
 *         email="dev@yourcompany.com"
 *     ),
 *     @OA\License(
 *         name="Proprietary",
 *         url="https://yourcompany.com/license"
 *     )
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Local Development Server"
 * )
 *
 * @OA\Server(
 *     url="https://api.yourapp.com",
 *     description="Production Server"
 * )
 *
 * @OA\Schema(
 *     schema="SuccessResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string"),
 *     @OA\Property(property="data", type="object"),
 *     @OA\Property(property="timestamp", type="string"),
 *     @OA\Property(property="execution_time", type="string")
 * )
 *
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string"),
 *     @OA\Property(property="errors", type="object"),
 *     @OA\Property(property="timestamp", type="string"),
 *     @OA\Property(property="execution_time", type="string")
 * )
 */
class OpenApiInfoController extends Controller
{
    
}
