<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class IconDetector
{
    private const FIGMA_API_BASE = 'https://api.figma.com/v1';
    private const ICON_STORAGE_PATH = 'public/figma_icons';
    private const VALID_CONTAINER_TYPES = ['FRAME', 'COMPONENT', 'INSTANCE', 'GROUP'];
    private const VECTOR_TYPE = 'VECTOR';

    /**
     * Detect nodes and download icons as SVGs
     *
     * @param string $fileKey
     * @param string $nodeId
     * @return array ['assets' => [['name'=>..., 'url'=>...], ...]]
     * @throws Exception
     */
    public function detectAndDownloadIcons(string $fileKey, string $nodeId): array
    {
        $start = microtime(true);

        try {
            // 1. Fetch node details (requires file_content:read)
            $nodeDocument = $this->fetchNodeDetails($fileKey, $nodeId);

            // 2. Detect icons (vectors + pure icon groups)
            $iconNodes = $this->detectIconNodes($nodeDocument);

            if (empty($iconNodes)) {
                return ['assets' => [], 'execution_time' => $this->msSince($start)];
            }

            // 3. Download SVGs (Images API)
            $assets = $this->downloadIconsAsSvg($fileKey, $iconNodes);

            return ['assets' => $assets, 'execution_time' => $this->msSince($start)];

        } catch (Exception $e) {
            Log::error('IconDetector error', [
                'file_key' => $fileKey,
                'node_id' => $nodeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function fetchNodeDetails(string $fileKey, string $nodeId): array
    {
        $url = self::FIGMA_API_BASE . "/files/{$fileKey}/nodes";

        $token = config('figma.api.token');

        $response = Http::withHeaders([
            'X-Figma-Token' => $token
        ])
        ->timeout(config('figma.api.timeout', 30))
        ->get($url, ['ids' => $nodeId]);

        if ($response->status() === 403) {
            // Provide clear message about missing scope
            $body = $response->body();
            // Example Figma error mentions missing scope, surface it clearly
            throw new Exception("Failed to fetch node details from Figma API: {$body}");
        }

        if (!$response->successful()) {
            throw new Exception("Failed to fetch node details from Figma API: " . $response->body());
        }

        $json = $response->json();

        if (empty($json['nodes'][$nodeId])) {
            throw new Exception("No node found for ID: {$nodeId}");
        }

        // Node structure includes 'document' -> actual node tree
        return $json['nodes'][$nodeId]['document'] ?? $json['nodes'][$nodeId];
    }

    /**
     * Recursively traverse node doc and find icon nodes (vectors) and icon groups (containers of vectors).
     *
     * @param array $node
     * @param array|null $collected
     * @return array
     */
    private function detectIconNodes(array $node, array &$collected = []): array
    {
        // If the current node itself is a pure vector -> add
        if ($this->isVectorNode($node)) {
            $collected[] = [
                'id' => $node['id'],
                'name' => $node['name'] ?? 'icon',
                'type' => 'vector'
            ];
        }

        // If current node is a container that contains only vectors -> treat as group
        if ($this->isIconGroupNode($node)) {
            $collected[] = [
                'id' => $node['id'],
                'name' => $node['name'] ?? 'icon_group',
                'type' => 'group'
            ];
            // don't descend into this group's children again for duplication (optional)
        }

        // Recurse children
        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                $this->detectIconNodes($child, $collected);
            }
        }

        return $collected;
    }

    private function isVectorNode(array $node): bool
    {
        return isset($node['type']) && $node['type'] === self::VECTOR_TYPE;
    }

    private function isIconGroupNode(array $node): bool
    {
        if (!isset($node['type']) || !in_array($node['type'], self::VALID_CONTAINER_TYPES)) {
            return false;
        }
        if (empty($node['children']) || !is_array($node['children'])) {
            return false;
        }

        return $this->childrenAreVectorsOrGroups($node['children']);
    }

    private function childrenAreVectorsOrGroups(array $children): bool
    {
        foreach ($children as $child) {
            if (!isset($child['type'])) {
                return false;
            }
            if ($child['type'] === self::VECTOR_TYPE) {
                continue;
            }
            if (in_array($child['type'], self::VALID_CONTAINER_TYPES)) {
                if (empty($child['children'])) {
                    return false;
                }
                if (!$this->childrenAreVectorsOrGroups($child['children'])) {
                    return false;
                }
                continue;
            }
            return false;
        }
        return true;
    }

    private function downloadIconsAsSvg(string $fileKey, array $iconNodes): array
    {
        $downloaded = [];

        // ensure directory exists
        Storage::makeDirectory(self::ICON_STORAGE_PATH);

        // chunk node ids into batches for Images API (to avoid URL length issues)
        $ids = array_map(fn($n) => $n['id'], $iconNodes);

        // The Images API expects comma-separated ids param; we will request per node to minimize complexity
        foreach ($iconNodes as $node) {
            try {
                $svg = $this->fetchSvgFromFigma($fileKey, $node['id']);

                $filename = $this->generateFilename($node['name'], $node['id']);
                $path = self::ICON_STORAGE_PATH . '/' . $filename;

                Storage::put($path, $svg);

                // Storage::url('figma_icons/...') -> returns /storage/figma_icons/...
                $publicPath = 'figma_icons/' . $filename;
                $publicUrl = url(Storage::url($publicPath));

                $downloaded[] = [
                    'name' => $filename,
                    'url'  => $publicUrl
                ];
            } catch (Exception $e) {
                Log::warning("Failed to download SVG for node {$node['id']}", [
                    'error' => $e->getMessage()
                ]);
                // continue
            }
        }

        return $downloaded;
    }

    private function fetchSvgFromFigma(string $fileKey, string $nodeId): string
    {
        $url = self::FIGMA_API_BASE . "/images/{$fileKey}";

        $response = Http::withHeaders([
            'X-Figma-Token' => config('figma.api.token')
        ])
        ->timeout(config('figma.api.timeout', 30))
        ->get($url, [
            'ids'    => $nodeId,
            'format' => 'svg'
        ]);

        if (!$response->successful()) {
            throw new Exception("Failed to fetch SVG URL from Figma Images API: " . $response->body());
        }

        $json = $response->json();

        if (empty($json['images'][$nodeId])) {
            throw new Exception("No SVG URL returned for node {$nodeId}");
        }

        $svgUrl = $json['images'][$nodeId];

        // download the SVG content
        $svgResponse = Http::timeout(30)->get($svgUrl);

        if (!$svgResponse->successful()) {
            throw new Exception("Failed to download SVG from {$svgUrl}");
        }

        return $svgResponse->body();
    }

    private function sanitizeIconName(string $name): string
    {
        $n = strtolower($name);
        $n = preg_replace('/[^a-z0-9_-]/', '_', $n);
        $n = preg_replace('/_+/', '_', $n);
        $n = trim($n, '_');
        return $n === '' ? 'icon' : $n;
    }

    private function generateFilename(string $name, string $nodeId): string
    {
        $sanitized = $this->sanitizeIconName($name);
        $idpart = str_replace([':', ';'], '-', $nodeId);
        $hash = substr(md5($nodeId . time()), 0, 8);
        return "{$sanitized}_{$hash}.svg";
    }

    private function msSince(float $start): string
    {
        return round((microtime(true) - $start) * 1000, 2) . ' ms';
    }
}
