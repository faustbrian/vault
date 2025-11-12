<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Encryption Key
    |--------------------------------------------------------------------------
    |
    | This value determines which encryption key will be used by default when
    | storing secret values in the vault. You can define multiple keys below.
    |
    */

    'default_encryption_key' => env('VAULT_DEFAULT_KEY', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Keys
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the encryption keys for your vault. Each key
    | should be a strong, random 32-byte string. You can generate secure keys
    | using: base64_encode(random_bytes(32))
    |
    */

    'encryption_keys' => [
        'default' => env('VAULT_ENCRYPTION_KEY', env('APP_KEY')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Track Access
    |--------------------------------------------------------------------------
    |
    | Determines whether the vault should track access to secret values. This
    | includes incrementing access counts and updating last accessed timestamps.
    | Disable this for better performance if you don't need access tracking.
    |
    */

    'track_access' => env('VAULT_TRACK_ACCESS', true),

    /*
    |--------------------------------------------------------------------------
    | Default Eviction Policy
    |--------------------------------------------------------------------------
    |
    | The default eviction policy to use when storing values without explicitly
    | specifying one. Set to null to disable automatic eviction by default.
    |
    */

    'default_eviction_policy' => null,

    /*
    |--------------------------------------------------------------------------
    | Value Types
    |--------------------------------------------------------------------------
    |
    | The registered value type handlers. These are used to automatically
    | determine the appropriate encryption handler for different value types.
    | Higher priority values are checked first.
    |
    */

    'value_types' => [
        \Cline\Vault\ValueTypes\IntValue::class,
        \Cline\Vault\ValueTypes\ArrayValue::class,
        \Cline\Vault\ValueTypes\JsonValue::class,
        \Cline\Vault\ValueTypes\StringValue::class,
    ],
];
