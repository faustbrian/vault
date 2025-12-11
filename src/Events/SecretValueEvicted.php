<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Vault\Events;

use Cline\Vault\Models\VaultEntry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class SecretValueEvicted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public VaultEntry $entry,
    ) {}
}
