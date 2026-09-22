<?php

declare(strict_types=1);

/*
 * Document B §8: passwords are hashed with Argon2id, tuned so one verification
 * takes at least 250 ms on the production host. Tune memory and time there and
 * measure; the test suite uses a cheap bcrypt through HASH_DRIVER.
 */
return [
    'driver' => env('HASH_DRIVER', 'argon2id'),

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => env('HASH_VERIFY', true),
    ],

    'argon' => [
        'memory' => (int) env('ARGON_MEMORY', 65536),
        'threads' => (int) env('ARGON_THREADS', 1),
        'time' => (int) env('ARGON_TIME', 4),
        'verify' => env('HASH_VERIFY', true),
    ],

    'rehash_on_login' => true,
];
