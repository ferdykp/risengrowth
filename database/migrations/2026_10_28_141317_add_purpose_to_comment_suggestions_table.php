<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('comment_suggestions', function (Blueprint $table) {
            $table->string('purpose', 20)->default('comment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comment_suggestions', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
};
