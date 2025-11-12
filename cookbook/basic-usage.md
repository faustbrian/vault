# Basic Usage

## Storing and Retrieving Values

```php
use Cline\Vault\Facades\Vault;

// Store a value
Vault::put('api_key', 'secret-key-123');

// Retrieve a value
$apiKey = Vault::get('api_key'); // 'secret-key-123'

// Check if exists
if (Vault::has('api_key')) {
    // ...
}

// Remove a value
Vault::forget('api_key');
```

## Owner-Scoped Storage

Store the same key for different owners using polymorphic relationships:

```php
$user1 = User::find(1);
$user2 = User::find(2);

// Each user gets their own isolated value
Vault::put('api_token', 'user1-token', $user1);
Vault::put('api_token', 'user2-token', $user2);

Vault::get('api_token', $user1); // 'user1-token'
Vault::get('api_token', $user2); // 'user2-token'
```

## Without Owner

Values can also be stored globally without an owner:

```php
// Global value
Vault::put('system_config', ['mode' => 'production']);

// Retrieve global value
$config = Vault::get('system_config'); // ['mode' => 'production']
```
