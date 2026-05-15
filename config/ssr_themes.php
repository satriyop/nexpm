<?php

/**
 * Per-main-contractor visual themes for the SSR PDF report.
 *
 * Keys are matched case-insensitively against the main contractor name.
 * Add a new entry per contractor; the 'default' theme is the fallback.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Default theme  (blue — original look)
    |--------------------------------------------------------------------------
    */
    'default' => [
        'accent_color' => '#1a56db',   // header strip, section-head-blue, links
        'section_head_bg' => '#d9d9d9',   // plain section header background
        'section_head_fg' => '#000000',   // plain section header text
        'border_color' => '#333333',   // outer section border
        'cell_border' => '#cccccc',   // info-table cell borders
        'label_bg' => '#f0f0f0',   // label column background
        'label_fg' => '#222222',   // label column text color
        'label_border' => '#cccccc',   // label cell border
        'font_family' => 'Arial, Helvetica, sans-serif',
    ],

    /*
    |--------------------------------------------------------------------------
    | Theme 2 — Forest Green
    | Intended for: e.g. "PT VAHANA GASTI TEKNIKA"
    |--------------------------------------------------------------------------
    */
    'PT VAHANA GASTI TEKNIKA' => [
        'accent_color' => '#15803d',
        'section_head_bg' => '#dcfce7',
        'section_head_fg' => '#14532d',
        'border_color' => '#166534',
        'cell_border' => '#86efac',
        'label_bg' => '#f0fdf4',
        'label_fg' => '#14532d',
        'label_border' => '#86efac',
        'font_family' => 'Arial, Helvetica, sans-serif',
    ],

    /*
    |--------------------------------------------------------------------------
    | Theme 3 — Corporate Red
    | Intended for: e.g. "CV SIGMATEC"
    |--------------------------------------------------------------------------
    */
    'CV SIGMATEC' => [
        'accent_color' => '#b91c1c',
        'section_head_bg' => '#fee2e2',
        'section_head_fg' => '#7f1d1d',
        'border_color' => '#991b1b',
        'cell_border' => '#fca5a5',
        'label_bg' => '#fff7f7',
        'label_fg' => '#7f1d1d',
        'label_border' => '#fca5a5',
        'font_family' => 'Arial, Helvetica, sans-serif',
    ],

    /*
    |--------------------------------------------------------------------------
    | Theme 4 — Ocean Teal
    | Intended for: e.g. a 4th contractor
    |--------------------------------------------------------------------------
    */
    'OCEAN TEAL PLACEHOLDER' => [
        'accent_color' => '#0e7490',
        'section_head_bg' => '#cffafe',
        'section_head_fg' => '#164e63',
        'border_color' => '#0e7490',
        'cell_border' => '#67e8f9',
        'label_bg' => '#f0fdff',
        'label_fg' => '#164e63',
        'label_border' => '#67e8f9',
        'font_family' => 'Arial, Helvetica, sans-serif',
    ],

];
