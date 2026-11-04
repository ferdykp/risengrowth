<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discovery_runs', function (Blueprint $table): void {
            $table->date('posted_from')->nullable();
            $table->date('posted_to')->nullable();
            $table->unsignedInteger('out_of_range_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('discovery_runs', function (Blueprint $table): void {
            $table->dropColumn(['posted_from', 'posted_to', 'out_of_range_count']);
        });
    }
};
