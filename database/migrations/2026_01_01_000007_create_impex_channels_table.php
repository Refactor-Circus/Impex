<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impex_channels', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            // The name every recorded message carries, so it never changes
            // once traffic has crossed the channel.
            $table->string('name', 191)->unique();
            $table->string('direction', 16);
            $table->string('transport', 32);
            $table->string('status', 16)->default('active');
            // Secrets and tokens, encrypted at rest and never rendered.
            $table->text('credentials')->nullable();
            $table->json('options')->nullable();
            $table->string('body_policy', 16)->default('all');
            // Who the channel belongs to, such as the subscriber whose
            // endpoint it is. Null for channels the application defines.
            $table->string('owner_type', 191)->nullable();
            $table->string('owner_id', 191)->nullable();
            $table->timestamps();

            $table->index(['owner_type', 'owner_id']);
            $table->index(['direction', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impex_channels');
    }
};
