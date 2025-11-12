# Value Types

Vault automatically detects and encrypts different value types using the type resolution system.

## Built-in Types

### String Values

```php
Vault::put('api_key', 'sk-1234567890');
$key = Vault::get('api_key'); // string
```

### Integer Values

```php
Vault::put('max_attempts', 5);
$attempts = Vault::get('max_attempts'); // int: 5
```

### Array Values

```php
Vault::put('permissions', ['read', 'write', 'delete']);
$perms = Vault::get('permissions'); // array
```

### JSON Values

```php
Vault::put('config', ['feature_flags' => ['new_ui' => true]]);
$config = Vault::get('config'); // array (stored as JSON)
```

## Creating Custom Value Types

Implement the `SecretValue` interface:

```php
use Cline\Vault\Contracts\SecretValue;

final class CreditCardValue implements SecretValue
{
    private string $number;

    public static function supports(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{16}$/', $value);
    }

    public static function priority(): int
    {
        return 100; // Higher = checked first
    }

    public function set(mixed $value): void
    {
        $this->number = $value;
    }

    public function get(): string
    {
        return $this->number;
    }

    public function encrypt(mixed $value, string $key): string
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt(
            (string) $value,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return base64_encode($iv.$tag.$encrypted);
    }

    public function decrypt(string $encrypted, string $key): string
    {
        $data = base64_decode($encrypted);
        $iv = substr($data, 0, 16);
        $tag = substr($data, 16, 16);
        $ciphertext = substr($data, 32);

        $decrypted = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed');
        }

        return $decrypted;
    }

    public function serialize(): string
    {
        return $this->number;
    }

    public static function unserialize(string $data): static
    {
        $instance = new self;
        $instance->set($data);

        return $instance;
    }
}
```

## Registering Custom Types

In your service provider:

```php
use Cline\Vault\ValueTypeRegistry;

public function boot(): void
{
    $registry = app(ValueTypeRegistry::class);
    $registry->register(CreditCardValue::class);
}
```

## Type Priority

Types are checked in priority order (highest first). Built-in priorities:

- `JsonValue`: 5
- `ArrayValue`: 8
- `IntValue`: 10
- `StringValue`: 10 (fallback for most strings)

Set higher priority for more specific types to override defaults.
