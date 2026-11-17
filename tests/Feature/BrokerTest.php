<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BrokerTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->role = 'admin';
        $user->save();

        return $user;
    }

    private function broker(array $overrides = []): int
    {
        return DB::table('brokers')->insertGetId(array_replace([
            'name' => 'Example Broker', 'description' => 'Descrizione del broker di prova.',
            'affiliate_url' => 'https://broker.example/register?ref=archive&campaign=mvp',
            'image_path' => 'broker-images/original.png', 'is_active' => true, 'sort_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ], $overrides));
    }

    private function payload(): array
    {
        return ['name' => 'Nuovo Broker', 'description' => 'Descrizione fornita dall’amministratore.',
            'affiliate_url' => 'https://broker.example/register?ref=archive&campaign=mvp',
            'is_active' => '1', 'sort_order' => '10'];
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg=='));
    }

    public function test_guests_must_sign_in_to_view_brokers_and_manage_them(): void
    {
        $this->get('/brokers')->assertRedirect('/login');
        $this->get('/brokers/1/image')->assertRedirect('/login');
        $this->get('/admin/brokers')->assertRedirect('/login');
        $this->post('/admin/brokers', [])->assertRedirect('/login');
        $this->assertDatabaseCount('brokers', 0);
    }

    public static function nonAdminRoles(): array
    {
        return [['user'], ['developer']];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_cannot_manage_brokers(string $role): void
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();
        $id = $this->broker();
        $this->actingAs($user);

        $this->get('/admin/brokers')->assertForbidden();
        $this->get('/admin/brokers/create')->assertForbidden();
        $this->get('/admin/brokers/'.$id.'/edit')->assertForbidden();
        $this->post('/admin/brokers', $this->payload())->assertForbidden();
        $this->put('/admin/brokers/'.$id, $this->payload())->assertForbidden();
        $this->delete('/admin/brokers/'.$id)->assertForbidden();

        $this->assertDatabaseHas('brokers', ['id' => $id, 'name' => 'Example Broker']);
        $this->assertDatabaseCount('brokers', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_users_see_only_active_brokers_in_admin_order_with_affiliate_links(): void
    {
        $this->broker(['name' => 'Later Broker', 'sort_order' => 20]);
        $this->broker(['name' => 'First Broker', 'sort_order' => 1]);
        $this->broker(['name' => 'Hidden Broker', 'is_active' => false]);

        $response = $this->actingAs(User::factory()->create())->get('/brokers');

        $response->assertOk()->assertSeeInOrder(['First Broker', 'Later Broker'])->assertDontSee('Hidden Broker');
        $response->assertSee('https://broker.example/register?ref=archive&campaign=mvp');
        $response->assertSee('rel="sponsored noopener noreferrer"', false)->assertSee('link di affiliazione');
    }

    public function test_users_cannot_read_hidden_broker_images_but_admin_can(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('broker-images/original.png', 'image content');
        $id = $this->broker(['is_active' => false]);

        $this->actingAs(User::factory()->create())->get('/brokers/'.$id.'/image')->assertNotFound();
        $this->actingAs($this->adminUser())->get('/brokers/'.$id.'/image')->assertOk();
    }

    public function test_active_broker_image_is_available_to_users_and_missing_image_returns_404(): void
    {
        Storage::fake('local');
        $id = $this->broker();
        Storage::disk('local')->put('broker-images/original.png', 'image content');

        $this->actingAs(User::factory()->create())->get('/brokers/'.$id.'/image')->assertOk();
        Storage::disk('local')->delete('broker-images/original.png');
        $this->get('/brokers/'.$id.'/image')->assertNotFound();
    }

    public function test_admin_can_create_a_broker_with_image_and_audit_record(): void
    {
        Storage::fake('local');
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post('/admin/brokers', $this->payload() + ['image' => $this->image(), 'image_path' => 'untrusted.png']);

        $response->assertRedirect('/admin/brokers');
        $record = DB::table('brokers')->first();
        $this->assertDatabaseHas('brokers', ['id' => $record->id, 'name' => 'Nuovo Broker', 'is_active' => true, 'sort_order' => 10]);
        $this->assertNotSame('untrusted.png', $record->image_path);
        Storage::disk('local')->assertExists($record->image_path);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'broker.created', 'subject' => 'brokers:'.$record->id]);
    }

    public function test_admin_can_update_and_hide_a_broker_without_replacing_image(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('broker-images/original.png', 'original');
        $id = $this->broker();

        $response = $this->actingAs($this->adminUser())->put('/admin/brokers/'.$id, array_replace($this->payload(), ['is_active' => '0']));

        $response->assertRedirect('/admin/brokers');
        $this->assertDatabaseHas('brokers', ['id' => $id, 'name' => 'Nuovo Broker', 'is_active' => false, 'image_path' => 'broker-images/original.png']);
        Storage::disk('local')->assertExists('broker-images/original.png');
        $this->assertDatabaseHas('audit_logs', ['action' => 'broker.updated', 'subject' => 'brokers:'.$id]);
        $this->get('/brokers')->assertDontSee('Nuovo Broker');
    }

    public function test_replacing_image_removes_the_previous_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('broker-images/original.png', 'original');
        $id = $this->broker();

        $response = $this->actingAs($this->adminUser())->put('/admin/brokers/'.$id, $this->payload() + ['image' => $this->image()]);

        $response->assertRedirect('/admin/brokers');
        Storage::disk('local')->assertMissing('broker-images/original.png');
        Storage::disk('local')->assertExists(DB::table('brokers')->where('id', $id)->value('image_path'));
    }

    public function test_admin_can_delete_broker_and_image_with_audit_record(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('broker-images/original.png', 'original');
        $id = $this->broker();

        $response = $this->actingAs($this->adminUser())->delete('/admin/brokers/'.$id);

        $response->assertRedirect('/admin/brokers');
        $this->assertDatabaseMissing('brokers', ['id' => $id]);
        Storage::disk('local')->assertMissing('broker-images/original.png');
        $this->assertDatabaseHas('audit_logs', ['action' => 'broker.deleted', 'subject' => 'brokers:'.$id]);
    }

    public static function unsafeLinks(): array
    {
        return [['javascript:alert(1)'], ['http://broker.example/register'], ['https://user:password@broker.example/register']];
    }

    #[DataProvider('unsafeLinks')]
    public function test_admin_cannot_save_unsafe_registration_link(string $url): void
    {
        $id = $this->broker();

        $response = $this->actingAs($this->adminUser())->put('/admin/brokers/'.$id, array_replace($this->payload(), ['affiliate_url' => $url]));

        $response->assertSessionHasErrors('affiliate_url');
        $this->assertDatabaseHas('brokers', ['id' => $id, 'name' => 'Example Broker']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_creation_requires_metadata_image_and_valid_order(): void
    {
        $this->actingAs($this->adminUser())->post('/admin/brokers', [])->assertSessionHasErrors(['name', 'description', 'affiliate_url', 'image', 'is_active', 'sort_order']);
        $this->assertDatabaseCount('brokers', 0);
        $id = $this->broker();

        $this->put('/admin/brokers/'.$id, array_replace($this->payload(), ['sort_order' => -1, 'is_active' => 5]))->assertSessionHasErrors(['sort_order', 'is_active']);
        $this->assertDatabaseHas('brokers', ['id' => $id, 'sort_order' => 0, 'is_active' => true]);
    }

    public function test_non_image_upload_is_rejected_without_creating_files_or_records(): void
    {
        Storage::fake('local');

        $response = $this->actingAs($this->adminUser())->post('/admin/brokers', $this->payload() + ['image' => UploadedFile::fake()->createWithContent('logo.svg', '<svg><script>alert(1)</script></svg>')]);

        $response->assertSessionHasErrors('image');
        $this->assertDatabaseCount('brokers', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_admin_forms_and_empty_user_page_render(): void
    {
        $this->actingAs($this->adminUser())->get('/admin/brokers')->assertOk();
        $this->get('/admin/brokers/create')->assertOk();
        $this->get('/brokers')->assertOk()->assertSee('Nessun broker disponibile');
        $id = $this->broker();
        $this->get('/admin/brokers/'.$id.'/edit')->assertOk()->assertSee('Example Broker');
        $this->get('/admin/brokers/999999/edit')->assertNotFound();
    }

    public function test_broker_text_is_escaped(): void
    {
        $this->broker(['name' => '<script>alert(1)</script>', 'description' => '<img src=x onerror=alert(2)>']);

        $response = $this->actingAs(User::factory()->create())->get('/brokers');

        $response->assertOk()->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('<img src=x onerror=alert(2)>')->assertDontSee('<img src=x onerror=alert(2)>', false);
    }
}
