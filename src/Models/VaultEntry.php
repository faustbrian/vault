<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Vault\Models;

use Cline\Vault\Contracts\SecretValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int                       $access_count
 * @property Carbon                    $created_at
 * @property string                    $encrypted_value
 * @property string                    $encryption_key_id
 * @property null|string               $eviction_policy
 * @property string                    $id
 * @property string                    $key
 * @property null|Carbon               $last_accessed_at
 * @property array<string, mixed>      $metadata
 * @property null|string               $owner_id
 * @property null|string               $owner_type
 * @property Carbon                    $updated_at
 * @property class-string<SecretValue> $value_type
 *
 * @method static Collection<int, static> all()
 * @method static static|null             first()
 * @method static static                  updateOrCreate(array<string, mixed> $attributes, array<string, mixed> $values = [])
 * @method static Builder<static>         where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 */
final class VaultEntry extends Model
{
    use HasUlids;

    protected $fillable = [
        'key',
        'owner_type',
        'owner_id',
        'value_type',
        'encrypted_value',
        'encryption_key_id',
        'access_count',
        'last_accessed_at',
        'eviction_policy',
        'metadata',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'access_count' => 'integer',
        'last_accessed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
