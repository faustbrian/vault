<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Vault;

use Cline\Vault\Contracts\SecretValue;
use Cline\Vault\Exceptions\ValueTypeNotFoundException;
use InvalidArgumentException;
use ReflectionClass;

use function class_exists;
use function sprintf;
use function throw_unless;
use function usort;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class ValueTypeRegistry
{
    /** @var array<class-string<SecretValue>> */
    private array $types = [];

    /**
     * @param class-string<SecretValue> $type
     */
    public function register(string $type): void
    {
        // Since $type is already class-string<SecretValue>, we know it implements SecretValue
        // Check if class exists and is a valid SecretValue implementation
        throw_unless(class_exists($type), InvalidArgumentException::class, sprintf('Class %s does not exist', $type));

        $reflection = new ReflectionClass($type);

        throw_unless($reflection->implementsInterface(SecretValue::class), InvalidArgumentException::class, 'Type must implement SecretValue interface');

        $this->types[] = $type;

        usort($this->types, fn ($a, $b): int => $b::priority() <=> $a::priority());
    }

    public function resolve(mixed $value): SecretValue
    {
        foreach ($this->types as $type) {
            if ($type::supports($value)) {
                $instance = new $type();
                $instance->set($value);

                return $instance;
            }
        }

        throw ValueTypeNotFoundException::forValue($value);
    }

    /**
     * @return class-string<SecretValue>
     */
    public function findType(mixed $value): string
    {
        foreach ($this->types as $type) {
            if ($type::supports($value)) {
                return $type;
            }
        }

        throw ValueTypeNotFoundException::forValue($value);
    }
}
