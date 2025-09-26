<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Service responsible for fetching Figma variables and building a Tailwind config.
 */
class FigmaTailwindBuilder
{
    private Client $http;

    public function __construct()
    {
        $this->http = new Client([
            'base_uri' => 'https://api.figma.com',
            'timeout'  => 10.0,
            // You may add retries/middleware as needed
        ]);
    }

    /**
     * @param string $fileKey
     * @param string $format 'js' or 'json'
     * @return string JS string (module.exports = ...) or JSON string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function build(string $fileKey, string $format = 'js'): string
    {
      $token = config('figma.api.token') ?: env('FIGMA_TOKEN');
    
        if (empty($token)) {
               throw new \RuntimeException('No valid Figma access token found. Please authenticate via /auth/figma/login');
        }

        $response = $this->http->request('GET', "/v1/files/{$fileKey}/variables/local", [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Accept'        => 'application/json',
            ],
        ]);

        $payload = json_decode((string) $response->getBody(), true);

        $variables = $payload['variables'] ?? $payload['data'] ?? [];
        // Normalize structure: some Figma responses put variables under 'variables', some under 'data', keep robust.

        $theme = $this->mapVariablesToTheme($variables);

        // Build tailwind config array
        $configArray = [
            'content' => ["./src/**/*.{js,ts,jsx,tsx,vue,blade.php}"],
            'theme' => [
                'extend' => $theme,
            ],
            'plugins' => [],
        ];

        if ($format === 'json') {
            return json_encode($configArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        // Build JS string (module.exports)
        $js = $this->toJsModuleString($configArray);

        return $js;
    }


    public function buildFromJson(array $variablesJson, string $format = 'js')
    {
        // parse $variablesJson and map to Tailwind config
        $theme = [
            'borderRadius' => [
                'DEFAULT' => '4px', // map from Radius variable
            ],
            'colors' => [
                'primary' => '#FF0000',
                'secondary' => '#00FF00',
            ],
            'spacing' => [
                'sm' => '4px',
                'md' => '8px',
                'lg' => '16px',
            ],
        ];

         if ($format === 'json') {
        // Return as plain JSON
        return json_encode(['theme' => $theme], JSON_PRETTY_PRINT);
    }

        // Return as JS config file
        return "/** Generated locally */\nmodule.exports = " 
            . json_encode(['theme' => $theme], JSON_PRETTY_PRINT) 
            . ";";
    }

    /**
     * Map Figma variables list to Tailwind theme structure.
     *
     * @param array $variables
     * @return array
     */
    private function mapVariablesToTheme(array $variables): array
    {
        $theme = [
            'colors' => [],
            'spacing' => [],
            'borderRadius' => [],
            'fontFamily' => [],
            'fontSize' => [],
            'boxShadow' => [],
            'opacity' => [],
            'custom' => [],
        ];

        foreach ($variables as $var) {
            // Figma variable payload can vary; try typical keys
            $name = $var['name'] ?? ($var['key'] ?? null);
            $type = $var['type'] ?? ($var['variableType'] ?? null);
            $value = $this->extractValue($var);

            if (empty($name)) {
                continue;
            }

            $k = $this->normalizeKey($name);

            // Try type-based mapping first
            if ($this->looksLikeColor($type, $name, $value)) {
                $this->insertNested($theme['colors'], $k, $this->normalizeColor($value));
                continue;
            }

            if ($this->looksLikeSpacing($type, $name)) {
                $this->insertNested($theme['spacing'], $k, $this->normalizeSpacing($value));
                continue;
            }

            if ($this->looksLikeRadius($type, $name)) {
                $this->insertNested($theme['borderRadius'], $k, $this->normalizeSpacing($value));
                continue;
            }

            if ($this->looksLikeFontFamily($type, $name, $value)) {
                // If the value is a string or array of fonts
                $fontVal = is_array($value) ? $value : [$value];
                $this->insertNested($theme['fontFamily'], $k, $fontVal);
                continue;
            }

            if ($this->looksLikeFontSize($type, $name)) {
                $this->insertNested($theme['fontSize'], $k, $this->normalizeFontSize($value));
                continue;
            }

            // fallback: add to custom
            $this->insertNested($theme['custom'], $k, $value);
        }

        // remove empties
        return array_filter($theme, function ($v) { return !empty($v); });
    }

    /**
     * Extracts the "value" from the variable object robustly.
     */
    private function extractValue(array $var)
    {
        // Commonly Figma variable has 'values' or 'value' or 'variableValues[0].value'
        if (isset($var['value'])) {
            return $var['value'];
        }

        if (isset($var['values'])) {
            return $var['values'];
        }

        if (isset($var['variableValues']) && is_array($var['variableValues'])) {
            // pick first
            $first = $var['variableValues'][0] ?? null;
            return $first['value'] ?? $first;
        }

        return $var;
    }

    private function normalizeKey(string $name): string
    {
        // remove weird characters and make key dot-notated e.g. color-primary-500 -> color.primary.500
        $name = trim($name);
        $name = str_replace(['[',']','(',')','#'], '', $name);
        $name = preg_replace('/\s+/', '-', $name);
        $parts = preg_split('/[._\-\s\/]+/', $name);
        // drop leading generic words like 'global' etc optionally
        return implode('.', array_map(fn($p) => Str::slug($p, '-'), $parts));
    }

    private function looksLikeColor($type, $name, $value): bool
    {
        if (is_string($type) && str_contains(strtolower($type), 'color')) {
            return true;
        }
        if (is_string($name) && preg_match('/\b(color|clr|col|c-)\b/i', $name)) {
            return true;
        }
        // value contains r,g,b or hex
        if (is_array($value) && (isset($value['r']) || isset($value['g']) || isset($value['b']) || isset($value['hex']))) {
            return true;
        }
        if (is_string($value) && preg_match('/^#?[0-9A-Fa-f]{6,8}$/', trim((string)$value))) {
            return true;
        }
        return false;
    }

    private function looksLikeSpacing($type, $name): bool
    {
        if ($type && str_contains(strtolower($type), 'number')) {
            if (preg_match('/(spacing|space|gap|padding|margin|sp|s-)/i', $name)) {
                return true;
            }
        }
        return (bool) preg_match('/\b(spacing|space|gap|padding|padding|px|rem|em|s-)\b/i', $name);
    }

    private function looksLikeRadius($type, $name): bool
    {
        return (bool) preg_match('/\b(radius|round|r-|\bbr\b|border-radius)\b/i', $name);
    }

    private function looksLikeFontFamily($type, $name, $value): bool
    {
        if (is_string($type) && str_contains(strtolower($type), 'font')) return true;
        if (preg_match('/font-family|typography|font/i', $name)) return true;
        if (is_string($value) && str_contains($value, ',')) return true;
        return false;
    }

    private function looksLikeFontSize($type, $name): bool
    {
        return (bool) preg_match('/font-size|fs-|fz-|type-size/i', $name);
    }

    private function normalizeColor($value)
    {
        // value could be hex string or object with r,g,b,a. Convert to hex or rgba string
        if (is_string($value)) {
            $v = trim($value);
            if (str_starts_with($v, '#')) return $v;
            if (preg_match('/^[0-9A-Fa-f]{6,8}$/', $v)) {
                return '#'.$v;
            }
            return $v;
        }

        if (is_array($value)) {
            // handle Figma color like {r:0.1,g:0.2,b:0.3,a:1}
            if (isset($value['r']) && isset($value['g']) && isset($value['b'])) {
                $r = (int) round($value['r'] * 255);
                $g = (int) round($value['g'] * 255);
                $b = (int) round($value['b'] * 255);
                $a = isset($value['a']) ? $value['a'] : 1;
                if ($a >= 1) {
                    return sprintf('#%02x%02x%02x', $r, $g, $b);
                }
                return "rgba({$r}, {$g}, {$b}, {$a})";
            }
            // some variables use 'hex' key
            if (isset($value['hex'])) {
                $hex = trim($value['hex']);
                return str_starts_with($hex, '#') ? $hex : "#{$hex}";
            }
        }

        return $value;
    }

    private function normalizeSpacing($value)
    {
        // if numeric, append 'px' by default to be safe — but Tailwind spacing typically numeric keys map to rem values.
        if (is_numeric($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_array($value) && isset($value['value'])) {
            return $this->normalizeSpacing($value['value']);
        }
        return $value;
    }

    private function normalizeFontSize($value)
    {
        if (is_string($value)) return $value;
        if (is_numeric($value)) return $value;
        if (is_array($value)) {
            // Could be {value: 16, unit: 'px'}
            if (isset($value['value']) && isset($value['unit'])) {
                return "{$value['value']}{$value['unit']}";
            }
            if (isset($value['value'])) {
                return $value['value'];
            }
        }
        return $value;
    }

    /**
     * Insert into nested array using dot key (a.b.c).
     */
    private function insertNested(array &$arr, string $dotKey, $value): void
    {
        $parts = explode('.', $dotKey);
        $ref = &$arr;
        foreach ($parts as $part) {
            if (!isset($ref[$part]) || !is_array($ref[$part])) {
                $ref[$part] = [];
            }
            $ref = &$ref[$part];
        }
        // if leaf already scalar, keep it as value; else set
        if (is_array($ref) && empty($ref)) {
            $ref = $value;
        } else {
            $ref = $value;
        }
    }

    private function toJsModuleString(array $config): string
    {
        // Simple conversion: use var_export for arrays and adjust to JS syntax minimally.
        // For a production tool you might use a proper JS serializer or templates.
        $export = var_export($config, true);

        // Convert PHP array syntax to JS object-ish syntax - basic conversion:
        // 1) => replace 'array (' / ')' with [ ] is not necessary; we'll produce JS object with module.exports = { ... }
        // Simpler: generate JSON and then adjust to JS (unquoted keys), but safe approach: output JSON as JS const.

        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $js = "/** Generated by Figma → Tailwind service */\n";
        $js .= "module.exports = {$json};\n";

        return $js;
    }
}
