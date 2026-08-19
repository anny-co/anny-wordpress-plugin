<?php

declare(strict_types=1);

namespace Anny\Blocks;

\defined('ABSPATH') || exit;

/**
 * Reads widgets-manifest.json (the single source of truth for all widgets)
 * and provides the translated title/description for every block.
 *
 * The manifest itself stays string-free; all user-facing labels live here as
 * `__()` literals so gettext tooling picks them up.
 */
final class Manifest
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $entries = null;

    /**
     * @return array<string, array<string, mixed>> Block slug => widget definition.
     */
    public function entries(): array
    {
        if ($this->entries === null) {
            $raw     = file_get_contents(ANNY_DIR . '/widgets-manifest.json');
            $decoded = is_string($raw) ? json_decode($raw, true) : null;

            $this->entries = is_array($decoded) ? $decoded : [];
        }

        return $this->entries;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function entry(string $slug): ?array
    {
        return $this->entries()[$slug] ?? null;
    }

    /**
     * Translated titles + descriptions for all blocks.
     *
     * @return array<string, array{title: string, description: string}>
     */
    public function labels(): array
    {
        return [
            'organization-page'      => [
                'title'       => __('anny Organization Page', 'anny-bookings'),
                'description' => __('Embed the full booking page of an anny organization.', 'anny-bookings'),
            ],
            'resource-page'          => [
                'title'       => __('anny Resource Page', 'anny-bookings'),
                'description' => __('Embed the booking page of a single anny resource.', 'anny-bookings'),
            ],
            'service-page'           => [
                'title'       => __('anny Service Page', 'anny-bookings'),
                'description' => __('Embed the booking page of an anny service.', 'anny-bookings'),
            ],
            'resource-calendar'      => [
                'title'       => __('anny Resource Calendar', 'anny-bookings'),
                'description' => __('Show the availability calendar of an anny resource.', 'anny-bookings'),
            ],
            'organization-calendar'  => [
                'title'       => __('anny Organization Calendar', 'anny-bookings'),
                'description' => __('Show the availability calendar of an anny organization.', 'anny-bookings'),
            ],
            'resource-map'           => [
                'title'       => __('anny Resource Map', 'anny-bookings'),
                'description' => __('Show an interactive map for an anny resource.', 'anny-bookings'),
            ],
            'organization-map'       => [
                'title'       => __('anny Organization Map', 'anny-bookings'),
                'description' => __('Show an interactive map of the resources of an anny organization.', 'anny-bookings'),
            ],
            'subscription-page'      => [
                'title'       => __('anny Subscription Page', 'anny-bookings'),
                'description' => __('Embed the subscription page of an anny plan.', 'anny-bookings'),
            ],
            'package-page'           => [
                'title'       => __('anny Package Page', 'anny-bookings'),
                'description' => __('Embed the booking page of an anny package.', 'anny-bookings'),
            ],
            'my-bookings'            => [
                'title'       => __('anny My Bookings', 'anny-bookings'),
                'description' => __('Let visitors view and manage their anny bookings.', 'anny-bookings'),
            ],
            'resource-booking-panel' => [
                'title'       => __('anny Resource Booking Panel', 'anny-bookings'),
                'description' => __('Embed a compact booking panel for an anny resource.', 'anny-bookings'),
            ],
            'service-booking-panel'  => [
                'title'       => __('anny Service Booking Panel', 'anny-bookings'),
                'description' => __('Embed a compact booking panel for an anny service.', 'anny-bookings'),
            ],
            'resource-button'        => [
                'title'       => __('anny Resource Button', 'anny-bookings'),
                'description' => __('A button that opens the booking dialog for an anny resource.', 'anny-bookings'),
            ],
            'service-button'         => [
                'title'       => __('anny Service Button', 'anny-bookings'),
                'description' => __('A button that opens the booking dialog for an anny service.', 'anny-bookings'),
            ],
            'organization-button'    => [
                'title'       => __('anny Organization Button', 'anny-bookings'),
                'description' => __('A button that opens the booking dialog for an anny organization.', 'anny-bookings'),
            ],
            'subscription-button'    => [
                'title'       => __('anny Subscription Button', 'anny-bookings'),
                'description' => __('A button that opens the subscription dialog for an anny plan.', 'anny-bookings'),
            ],
            'package-button'         => [
                'title'       => __('anny Package Button', 'anny-bookings'),
                'description' => __('A button that opens the booking dialog for an anny package.', 'anny-bookings'),
            ],
            'modal-button'           => [
                'title'       => __('anny Modal Button', 'anny-bookings'),
                'description' => __('A configurable button that opens an anny booking dialog.', 'anny-bookings'),
            ],
            'login-button'           => [
                'title'       => __('anny Login Button', 'anny-bookings'),
                'description' => __('A button that lets visitors log in to their anny account.', 'anny-bookings'),
            ],
            'cart-modal-button'      => [
                'title'       => __('anny Cart Button', 'anny-bookings'),
                'description' => __('A button that opens the anny booking cart.', 'anny-bookings'),
            ],
        ];
    }
}
