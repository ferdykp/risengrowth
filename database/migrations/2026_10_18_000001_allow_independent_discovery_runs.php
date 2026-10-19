<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discovery_runs', function (Blueprint $table) {
            $table->foreignId('social_account_id')->nullable()->change();
            $table->string('source_type', 32)->default('threads')->after('social_account_id');
        });
    }

    public function down(): void
    {
        // Old runs may have no Threads account. Preserve data rather than forcing NOT NULL.
        Schema::table('discovery_runs', function (Blueprint $table) {
            $table->dropColumn('source_type');
        });
    }
};
