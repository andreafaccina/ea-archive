<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('brokers', function (Blueprint $table) {
            $table->text('image_url')->nullable();
            $table->string('image_path')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('brokers')->whereNull('image_path')->exists()) {
            throw new RuntimeException('Replace remote broker images with uploads before rolling back this migration.');
        }
        Schema::table('brokers', function (Blueprint $table) {
            $table->dropColumn('image_url');
            $table->string('image_path')->nullable(false)->change();
        });
    }
};
