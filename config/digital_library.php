<?php

/**
 * Single source of truth for what each Intelligent Vault document type
 * needs. Both the upload form (via @json() into its JS) and the browse
 * page read this same array, so adding or re-tiering a type is a one-line
 * change here instead of hunting through blade views and JS arrays.
 *
 * level:
 *   'product' — tied to a brand AND a chemical sub-category (SDS/TDS-style).
 *   'brand'   — tied to a brand only, no sub-category.
 *   'video'   — tied to its own category taxonomy, no brand at all.
 */

return [
    'document_types' => [
        'SDS' => [
            'label' => 'SDS – Safety Data Sheet',
            'level' => 'product',
        ],
        'TDS' => [
            'label' => 'TDS – Technical Data Sheet',
            'level' => 'product',
        ],
        'Test Report' => [
            'label' => '🧪 Test Report',
            'level' => 'product',
        ],
        'Presentation' => [
            'label' => 'Presentation',
            'level' => 'brand',
        ],
        'Comparison Report' => [
            'label' => '📊 Comparison Report',
            'level' => 'brand',
        ],
        'Video' => [
            'label' => 'Video',
            'level' => 'video',
        ],
    ],
];
