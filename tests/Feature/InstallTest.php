<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'a-very-long-install-token-1234567890';

    public function test_installer_is_off_without_a_token(): void
    {
        config(['speedtech.install_token' => null]);
        $this->get('/install/anything-at-all-here-1234')->assertNotFound();
    }

    public function test_installer_rejects_wrong_or_short_token(): void
    {
        config(['speedtech.install_token' => $this->token]);
        $this->get('/install/wrong-token-wrong-token-123')->assertNotFound();

        config(['speedtech.install_token' => 'short']);
        $this->get('/install/short')->assertNotFound();
    }

    public function test_installer_creates_admin_and_catalogue_and_shows_password_once(): void
    {
        config(['speedtech.install_token' => $this->token, 'speedtech.admin_password' => null]);

        $response = $this->get('/install/'.$this->token)->assertOk()->assertSee('Password:');
        $this->assertMatchesRegularExpression('/Password: \S{16}/', $response->getContent());
        $this->assertTrue(User::where('is_admin', true)->exists());
        $this->assertGreaterThan(0, Brand::count());

        // Running it again keeps the existing admin and does not reveal a password.
        $again = $this->get('/install/'.$this->token)->assertOk();
        $this->assertDoesNotMatchRegularExpression('/Password: \S{16}/', $again->getContent());
        $this->assertSame(1, User::where('is_admin', true)->count());
    }
}
