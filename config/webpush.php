<?php

return [
    // Standard W3C Web Push (VAPID) -- generate once with:
    //   php artisan tinker --execute="print_r((new \Minishlink\WebPush\VAPID())::createVapidKeys());"
    // then set these three in .env. Never regenerate once real subscriptions
    // exist -- every stored subscription is tied to the public key it was
    // created with, and swapping keys invalidates all of them at once.
    'public_key' => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),
    'subject' => env('VAPID_SUBJECT', 'mailto:ai@rustx.com'),
];
