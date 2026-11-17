<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->string('role')->default('user')->index());
        Schema::create('broker_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('login', 20);
            $t->string('server', 120);
            $t->string('status')->default('pending');
            $t->foreignId('verified_by')->nullable()->constrained('users');
            $t->timestamps();
            $t->unique(['login', 'server']);
        });
        Schema::create('experts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('name');
            $t->text('description');
            $t->string('strategy');
            $t->string('symbols');
            $t->string('timeframe');
            $t->string('risk');
            $t->string('version', 30);
            $t->string('source_path')->nullable();
            $t->string('image_path')->nullable();
            $t->string('status')->default('pending')->index();
            $t->timestamps();
        });
        Schema::create('builds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('expert_id')->constrained();
            $t->foreignId('broker_account_id')->constrained();
            $t->string('status')->default('queued')->index();
            $t->string('artifact_path')->nullable();
            $t->text('error')->nullable();
            $t->timestamp('downloaded_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'created_at']);
        });
        Schema::create('reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('expert_id')->constrained();
            $t->unsignedTinyInteger('rating');
            $t->text('body');
            $t->timestamps();
            $t->unique(['user_id', 'expert_id']);
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('action');
            $t->string('subject');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'reviews', 'builds', 'experts', 'broker_accounts'] as $t) {
            Schema::dropIfExists($t);
        }Schema::table('users', fn (Blueprint $t) => $t->dropColumn('role'));
    }
};
