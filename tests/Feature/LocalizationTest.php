<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_italian_default_and_english_session_render_localized_pages(): void
    {
        $this->get('/')->assertOk()->assertHeader('Content-Language', 'it')->assertSee('Esplora gli EA');
        $this->post('/language', ['locale' => 'en'])->assertRedirect('/')->assertSessionHas('locale', 'en');
        $this->get('/')->assertOk()->assertSee('Explore EAs')->assertSee('Your strategy.')->assertSee('lang="en"', false);
        $this->get('/login')->assertOk()->assertSee('Welcome back.')->assertSee('Forgot your password?');
        $this->post('/language', ['locale' => 'it'])->assertSessionHas('locale', 'it');
        $this->get('/login')->assertOk()->assertSee('Bentornato.');
    }

    public function test_authenticated_locale_is_persisted_without_external_redirect(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('https://untrusted.example/account')->post('/language', ['locale' => 'en'])->assertRedirect('/account');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'locale' => 'en']);
        $this->flushSession();
        $this->get('/account')->assertOk()->assertHeader('Content-Language', 'en')->assertSee('Hello, '.$user->name.'.');
        $this->get('/brokers')->assertOk()->assertSee('Choose your broker.');
    }

    public function test_unsupported_locale_is_rejected(): void
    {
        $this->post('/language', ['locale' => '../../config'])->assertSessionHasErrors('locale')->assertSessionMissing('locale');
    }

    public function test_validation_and_flash_errors_follow_locale(): void
    {
        $this->withSession(['locale' => 'it'])->post('/login', [])->assertSessionHasErrors(['email' => 'Il campo email è obbligatorio.']);
        $this->withSession(['locale' => 'en'])->post('/login', ['email' => 'unknown@example.com', 'password' => 'incorrect'])->assertSessionHasErrors(['email' => 'Invalid credentials.']);
    }

    public function test_registration_preserves_locale_for_notifications(): void
    {
        Notification::fake();

        $this->withSession(['locale' => 'en'])->post('/register', ['name' => 'Alice', 'email' => 'alice@example.com', 'password' => 'a-secure-passphrase', 'password_confirmation' => 'a-secure-passphrase'])->assertRedirect('/account');

        $user = User::where('email', 'alice@example.com')->firstOrFail();
        $this->assertSame('en', $user->preferredLocale());
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_translations_have_matching_keys_and_english_pages_render(): void
    {
        $italian = json_decode(file_get_contents(lang_path('it.json')), true, flags: JSON_THROW_ON_ERROR);
        $english = json_decode(file_get_contents(lang_path('en.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(array_keys($italian), array_keys($english));
        $user = User::factory()->create();
        $user->role = 'admin';
        $user->save();

        foreach (['/', '/register', '/forgot-password', '/email/verify', '/developer', '/admin', '/admin/brokers', '/admin/brokers/create'] as $path) {
            if ($path === '/email/verify') {
                $this->actingAs($user);
            }
            $this->withSession(['locale' => 'en'])->get($path)->assertOk();
        }
    }
}
