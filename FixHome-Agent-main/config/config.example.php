<?php
// Copy to config.php and replace the values from DirectAdmin > MySQL Management.
return [
    'app' => [
        'name' => 'FixHome',
        'url' => 'https://fixhome.id.vn',
        'timezone' => 'Asia/Ho_Chi_Minh',
        'debug' => false,
        'session_name' => 'fixhome_session',
    ],
    'db' => [
        'host' => 'localhost',
        'port' => '3306',
        'name' => 'youraccount_fixhome',
        'user' => 'youraccount_fixuser',
        'pass' => 'YOUR_DATABASE_PASSWORD',
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'session_idle_seconds' => 7200,
        'session_regenerate_seconds' => 1800,
        'login_max_failures' => 20,
        'login_window_minutes' => 10,
        'register_max_per_hour' => 5,
        'partner_apply_max_per_hour' => 5,
        'customer_order_max_per_hour' => 5,
    ],
    'uploads' => [
        'max_bytes' => 3 * 1024 * 1024,
        'max_pixels' => 24 * 1000 * 1000,
        'allowed_mime' => [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ],
    ],
];
