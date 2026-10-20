<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discovery_provider_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('enabled')->default(false);
            $table->boolean('automation_enabled')->default(false);
            $table->unsignedSmallInteger('daily_runs')->default(1);
            $table->unsignedInteger('min_interval_minutes')->default(360);
            $table->timestamps();
        });
        DB::table('discovery_provider_settings')->insert(['id' => 1, 'enabled' => false, 'automation_enabled' => false,
            'daily_runs' => 1, 'min_interval_minutes' => 360, 'created_at' => now(), 'updated_at' => now()]);
        Schema::table('discovery_runs', function (Blueprint $table): void {
            $table->string('keyword_snapshot', 191)->nullable();
            $table->string('provider_run_id', 64)->nullable()->unique();
            $table->string('provider_dataset_id', 64)->nullable();
            $table->timestamp('provider_attempted_at')->nullable();
            $table->timestamp('provider_poll_after')->nullable();
            $table->unsignedSmallInteger('provider_poll_count')->default(0);
            $table->decimal('provider_usage_usd', 12, 6)->nullable();
            $table->unsignedInteger('excluded_own_count')->default(0);
            $table->unsignedInteger('uncertain_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('discovery_runs', function (Blueprint $table): void {
            $table->dropUnique(['provider_run_id']);
            $table->dropColumn(['keyword_snapshot', 'provider_run_id', 'provider_dataset_id', 'provider_attempted_at',
                'provider_poll_after', 'provider_poll_count', 'provider_usage_usd', 'excluded_own_count', 'uncertain_count', 'rejected_count']);
        });
        Schema::dropIfExists('discovery_provider_settings');
    }
};
