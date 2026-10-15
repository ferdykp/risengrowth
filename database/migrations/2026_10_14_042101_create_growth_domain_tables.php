<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('platform', 32);
            $table->string('external_account_id', 191);
            $table->string('username', 191)->nullable();
            $table->string('status', 32)->default('disconnected');
            $table->text('access_token_encrypted')->nullable();
            $table->text('refresh_token_encrypted')->nullable();
            $table->json('granted_scopes')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'platform', 'external_account_id'], 'social_account_identity_unique');
            $table->index(['platform', 'status']);
        });

        Schema::create('search_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('platform', 32)->default('threads');
            $table->string('phrase', 191);
            $table->string('normalized_phrase', 191);
            $table->string('category', 40)->default('direct_demand');
            $table->string('search_type', 16)->default('RECENT');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('interval_minutes')->nullable();
            $table->timestamp('last_ran_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            // Uniqueness is across active AND soft-deleted records; restore/reactivate instead of recreating.
            $table->unique(['user_id', 'platform', 'normalized_phrase', 'category'], 'keyword_identity_unique');
            $table->index(['enabled', 'last_ran_at']);
        });

        Schema::create('discovery_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('search_keyword_id')->constrained()->restrictOnDelete();
            $table->uuid('run_uuid')->unique();
            $table->string('status', 32)->default('queued');
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('added_count')->default(0);
            $table->unsignedInteger('relevant_count')->default(0);
            $table->string('error_code', 100)->nullable();
            $table->text('error_summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['search_keyword_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('discovered_posts', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 32);
            $table->string('external_post_id', 191);
            $table->string('username', 191)->nullable();
            $table->longText('body')->nullable();
            $table->text('permalink')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('first_discovered_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['platform', 'external_post_id'], 'post_platform_external_unique');
            $table->index(['platform', 'posted_at']);
        });

        Schema::create('discovery_run_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discovery_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discovered_post_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['discovery_run_id', 'discovered_post_id'], 'run_post_unique');
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discovered_post_id')->unique()->constrained()->restrictOnDelete();
            $table->string('intent', 32)->default('unclassified');
            $table->string('service_category', 48)->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->string('priority', 16)->default('unscored');
            $table->string('status', 24)->default('new');
            $table->text('rationale')->nullable();
            $table->boolean('manually_overridden')->default(false);
            $table->text('operator_notes')->nullable();
            $table->timestamps();
            $table->index(['priority', 'score']);
            $table->index(['status', 'created_at']);
            $table->index('intent');
        });

        Schema::create('ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discovered_post_id')->constrained()->restrictOnDelete();
            $table->string('provider', 48);
            $table->string('model', 100);
            $table->string('prompt_version', 40);
            $table->string('status', 24)->default('pending');
            $table->json('output')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();
            $table->index(['discovered_post_id', 'created_at']);
        });

        Schema::create('comment_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->string('variant_key', 32)->default('friendly');
            $table->unsignedInteger('revision')->default(1);
            $table->longText('body');
            $table->char('content_sha256', 64);
            $table->string('status', 32)->default('draft');
            $table->timestamps();
            $table->unique(['lead_id', 'variant_key', 'revision'], 'draft_variant_revision_unique');
            $table->index(['status', 'created_at']);
        });

        Schema::create('comment_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_draft_id')->constrained()->restrictOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 16); // approved | rejected
            $table->char('approved_sha256', 64)->nullable();
            $table->string('approved_target_post_id', 191)->nullable();
            $table->string('approved_platform', 32)->nullable();
            $table->text('approved_body_snapshot')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->index(['comment_draft_id', 'decided_at']);
        });

        Schema::create('engagement_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_draft_id')->constrained()->restrictOnDelete();
            $table->foreignId('comment_approval_id')->constrained()->restrictOnDelete();
            $table->foreignId('social_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('triggered_by')->constrained('users')->restrictOnDelete();
            $table->string('method', 24); // manual | api
            $table->string('status', 32)->default('pending');
            $table->uuid('dispatch_key')->unique();
            $table->string('target_external_post_id', 191);
            $table->char('approved_sha256', 64);
            $table->text('approved_body_snapshot');
            $table->string('provider_reply_id', 191)->nullable();
            $table->text('provider_receipt')->nullable(); // sanitized, no secrets
            $table->text('failure_summary')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['social_account_id', 'created_at']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 100);
            $table->json('metadata')->nullable(); // sanitized only
            $table->timestamps();
            $table->index(['event', 'created_at']);
            $table->index(['lead_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (
            [
                'activity_logs',
                'engagement_attempts',
                'comment_approvals',
                'comment_drafts',
                'ai_analyses',
                'leads',
                'discovery_run_posts',
                'discovered_posts',
                'discovery_runs',
                'search_keywords',
                'social_accounts',
            ] as $name
        ) {
            Schema::dropIfExists($name);
        }
    }
};
