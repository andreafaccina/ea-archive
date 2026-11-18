<?php

namespace Tests\Feature;

use App\Jobs\CompileExpert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_email_cannot_link_broker_account(): void
    {
        $u = User::factory()->unverified()->create();
        $this->actingAs($u)->post('/broker-accounts', ['login' => '12345', 'server' => 'Broker-Live'])->assertRedirect('/email/verify');
        $this->assertDatabaseCount('broker_accounts', 0);
    }

    public function test_password_reset_token_can_only_be_used_once(): void
    {
        $u = User::factory()->create();
        $token = Password::createToken($u);
        $data = ['email' => $u->email, 'token' => $token, 'password' => 'new-secure-password', 'password_confirmation' => 'new-secure-password'];
        $this->post('/reset-password', $data)->assertRedirect('/login');
        $this->assertTrue(Hash::check('new-secure-password', $u->fresh()->password));
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
    }

    private function expert(User $u, string $status = 'published'): int
    {
        return DB::table('experts')->insertGetId(['user_id' => $u->id, 'name' => 'Test EA', 'description' => 'A reviewed strategy with sufficient description.', 'strategy' => 'Trend', 'symbols' => 'EURUSD', 'timeframe' => 'H1', 'risk' => 'Medio', 'version' => '1', 'status' => $status, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function account(User $u, string $status = 'verified'): int
    {
        return DB::table('broker_accounts')->insertGetId(['user_id' => $u->id, 'login' => (string) random_int(10000, 99999999), 'server' => 'Broker-Live', 'status' => $status, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_roles_cannot_be_self_assigned(): void
    {
        $this->post('/register', ['name' => 'Alice', 'email' => 'alice@example.com', 'password' => 'a-secure-passphrase', 'password_confirmation' => 'a-secure-passphrase', 'role' => 'admin'])->assertRedirect('/account');
        $this->assertDatabaseHas('users', ['email' => 'alice@example.com', 'role' => 'user']);
    }

    public function test_users_cannot_access_developer_or_admin_areas(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/admin')->assertForbidden();
        $this->get('/developer')->assertForbidden();
        $this->post('/admin/moderate', ['type' => 'role', 'id' => $u->id, 'value' => 'developer'])->assertForbidden();
    }

    public function test_only_published_experts_are_public(): void
    {
        $u = User::factory()->create();
        $id = $this->expert($u, 'pending');
        $this->get('/')->assertOk()->assertDontSee('Test EA');
        $this->get('/experts/'.$id)->assertNotFound();
    }

    public function test_build_requires_own_verified_account(): void
    {
        Queue::fake();
        $u = User::factory()->create();
        $other = User::factory()->create();
        $id = $this->expert($other);
        $a = $this->account($other);
        $this->actingAs($u)->post('/experts/'.$id.'/builds', ['broker_account_id' => $a])->assertNotFound();
        $a = $this->account($u, 'pending');
        $this->post('/experts/'.$id.'/builds', ['broker_account_id' => $a])->assertNotFound();
        Queue::assertNothingPushed();
    }

    public function test_verified_account_queues_build_and_prevents_parallel_requests(): void
    {
        Queue::fake();
        $u = User::factory()->create();
        $id = $this->expert($u);
        $a = $this->account($u);
        $this->actingAs($u)->post('/experts/'.$id.'/builds', ['broker_account_id' => $a])->assertRedirect('/account');
        Queue::assertNothingPushed();
        $this->assertDatabaseHas('builds', ['user_id' => $u->id, 'status' => 'queued']);
        $this->post('/experts/'.$id.'/builds', ['broker_account_id' => $a])->assertStatus(422);
        $this->assertDatabaseCount('builds', 1);
    }

    public function test_download_is_private_and_review_requires_download(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $eid = $this->expert($owner);
        $aid = $this->account($owner);
        $bid = DB::table('builds')->insertGetId(['user_id' => $owner->id, 'expert_id' => $eid, 'broker_account_id' => $aid, 'status' => 'ready', 'artifact_path' => 'artifacts/test.ex5', 'created_at' => now(), 'updated_at' => now()]);
        Storage::disk('local')->put('artifacts/test.ex5', 'test');
        $this->actingAs($other)->get('/builds/'.$bid.'/download')->assertNotFound();
        $this->actingAs($owner)->post('/experts/'.$eid.'/reviews', ['rating' => 5, 'body' => 'A useful strategy review.'])->assertForbidden();
        $this->get('/builds/'.$bid.'/download')->assertDownload();
        $this->post('/experts/'.$eid.'/reviews', ['rating' => 5, 'body' => 'A useful strategy review.'])->assertRedirect();
        $this->assertDatabaseHas('reviews', ['user_id' => $owner->id, 'expert_id' => $eid, 'rating' => 5]);
    }

    public function test_admin_actions_are_audited(): void
    {
        $a = User::factory()->create();
        $a->role = 'admin';
        $a->save();
        $u = User::factory()->create();
        $this->actingAs($a)->post('/admin/moderate', ['type' => 'role', 'id' => $u->id, 'value' => 'developer'])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $u->id, 'role' => 'developer']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $a->id, 'subject' => 'users:'.$u->id]);
    }

    public function test_legacy_job_leaves_build_queued_for_external_compiler(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        $eid = $this->expert($u);
        $aid = $this->account($u);
        $bid = DB::table('builds')->insertGetId(['user_id' => $u->id, 'expert_id' => $eid, 'broker_account_id' => $aid, 'created_at' => now(), 'updated_at' => now()]);
        config(['archive.compiler' => null]);
        (new CompileExpert($bid))->handle();
        $this->assertDatabaseHas('builds', ['id' => $bid, 'status' => 'queued', 'artifact_path' => null]);
    }

    public function test_workspace_pages_render_for_authorized_roles(): void
    {
        $u = User::factory()->create();
        $u->role = 'admin';
        $u->save();
        $eid = $this->expert($u);
        $this->actingAs($u)->get('/account')->assertOk();
        $this->get('/developer')->assertOk();
        $this->get('/admin')->assertOk();
        $this->get('/experts/'.$eid)->assertOk();
        $this->get('/')->assertOk()->assertSee('Test EA');
    }
}
