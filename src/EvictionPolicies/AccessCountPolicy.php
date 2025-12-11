<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Vault\EvictionPolicies;

use Cline\Vault\Contracts\EvictionPolicy;
use Cline\Vault\Models\VaultEntry;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class AccessCountPolicy implements EvictionPolicy
{
    public function __construct(
        private int $maxAccesses,
    ) {}

    public function shouldEvict(VaultEntry $entry): bool
    {
        return $entry->access_count >= $this->maxAccesses;
    }

    public function getName(): string
    {
        return 'access_count';
    }
}
