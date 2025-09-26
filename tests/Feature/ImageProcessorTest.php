<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Test class for ImageProcessor functionality
 */
class ImageProcessorTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create temp directory for testing
        Storage::disk('local')->makeDirectory('temp');
    }

    /**
     * Clean up after tests
     */
    protected function tearDown(): void
    {
        // Clean up temp directory
        Storage::disk('local')->deleteDirectory('temp');

        parent::tearDown();
    }

    /**
     * Test successful image processing
     *
     * @return void
     */
    public function test_process_image_successfully()
    {
        // Mock Figma API response for image URL
        Http::fake([
            'api.figma.com/v1/images/*' => Http::response([
                'images' => [
                    'test-node-id' => 'https://example.com/test-image.png'
                ]
            ], 200),
            'example.com/*' => Http::response(
                file_get_contents(base_path('tests/fixtures/test-image.png')),
                200
            )
        ]);

        // Make request to process image
        $response = $this->postJson('/api/v1/process-image', [
            'file_key' => 'test-file-key',
            'node_id'  => 'test-node-id'
        ]);

        // Assert successful response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'original_size',
                    'optimized_size',
                    'compression_ratio',
                    'base64_preview',
                    'base64_full'
                ],
                'timestamp',
                'execution_time'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Image processed successfully'
            ]);

        // Assert data types
        $data = $response->json('data');
        $this->assertIsNumeric($data['original_size']);
        $this->assertIsNumeric($data['optimized_size']);
        $this->assertStringContainsString('%', $data['compression_ratio']);
        $this->assertEquals(100, strlen($data['base64_preview']));
        $this->assertStringStartsWith('data:image/', $data['base64_full']);
    }

    /**
     * Test validation error for missing file_key
     *
     * @return void
     */
    public function test_validation_error_missing_file_key()
    {
        $response = $this->postJson('/api/v1/process-image', [
            'node_id' => 'test-node-id'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file_key'])
            ->assertJson([
                'success' => false,
                'errors' => [
                    'file_key' => ['The Figma file key is required.']
                ]
            ]);
    }

    /**
     * Test validation error for missing node_id
     *
     * @return void
     */
    public function test_validation_error_missing_node_id()
    {
        $response = $this->postJson('/api/v1/process-image', [
            'file_key' => 'test-file-key'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['node_id'])
            ->assertJson([
                'success' => false,
                'errors' => [
                    'node_id' => ['The node ID is required.']
                ]
            ]);
    }

    /**
     * Test validation error for empty file_key
     *
     * @return void
     */
    public function test_validation_error_empty_file_key()
    {
        $response = $this->postJson('/api/v1/process-image', [
            'file_key' => '',
            'node_id'  => 'test-node-id'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file_key']);
    }

    /**
     * Test handling of Figma API error
     *
     * @return void
     */
    public function test_handles_figma_api_error()
    {
        // Mock Figma API error response
        Http::fake([
            'api.figma.com/v1/images/*' => Http::response([
                'error' => 'Invalid token'
            ], 403)
        ]);

        $response = $this->postJson('/api/v1/process-image', [
            'file_key' => 'test-file-key',
            'node_id'  => 'test-node-id'
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to process image'
            ]);
    }

    /**
     * Test handling of image not found
     *
     * @return void
     */
    public function test_handles_image_not_found()
    {
        // Mock Figma API response without the requested node
        Http::fake([
            'api.figma.com/v1/images/*' => Http::response([
                'images' => []
            ], 200)
        ]);

        $response = $this->postJson('/api/v1/process-image', [
            'file_key' => 'test-file-key',
            'node_id'  => 'non-existent-node'
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to process image'
            ]);
    }
}
