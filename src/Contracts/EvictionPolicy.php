<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Vault\Contracts;

use Cline\Vault\Models\VaultEntry;

/**
 * @author Brian Faust <brian@cline.sh>
 */
interface EvictionPolicy
{
    public function shouldEvict(VaultEntry $entry): bool;

    public function getName(): string;
}
