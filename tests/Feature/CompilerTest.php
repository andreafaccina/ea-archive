<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompilerTest extends TestCase
{
    use RefreshDatabase;

    private function setupBuild(): int
    {
        config(['archive.compiler_token' => str_repeat('a', 48), 'archive.files_disk' => 's3']);
        Storage::fake('s3');
        $user = User::factory()->create();
        $account = DB::table('broker_accounts')->insertGetId(['user_id' => $user->id, 'login' => '12345', 'server' => 'Broker-Live', 'status' => 'verified', 'created_at' => now(), 'updated_at' => now()]);
        $source = 'int EA_OnInit(){return 0;} void EA_OnTick(){}';
        Storage::disk('s3')->put('sources/one.mq5', $source);
        $expert = DB::table('experts')->insertGetId(['user_id' => $user->id, 'name' => 'External EA', 'description' => 'Reviewed EA source', 'strategy' => 'Trend', 'symbols' => 'EURUSD', 'timeframe' => 'H1', 'risk' => 'Medio', 'version' => '1', 'status' => 'published', 'source_disk' => 's3', 'source_path' => 'sources/one.mq5', 'source_sha256' => hash('sha256', $source), 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('builds')->insertGetId(['user_id' => $user->id, 'expert_id' => $expert, 'broker_account_id' => $account, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function claim(): ?array
    {
        return $this->withToken(str_repeat('a', 48))->postJson('/api/v1/compiler/claim')->assertOk()->json('build');
    }

    private function artifactPayload(): array
    {
        return ['artifact' => UploadedFile::fake()->createWithContent('expert.ex5', 'compiled-test-content'), 'sha256' => hash('sha256', 'compiled-test-content')];
    }

    public function test_legacy_compiling_build_without_lease_can_be_recovered(): void
    {
        $id = $this->setupBuild();
        DB::table('builds')->where('id', $id)->update(['status' => 'compiling']);

        $job = $this->claim();

        $this->assertSame($id, $job['id']);
        $this->assertDatabaseHas('builds', ['id' => $id, 'attempts' => 1, 'status' => 'compiling']);
    }

    public function test_compiler_requires_configured_secret_not_user_session(): void
    {
        $this->setupBuild();
        $this->actingAs(User::factory()->create())->postJson('/api/v1/compiler/claim')->assertUnauthorized();
        $this->withToken('wrong')->postJson('/api/v1/compiler/claim')->assertUnauthorized();
        config(['archive.compiler_token' => null]);
        $this->postJson('/api/v1/compiler/claim')->assertStatus(503);
    }

    public function test_worker_claims_one_job_and_reads_only_with_lease_token(): void
    {
        $id = $this->setupBuild();

        $job = $this->claim();

        $this->assertSame($id, $job['id']);
        $this->assertSame('12345', $job['login']);
        $this->assertSame('Broker-Live', $job['server']);
        $this->assertSame(64, strlen($job['token']));
        $this->assertDatabaseHas('builds', ['id' => $id, 'status' => 'compiling', 'lease_hash' => hash('sha256', $job['token']), 'source_disk' => 's3']);
        $this->withHeader('X-Build-Token', 'wrong')->get('/api/v1/compiler/builds/'.$id.'/source')->assertForbidden();
        $this->withHeader('X-Build-Token', $job['token'])->get('/api/v1/compiler/builds/'.$id.'/source')->assertOk()->assertDownload('expert.mq5');
        $this->assertNull($this->claim());
    }

    public function test_completed_artifact_is_private_remote_and_repeat_is_idempotent(): void
    {
        $id = $this->setupBuild();
        $job = $this->claim();
        $this->withHeader('X-Build-Token', $job['token'])->post('/api/v1/compiler/builds/'.$id.'/complete', $this->artifactPayload())->assertOk()->assertJson(['status' => 'ready']);

        $build = DB::table('builds')->find($id);
        $this->assertSame('s3', $build->artifact_disk);
        Storage::disk('s3')->assertExists($build->artifact_path);
        $this->assertSame(hash('sha256', 'compiled-test-content'), $build->artifact_sha256);
        $this->post('/api/v1/compiler/builds/'.$id.'/complete', $this->artifactPayload())->assertOk();
        $this->assertCount(1, Storage::disk('s3')->allFiles('artifacts'));
        $owner = User::findOrFail($build->user_id);
        $this->actingAs($owner)->get('/builds/'.$id.'/download')->assertOk()->assertDownload();
        $this->actingAs(User::factory()->create())->get('/builds/'.$id.'/download')->assertNotFound();
    }

    public function test_expired_lease_can_be_reclaimed_but_old_worker_cannot_complete(): void
    {
        $id = $this->setupBuild();
        $first = $this->claim();
        $this->travel(11)->minutes();

        $second = $this->claim();

        $this->assertNotSame($first['token'], $second['token']);
        $this->withHeader('X-Build-Token', $first['token'])->post('/api/v1/compiler/builds/'.$id.'/complete', $this->artifactPayload())->assertForbidden();
        $this->withHeader('X-Build-Token', $second['token'])->post('/api/v1/compiler/builds/'.$id.'/complete', $this->artifactPayload())->assertOk();
        $this->assertDatabaseHas('builds', ['id' => $id, 'attempts' => 2, 'status' => 'ready']);
    }

    public function test_expired_final_attempt_is_failed_instead_of_reclaimed_forever(): void
    {
        $id = $this->setupBuild();
        $this->claim();
        DB::table('builds')->where('id', $id)->update(['attempts' => 3]);
        $this->travel(11)->minutes();

        $this->assertNull($this->claim());
        $this->assertDatabaseHas('builds', ['id' => $id, 'status' => 'failed', 'error' => 'compilation.failed']);
    }

    public function test_unverified_account_and_unpublished_expert_are_not_compiled(): void
    {
        $id = $this->setupBuild();
        DB::table('broker_accounts')->update(['status' => 'rejected']);

        $this->assertNull($this->claim());
        $this->assertDatabaseHas('builds', ['id' => $id, 'status' => 'failed']);
    }

    public function test_revoked_account_cannot_complete_and_corrupt_artifact_is_rejected(): void
    {
        $id = $this->setupBuild();
        $job = $this->claim();
        $this->withHeader('X-Build-Token', $job['token'])->post('/api/v1/compiler/builds/'.$id.'/complete', array_replace($this->artifactPayload(), ['sha256' => str_repeat('0', 64)]))->assertUnprocessable();
        DB::table('broker_accounts')->update(['status' => 'rejected']);
        $this->post('/api/v1/compiler/builds/'.$id.'/complete', $this->artifactPayload())->assertConflict();
        $this->assertDatabaseHas('builds', ['id' => $id, 'status' => 'compiling', 'artifact_path' => null]);
        $this->assertSame([], Storage::disk('s3')->allFiles('artifacts'));
    }

    public function test_failure_callback_does_not_expose_worker_supplied_error(): void
    {
        $id = $this->setupBuild();
        $job = $this->claim();

        $this->withHeader('X-Build-Token', $job['token'])->postJson('/api/v1/compiler/builds/'.$id.'/fail', ['error' => 'secret source contents'])->assertOk();

        $this->assertDatabaseHas('builds', ['id' => $id, 'status' => 'failed', 'error' => 'compilation.failed']);
    }

    public function test_storage_copy_preserves_original_and_switches_reference_after_checksum(): void
    {
        $id = $this->setupBuild();
        Storage::fake('local');
        $build = DB::table('builds')->find($id);
        DB::table('experts')->where('id', $build->expert_id)->update(['source_disk' => 'local', 'source_path' => 'sources/legacy.mq5']);
        Storage::disk('local')->put('sources/legacy.mq5', 'reviewed legacy source');

        $this->artisan('archive:copy-ea-storage', ['disk' => 's3'])->assertSuccessful();
        $this->assertDatabaseHas('experts', ['id' => $build->expert_id, 'source_disk' => 'local']);
        $this->artisan('archive:copy-ea-storage', ['disk' => 's3', '--apply' => true])->assertSuccessful();

        $this->assertDatabaseHas('experts', ['id' => $build->expert_id, 'source_disk' => 's3', 'source_sha256' => hash('sha256', 'reviewed legacy source')]);
        Storage::disk('local')->assertExists('sources/legacy.mq5');
        Storage::disk('s3')->assertExists('sources/legacy.mq5');
    }

    public function test_developer_upload_uses_external_disk_and_admin_source_download_reads_it(): void
    {
        $this->setupBuild();
        $developer = User::factory()->create();
        $developer->role = 'developer';
        $developer->save();
        $payload = ['name' => 'Uploaded EA', 'description' => 'A long description for the uploaded expert advisor.', 'strategy' => 'Trend', 'symbols' => 'EURUSD', 'timeframe' => 'H1', 'risk' => 'Medio', 'version' => '1', 'source' => UploadedFile::fake()->createWithContent('source.mq5', 'int EA_OnInit(){return 0;} void EA_OnTick(){}')];

        $this->actingAs($developer)->post('/developer/experts', $payload)->assertRedirect();

        $expert = DB::table('experts')->where('name', 'Uploaded EA')->first();
        $this->assertSame('s3', $expert->source_disk);
        $this->assertSame('pending', $expert->status);
        Storage::disk('s3')->assertExists($expert->source_path);
        $this->get('/admin/experts/'.$expert->id.'/source')->assertForbidden();
        $developer->role = 'admin';
        $developer->save();
        $this->get('/admin/experts/'.$expert->id.'/source')->assertDownload();
    }

    public function test_expired_token_cannot_read_or_submit_artifacts_without_reclaim(): void
    {
        $id = $this->setupBuild();
        $job = $this->claim();
        $this->travel(11)->minutes();

        $this->withHeader('X-Build-Token', $job['token'])->get('/api/v1/compiler/builds/'.$id.'/source')->assertConflict();
        $this->post('/api/v1/compiler/builds/'.$id.'/complete', $this->artifactPayload())->assertConflict();
        $this->assertDatabaseHas('builds', ['id' => $id, 'artifact_path' => null]);
    }
}
