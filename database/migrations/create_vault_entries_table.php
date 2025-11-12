<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vault_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key')->index();
            $table->nullableUlidMorphs('owner');
            $table->string('value_type');
            $table->text('encrypted_value');
            $table->string('encryption_key_id')->default('default');
            $table->unsignedBigInteger('access_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->string('eviction_policy')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['key', 'owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_entries');
    }
};
