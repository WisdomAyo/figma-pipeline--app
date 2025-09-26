<?php

namespace App\Console\Commands;

use App\Http\Data\ProcessImageData;
use App\Services\ImageProcessor;
use Exception;
use Illuminate\Console\Command;

class ProcessFigmaImage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'figma:import
                            {fileKey : The Figma file key}
                            {nodeId : The node/frame ID}
                            {--output= : Optional output file path for Base64 data}';

    /**
     * The console command description.
     *
     * @var string
     */
      protected $description = 'Process a Figma image through the optimization pipeline';

  /**
     * ImageProcessor service instance
     *
     * @var ImageProcessor
     */
    private ImageProcessor $imageProcessor;

    /**
     * Create a new command instance
     *
     * @param ImageProcessor $imageProcessor
     * @return void
     */
    public function __construct(ImageProcessor $imageProcessor)
    {
        parent::__construct();
        $this->imageProcessor = $imageProcessor;
    }

    /**
     * Execute the console command
     *
     * @return int
     */
    public function handle(): int
    {
        $startTime = microtime(true);

        $this->info('Starting Figma image processing...');
        $this->newLine();

        try {
            // Get input arguments
            $fileKey = $this->argument('fileKey');
            $nodeId = $this->argument('nodeId');

            $this->info("File Key: {$fileKey}");
            $this->info("Node ID: {$nodeId}");
            $this->newLine();

            // Create DTO
            $data = ProcessImageData::from([
                'fileKey' => $fileKey,
                'nodeId' => $nodeId
            ]);

            // Process image
            $this->info('Fetching image from Figma API...');
            $result = $this->imageProcessor->processImage($data);

            // Display results
            $this->newLine();
            $this->info('✅ Image processed successfully!');
            $this->newLine();

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Original Size', $result['original_size'] . ' KB'],
                    ['Optimized Size', $result['optimized_size'] . ' KB'],
                    ['Compression Ratio', $result['compression_ratio']],
                    ['Base64 Preview', $result['base64_preview'] . '...'],
                ]
            );

            // Save to file if output option is provided
            if ($outputPath = $this->option('output')) {
                file_put_contents($outputPath, $result['base64_full']);
                $this->info("Base64 data saved to: {$outputPath}");
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->newLine();
            $this->info("Execution time: {$executionTime} ms");

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('❌ Failed to process image!');
            $this->error($e->getMessage());

            if ($this->option('verbose')) {
                $this->error('Stack trace:');
                $this->error($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }
}
