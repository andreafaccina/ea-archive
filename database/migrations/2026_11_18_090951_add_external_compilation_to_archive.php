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
        Schema::table('users', fn (Blueprint $table) => $table->string('locale', 5)->nullable());
        Schema::table('experts', function (Blueprint $table) {
            $table->string('source_disk')->default('local');
            $table->char('source_sha256', 64)->nullable();
        });
        Schema::table('builds', function (Blueprint $table) {
            $table->string('artifact_disk')->default('local');
            $table->char('artifact_sha256', 64)->nullable();
            $table->char('lease_hash', 64)->nullable();
            $table->timestamp('lease_expires_at')->nullable()->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('source_disk')->nullable();
            $table->string('source_path')->nullable();
            $table->char('source_sha256', 64)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('builds', function (Blueprint $table) {
            $table->dropIndex(['lease_expires_at']);
            $table->dropColumn(['artifact_disk', 'artifact_sha256', 'lease_hash', 'lease_expires_at', 'attempts', 'source_disk', 'source_path', 'source_sha256']);
        });
        Schema::table('experts', fn (Blueprint $table) => $table->dropColumn(['source_disk', 'source_sha256']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('locale'));
    }
};
