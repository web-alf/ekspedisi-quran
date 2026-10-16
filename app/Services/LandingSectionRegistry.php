<?php

namespace App\Services;

class LandingSectionRegistry
{
    /**
     * Default landing page sections with stable identifiers
     */
    public static function getDefaultOrder(): array
    {
        return [
            ['id' => 'hero', 'label' => 'Hero Banner', 'enabled' => true],
            ['id' => 'about', 'label' => 'Tentang Program', 'enabled' => true],
            ['id' => 'map', 'label' => 'Peta Distribusi', 'enabled' => true],
            ['id' => 'tracking', 'label' => 'Cek Resi', 'enabled' => true],
            ['id' => 'gallery', 'label' => 'Galeri', 'enabled' => true],
            ['id' => 'video', 'label' => 'Video', 'enabled' => true],
            ['id' => 'testimonials', 'label' => 'Testimoni', 'enabled' => true],
            ['id' => 'faq', 'label' => 'FAQ', 'enabled' => true],
            ['id' => 'cta', 'label' => 'Call to Action', 'enabled' => true],
        ];
    }

    /**
     * Get list of valid section IDs
     *
     * @return array<string>
     */
    public static function getValidSectionIds(): array
    {
        return array_column(static::getDefaultOrder(), 'id');
    }

    /**
     * Validate and sanitize a custom section order array
     *
     * @param  array<int, array<string, mixed>>  $order
     * @return array<int, array<string, mixed>>
     */
    public static function validate(array $order): array
    {
        $validIds = static::getValidSectionIds();
        $seenIds = [];
        $validated = [];

        foreach ($order as $item) {
            if (! is_array($item) || empty($item['id'])) {
                continue;
            }

            $id = $item['id'];

            if (! in_array($id, $validIds, true) || in_array($id, $seenIds, true)) {
                continue;
            }

            $seenIds[] = $id;
            $validated[] = [
                'id' => $id,
                'label' => $item['label'] ?? static::getDefaultLabel($id),
                'enabled' => filter_var($item['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        // Ensure any missing sections are appended with defaults
        foreach ($validIds as $id) {
            if (! in_array($id, $seenIds, true)) {
                $validated[] = [
                    'id' => $id,
                    'label' => static::getDefaultLabel($id),
                    'enabled' => true,
                ];
            }
        }

        return $validated;
    }

    /**
     * Get default label for a section ID
     */
    public static function getDefaultLabel(string $id): string
    {
        $defaults = array_column(static::getDefaultOrder(), 'label', 'id');

        return $defaults[$id] ?? $id;
    }
}
