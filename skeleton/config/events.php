<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Event Listeners
    |--------------------------------------------------------------------------
    |
    | Register synchronous, container-resolved listeners for domain events.
    | Use fully qualified event class names as keys and listener class names
    | as values. Keep listeners API-safe: no sessions, cookies, or UI work.
    |
    */
    'listeners' => [
        App\Events\UserCreated::class => [
            App\Listeners\LogUserCreated::class,
        ],
    ],
];
