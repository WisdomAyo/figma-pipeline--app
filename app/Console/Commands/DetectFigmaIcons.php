<?php

namespace App\Console\Commands;


use App\Http\Data\ProcessImageData;
use App\Services\IconDetector;
use Exception;
use Illuminate\Console\Command;

/**
 * Artisan command for detecting and downloading Figma icons via CLI
 *
 * Useful for testing and batch processing operations
 */
class DetectFigmaIcons extends Command
{
    /**
     * The name and signature of the console command
     *
     * @var string
     */
    protected $signature = 'figma:detect-icons
                            {fileKey : The Figma file key}
                            {nodeId : The node/frame ID}
                            {--output= : Optional output directory for downloaded icons}
                            {--json : Output results as JSON}';

    /**
     * The console command description
     *
     * @var string
     */
    protected $description = 'Detect and download vector icons from Figma designs';

    /**
     * IconDetector service instance
     *
     * @var IconDetector
     */
    private IconDetector $iconDetector;

    /**
     * Create a new command instance
     *
     * @param IconDetector $iconDetector
     * @return void
     */
    public function __construct(IconDetector $iconDetector)
    {
        parent::__construct();
        $this->iconDetector = $iconDetector;
    }

    /**
     * Execute the console command
     *
     * @return int
     */
    public function handle(): int
    {
        $startTime = microtime(true);

        $this->info('Starting Figma icon detection...');
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

            // Detect and download icons
            $this->info('Fetching node details from Figma API...');
            $this->info('Detecting vector icons...');

            $result = $this->iconDetector->detectAndDownloadIcons($data->fileKey, $data->nodeId);

            // Handle JSON output option
            if ($this->option('json')) {
                $this->line(json_encode($result, JSON_PRETTY_PRINT));
                return Command::SUCCESS;
            }

            // Display results
            $this->newLine();

            if (empty($result['assets'])) {
                $this->warn('No vector icons detected in the specified node.');
            } else {
                $this->info('Icons detected and downloaded successfully!');
                $this->newLine();

                $this->info('Downloaded Icons:');
                $this->table(
                    ['Name', 'URL'],
                    collect($result['assets'])->map(function ($asset) {
                        return [
                            $asset['name'],
                            $asset['url']
                        ];
                    })->toArray()
                );

                $this->info('Total icons downloaded: ' . count($result['assets']));
            }

            // Handle custom output directory option
            if ($outputDir = $this->option('output')) {
                $this->newLine();
                $this->info("Copying icons to custom directory: {$outputDir}");

                if (!is_dir($outputDir)) {
                    mkdir($outputDir, 0755, true);
                }

                foreach ($result['assets'] as $asset) {
                    $sourcePath = storage_path('app/public/figma_icons/' . $asset['name']);
                    $destPath = $outputDir . '/' . $asset['name'];

                    if (file_exists($sourcePath)) {
                        copy($sourcePath, $destPath);
                        $this->line("Copied: {$asset['name']}");
                    }
                }
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->newLine();
            $this->info("Execution time: {$executionTime} ms");

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('❌ Failed to detect icons!');
            $this->error($e->getMessage());

            if ($this->option('verbose')) {
                $this->error('Stack trace:');
                $this->error($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }
}
