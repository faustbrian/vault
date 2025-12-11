<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Vault;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;
use Override;

use function class_exists;
use function config;
use function config_path;
use function database_path;
use function is_array;
use function is_string;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class VaultServiceProvider extends ServiceProvider
{
    #[Override()]
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/vault.php',
            'vault',
        );

        $this->app->singleton(function (Container $app): ValueTypeRegistry {
            $registry = new ValueTypeRegistry();

            /** @var mixed $valueTypes */
            $valueTypes = config('vault.value_types', []);

            if (is_array($valueTypes)) {
                foreach ($valueTypes as $valueType) {
                    if (!is_string($valueType) || !class_exists($valueType)) {
                        continue;
                    }

                    /** @var class-string<Contracts\SecretValue> $valueType */
                    $registry->register($valueType);
                }
            }

            return $registry;
        });

        $this->app->singleton(function (Container $app): Vault {
            /** @var ValueTypeRegistry $registry */
            $registry = $app->make(ValueTypeRegistry::class);

            return new Vault($registry);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/vault.php' => config_path('vault.php'),
            ], 'vault-config');

            $this->publishes([
                __DIR__.'/../database/migrations/create_vault_entries_table.php' => database_path('migrations/'.Date::now()->format('Y_m_d_His').'_create_vault_entries_table.php'),
            ], 'vault-migrations');
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
