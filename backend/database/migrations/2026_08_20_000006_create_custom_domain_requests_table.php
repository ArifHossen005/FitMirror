<?php

use App\Enums\CustomDomainStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_domain_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // Globally unique: `tenants.custom_domain` is itself unique, so
            // two tenants must not be able to hold competing *pending*
            // claims on the same host either — the loser would only find
            // out after publishing DNS.
            $table->string('domain')->unique();
            // The value the tenant publishes as a TXT record at
            // _fitmirror-verification.{domain}. Random per request, never
            // rotated on a retry (see CustomDomainStatus::isRetryable()).
            $table->string('verification_token', 64);
            $table->string('status')->default(CustomDomainStatus::Pending->value);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_domain_requests');
    }
};
