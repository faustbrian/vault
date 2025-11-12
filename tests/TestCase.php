<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Vault\VaultServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;

use function base64_encode;
use function random_bytes;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            VaultServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app->make(Repository::class)->set('vault.encryption_keys.default', base64_encode(random_bytes(32)));
        $app->make(Repository::class)->set('vault.track_access', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Schema::create('users', function ($table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });
    }
}
