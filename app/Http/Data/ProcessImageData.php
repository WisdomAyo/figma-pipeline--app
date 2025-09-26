<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\MapInputName;

/**
 * Data Transfer Object for image processing request
 *
 * This DTO encapsulates the required parameters for processing
 * a Figma design screenshot through the optimization pipeline
 */
class ProcessImageData extends Data
{
     public function __construct(
        #[Required, StringType, Min(1), Max(255)]
        #[MapInputName('file_key')]
        public string $fileKey,

        #[Required, StringType, Min(1), Max(255)]
        #[MapInputName('node_id')]
        public string $nodeId
    ) {}
}
