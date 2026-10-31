<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comment_suggestions', function (Blueprint $table): void {
            $table->timestamp('replied_at')->nullable();
        });
        Schema::create('relationships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('lead_id')->constrained()->restrictOnDelete();
            $table->string('category', 24)->default('community');
            $table->string('stage', 24)->default('discovered');
            $table->date('follow_up_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'lead_id']);
            $table->index(['user_id', 'stage', 'follow_up_on']);
        });
        Schema::create('content_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('topic', 191);
            $table->string('pillar', 32);
            $table->string('platform', 32);
            $table->text('body');
            $table->string('status', 24)->default('draft');
            $table->date('published_on')->nullable();
            $table->unsignedInteger('impressions')->nullable();
            $table->unsignedInteger('engagements')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_drafts');
        Schema::dropIfExists('relationships');
        Schema::table('comment_suggestions', function (Blueprint $table): void {
            $table->dropColumn('replied_at');
        });
    }
};
