<?php

/*
 * The pre-built designs a school can choose between for ID cards and
 * certificates (spec: "configure pre templates and be able to choose which
 * one is active"). Each entry names a Blade file under
 * resources/views/{id-cards,certificates}/layouts/ that renders the card —
 * a genuinely different layout, not just a recolour of one fixed design —
 * plus the colours it looks best with (a school can still pick its own).
 *
 * 'classic' is the layout that already existed before this catalog did; it
 * is every template's default so a school that never touches this still
 * gets exactly what it always got.
 */
return [

    'id_card' => [
        'classic' => [
            'name' => 'Classic',
            'description' => 'Photo and details side by side, a thin double border. The original ElimuGrid card.',
            'colors' => ['#0a1f44', '#d4a017'],
        ],
        'wave_header' => [
            'name' => 'Wave Header',
            'description' => 'A bold colour band across the top behind the school name, photo below.',
            'colors' => ['#0f5c9e', '#f2b705'],
        ],
        'side_bar' => [
            'name' => 'Side Bar',
            'description' => 'A colour bar down one edge; name and details lead, photo to the side.',
            'colors' => ['#1b6b4a', '#0a1f44'],
        ],
        'minimal_mono' => [
            'name' => 'Minimal',
            'description' => 'Plain white card, a thin single-colour border — nothing else competing for attention.',
            'colors' => ['#22262f', '#22262f'],
        ],
        'dark_executive' => [
            'name' => 'Dark Executive',
            'description' => 'A dark card with light text and a gold rule — a formal, high-contrast look.',
            'colors' => ['#12141c', '#d4a017'],
        ],
    ],

    'certificate' => [
        'classic' => [
            'name' => 'Classic',
            'description' => 'A double border frame, centred title and name. The original ElimuGrid certificate.',
            'colors' => ['#0a1f44', '#d4a017'],
        ],
        'gold_ribbon' => [
            'name' => 'Gold Ribbon',
            'description' => 'A round gold seal beneath the signature line and gold corner accents.',
            'colors' => ['#7a1f2b', '#c9a227'],
        ],
        'modern_diagonal' => [
            'name' => 'Modern Diagonal',
            'description' => 'A colour block cut across one corner — a contemporary, asymmetric frame.',
            'colors' => ['#0f5c9e', '#f2b705'],
        ],
        'elegant_serif' => [
            'name' => 'Elegant Serif',
            'description' => 'A serif, academic look with a slim gold border and a wax-seal mark.',
            'colors' => ['#1b3a2b', '#c9a227'],
        ],
        'minimal_thin' => [
            'name' => 'Minimal',
            'description' => 'A single thin rule, generous white space, sans-serif — understated and modern.',
            'colors' => ['#22262f', '#5a6478'],
        ],
    ],

];
