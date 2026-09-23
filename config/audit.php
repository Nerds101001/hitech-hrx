<?php

return [

    'enabled' => env('AUDITING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Audit Implementation
    |--------------------------------------------------------------------------
    |
    | Define which Audit model implementation should be used.
    |
    */

    'implementation' => OwenIt\Auditing\Models\Audit::class,

    /*
    |--------------------------------------------------------------------------
    | User Morph prefix & Guards
    |--------------------------------------------------------------------------
    |
    | Define the morph prefix and authentication guards for the User resolver.
    |
    */

    'user'      => [
        'morph_prefix' => 'user',
        'guards'       => [
            'web',
            'api'
        ],
        'resolver'     => OwenIt\Auditing\Resolvers\UserResolver::class
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Resolvers
    |--------------------------------------------------------------------------
    |
    | Define the IP Address, User Agent and URL resolver implementations.
    |
    */
    'resolvers' => [
        'ip_address' => OwenIt\Auditing\Resolvers\IpAddressResolver::class,
        'user_agent' => OwenIt\Auditing\Resolvers\UserAgentResolver::class,
        'url'        => OwenIt\Auditing\Resolvers\UrlResolver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Events
    |--------------------------------------------------------------------------
    |
    | The Eloquent events that trigger an Audit.
    |
    */

    'events' => [
        'created',
        'updated',
        'deleted',
        'restored'
    ],

    /*
    |--------------------------------------------------------------------------
    | Strict Mode
    |--------------------------------------------------------------------------
    |
    | Enable the strict mode when auditing?
    |
    */

    'strict' => false,

    /*
    |--------------------------------------------------------------------------
    | Global exclude
    |--------------------------------------------------------------------------
    |
    | Have something you always want to exclude by default? - add it here.
    | Note that this is overwritten (not merged) with local exclude
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Retention is owned by `hrx:archive-audits`, which MOVES rows older than
    | 12 months into audits_archive rather than deleting them, so nothing is
    | actually lost. This setting is a second, destructive horizon on top of
    | that: audits:partitions drops whole monthly partitions older than this.
    |
    | It defaults to 0 (off) deliberately. With archiving already running,
    | dropping partitions only ever reclaims months that are already empty,
    | and the command refuses to drop a month that still holds rows unless
    | told to. Set a value here only if you want emptied months tidied away
    | automatically.
    |
    */

    'retain_months' => (int) env('AUDIT_RETAIN_MONTHS', 0),

    'exclude' => [
        // Secrets. 434 rows already hold a bcrypt hash of somebody's password
        // because this list was empty -- an audit trail is read by more people
        // than the users table is, and a hash sitting in it is an offline
        // cracking target that buys nothing. The event that the password
        // CHANGED is still recorded; only the value is dropped.
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'api_token',
        'device_token',
        'fcm_token',

        // Bookkeeping columns. They change on every single write, so leaving
        // them in means every audit row carries two timestamps that duplicate
        // the row's own created_at and tell a reader nothing.
        'updated_at',
        'created_at',
        'updated_by_id',
        'deleted_at',

        // Laravel's own cache of a relation count / eager-loaded aggregates.
        'remember_created_at',
    ],
    /*
    |--------------------------------------------------------------------------
    | Empty Values
    |--------------------------------------------------------------------------
    |
    | Should Audit records be stored when the recorded old_values & new_values
    | are both empty?
    |
    | Some events may be empty on purpose. Use allowed_empty_values to exclude
    | those from the empty values check. For example when auditing
    | model retrieved events which will never have new and old values.
    |
    |
    */

    'empty_values'         => true,
    'allowed_empty_values' => [
        'retrieved'
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Array Values
    |--------------------------------------------------------------------------
    |
    | Should the array values be audited?
    |
    | By default, array values are not allowed. This is to prevent performance
    | issues when storing large amounts of data. You can override this by
    | setting allow_array_values to true.
    */
    'allowed_array_values' => false,

    /*
    |--------------------------------------------------------------------------
    | Audit Timestamps
    |--------------------------------------------------------------------------
    |
    | Should the created_at, updated_at and deleted_at timestamps be audited?
    |
    */

    'timestamps' => false,

    /*
    |--------------------------------------------------------------------------
    | Audit Threshold
    |--------------------------------------------------------------------------
    |
    | Specify a threshold for the amount of Audit records a model can have.
    | Zero means no limit.
    |
    */

    'threshold' => 0,

    /*
    |--------------------------------------------------------------------------
    | Audit Driver
    |--------------------------------------------------------------------------
    |
    | The default audit driver used to keep track of changes.
    |
    */

    'driver' => 'database',

    /*
    |--------------------------------------------------------------------------
    | Audit Driver Configurations
    |--------------------------------------------------------------------------
    |
    | Available audit drivers and respective configurations.
    |
    */

    'drivers' => [
        'database' => [
            'table'      => 'audits',
            'connection' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Queue Configurations
    |--------------------------------------------------------------------------
    |
    | Available audit queue configurations.
    |
    */

    'queue' => [
        'enable'     => false,
        'connection' => 'sync',
        'queue'      => 'default',
        'delay'      => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Console
    |--------------------------------------------------------------------------
    |
    | Whether console events should be audited (eg. php artisan db:seed).
    |
    */

    'console' => true,
];
