<?php

declare(strict_types=1);

namespace Anny;

\defined('ABSPATH') || exit;

/**
 * The anny data regions and the CDN script URL.
 *
 * Each region maps to a widget base URL (used as `base-url` on the custom
 * elements), a JSON:API base, a Cognitor auth host and the CDN URL of the
 * web-components bundle. The per-region OAuth client UUIDs are baked in at
 * release time.
 */
final class Regions
{
    /** Fallback bundle URL for regions added via `anny_regions` without a `script` key. */
    private const SCRIPT_URL = 'https://cdn.anny.co/widget/annyComponents.umd.latest.min.js';

    /**
     * All known regions, filterable via `anny_regions`.
     *
     * @return array<string, array{label: string, widget_base: string, api: string, auth: string, client_id: string, script: string}>
     */
    public function all(): array
    {
        $map = [
            'anny.co'         => [
                'label'       => 'anny.co',
                'widget_base' => 'https://anny.co',
                'api'         => 'https://b.anny.co/api/v1',
                'auth'        => 'https://auth.anny.co',
                'client_id'   => '019ff670-832f-7178-8383-81a9c8cbc082',
                'script'      => 'https://cdn.anny.co/widget/annyComponents.umd.latest.min.js',
            ],
            'anny.eu'         => [
                'label'       => 'anny.eu',
                'widget_base' => 'https://anny.eu',
                'api'         => 'https://b.anny.eu/api/v1',
                'auth'        => 'https://auth.anny.eu',
                'client_id'   => '019ff680-0a9e-720a-ae83-e9acfc3d24b6',
                'script'      => 'https://cdn.anny.eu/widget/annyComponents.umd.latest.min.js',
            ],
        ];

        /**
         * Filters the region map.
         *
         * @param array<string, array<string, string>> $map Region key => region config.
         */
        $filtered = apply_filters('anny_regions', $map);

        return is_array($filtered) ? $filtered : $map;
    }

    /**
     * @return array{label: string, widget_base: string, api: string, auth: string, client_id: string, script: string}|null
     */
    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * URL of the web-components bundle for a region (each region loads it
     * from its own CDN), filterable via `anny_script_url`.
     */
    public function scriptUrl(string $regionKey): string
    {
        $region  = $this->get($regionKey);
        $default = is_string($region['script'] ?? null) && $region['script'] !== ''
            ? $region['script']
            : self::SCRIPT_URL;

        $url = apply_filters('anny_script_url', $default, $regionKey);

        return is_string($url) ? $url : $default;
    }
}
