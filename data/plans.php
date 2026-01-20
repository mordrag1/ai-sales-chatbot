<?php
/**
 * Plans configuration and Polar.sh product mapping.
 */
return [
    // Available plans
    'plans' => [
        'demo'  => ['name' => 'Demo',  'price' => 0],
        'start' => ['name' => 'Start', 'price' => 990],
        'pro'   => ['name' => 'Pro',   'price' => 1990],
        'max'   => ['name' => 'Max',   'price' => 4990],
    ],

    // Polar.sh product ID -> plan_id mapping
    'products' => [
        // Add your Polar product IDs here after creating products
        // 'product_xxx' => 'start',
        // 'product_yyy' => 'pro',
        // 'product_zzz' => 'max',
    ],

    // Polar.sh product name -> plan_id mapping (fallback)
    'names' => [
        'Start Plan' => 'start',
        'Start'      => 'start',
        'Pro Plan'   => 'pro',
        'Pro'        => 'pro',
        'Max Plan'   => 'max',
        'Max'        => 'max',
    ],
];


