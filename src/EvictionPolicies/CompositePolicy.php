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

use function array_all;
use function array_any;
use function array_map;
use function implode;
use function mb_strtolower;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class CompositePolicy implements EvictionPolicy
{
    /**
     * @param array<EvictionPolicy> $policies
     */
    public function __construct(
        private array $policies,
        private string $operator = 'OR',
    ) {}

    public function shouldEvict(VaultEntry $entry): bool
    {
        if ($this->operator === 'AND') {
            return array_all($this->policies, fn ($policy): bool => $policy->shouldEvict($entry));
        }

        return array_any($this->policies, fn ($policy): bool => $policy->shouldEvict($entry));
    }

    public function getName(): string
    {
        $names = array_map(fn (EvictionPolicy $p): string => $p->getName(), $this->policies);

        return 'composite_'.mb_strtolower($this->operator).'_'.implode('_', $names);
    }
}
