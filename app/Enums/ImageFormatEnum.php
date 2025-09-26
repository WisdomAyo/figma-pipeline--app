<?php

namespace App\Enums;

/**
 * Enum for image format types
 *
 * Defines the supported image formats in the processing pipeline
 */
enum ImageFormatEnum: string
{
    case WEBP = 'webp';
    case PNG  = 'png';
    case JPG  = 'jpg';
    case JPEG = 'jpeg';
}
