<?php

return [
    /**
     * Figma API Configuration
     */
    'api' => [
        'token'    => env('FIGMA_API_TOKEN'),
        'base_url' => env('FIGMA_API_BASE_URL', 'https://api.figma.com/v1'),
        'timeout'  => 120,
        'client_id' => env('FIGMA_CLIENT_ID'),
        'client_secret' => env('FIGMA_CLIENT_SECRET'),
        'redirect_uri' => env('FIGMA_REDIRECT_URI'),
    ],
    

    /**
     * Image Processing Configuration
     */
    'image' => [
        'max_width'      => env('IMAGE_MAX_WIDTH', 800),
        'webp_quality'   => env('IMAGE_WEBP_QUALITY', 85),
        'temp_directory' => env('IMAGE_TEMP_DIRECTORY', 'temp'),
    ],
];
