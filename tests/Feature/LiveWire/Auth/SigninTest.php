<?php

namespace Tests\Feature\LiveWire\Auth;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use App\Http\Livewire\Auth\Signin;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;


class SigninTest extends TestCase
{
    use RefreshDatabase;



    protected function setUp(): void
    {
        parent::setUp();


        DB::table('roles')->insert([
            'id'          => 3,
            'name'        => 'Test Role',
            'guard_name' => 'web',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }


    /** @test */
    public function test_login_page_loads_properly()
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Login via OTP');
    }

    /** @test */
    public function test_contact_field_must_be_valid()
    {
        Livewire::test(Signin::class)
            ->set('contact', 'invalid')
            ->call('sendOtp')
            ->assertHasErrors(['contact']);
    }

    /** @test */
    public function test_otp_is_sent_for_valid_email()
    {


        Livewire::test(Signin::class)
            ->set('contact', 'user@example.com')
            ->call('sendOtp')
            ->assertHasNoErrors()
            ->assertSet('otpSent', true)
            ->assertSee('OTP Sent!')
            ->assertDispatched('start-otp-countdown');

        $user = User::where('contact', 'user@example.com')->first();
        $this->assertNotNull($user);
        $this->assertDatabaseHas('user_otps', ['user_id' => $user->id]);
    }

    /** @test */
    public function test_it_verifies_valid_otp_and_logs_in()
    {


        // DB::table('roles')->insert([
        //     'id'          => 3,
        //     'name'        => 'Test Role', // required
        //     'description' => 'Temporary role for OTP verification test', // if NOT NULL
        //     'created_at'  => now(),
        //     'updated_at'  => now(),
        // ]);


        Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'web']);
        $user = User::factory()->create(['contact' => 'verify@example.com']);
        $user->assignRole('Customer');
        UserOtp::create([
            'user_id' => $user->id,
            'otp' => '12345',
            'expire_at' => now()->addMinutes(5),
        ]);

        Livewire::test(Signin::class)
            ->set('contact', 'verify@example.com')
            ->set('otp', ['1', '2', '3', '4', '5'])
            ->call('verifyOtp')
            ->assertHasNoErrors()
            ->assertDispatched('redirecting');
    }

    /** @test */
    public function test_it_fails_verification_for_expired_or_wrong_otp()
    {
        //     DB::table('roles')->insert([
        //         'id' => 3,
        //         'name' => 'Test Role',
        //         'description' => 'Role for testing OTP flow',
        //     ]);

        $user = User::factory()->create(['contact' => 'expired@example.com']);
        UserOtp::create([
            'user_id' => $user->id,
            'otp' => '99999',
            'expire_at' => now()->subMinute(),
        ]);

        Livewire::test(Signin::class)
            ->set('contact', 'expired@example.com')
            ->set('otp', ['9', '9', '9', '9', '9'])
            ->call('verifyOtp')
            ->assertHasErrors(['otp']);
    }
}
