<?php

namespace Tests\Feature;

use App\Models\ShopProduct;
use App\Models\User;
use App\Settings\UserSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TestBillingDetailsCheckout extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'User'], ['color' => '#0052a3', 'power' => 10]);
        $role->givePermissionTo(Permission::firstOrCreate(
            ['name' => 'user.shop.buy'],
            ['readable_name' => 'Shop Buy']
        ));
    }

    private function makeUser(): User
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'credits' => 250000,
            'server_limit' => 1,
            'pterodactyl_id' => null,
        ]);

        $user->assignRole('User');

        return $user;
    }

    private function makeProduct(): ShopProduct
    {
        return ShopProduct::create([
            'type' => 'Credits',
            'price' => 1000,
            'quantity' => 1000,
            'description' => 'Test credits',
            'display' => 'Test Credits',
            'currency_code' => 'EUR',
            'disabled' => false,
        ]);
    }

    public function test_checkout_passes_billing_required_when_enabled()
    {
        $settings = new UserSettings();
        $settings->require_billing_details_on_purchase = true;
        $settings->save();

        $user = $this->makeUser();

        $response = $this->actingAs($user)->get(route('checkout', $this->makeProduct()->id));

        $response->assertStatus(200);
        $response->assertSee('Billing Details');
    }

    public function test_checkout_does_not_show_billing_when_disabled()
    {
        $settings = new UserSettings();
        $settings->require_billing_details_on_purchase = false;
        $settings->save();

        $user = $this->makeUser();

        $response = $this->actingAs($user)->get(route('checkout', $this->makeProduct()->id));

        $response->assertStatus(200);
        $response->assertDontSee('Billing Details');
    }

    public function test_pay_requires_billing_details_when_enabled()
    {
        $settings = new UserSettings();
        $settings->require_billing_details_on_purchase = true;
        $settings->save();

        $user = $this->makeUser();
        $product = $this->makeProduct();

        $response = $this->actingAs($user)->post(route('payment.pay'), [
            'product_id' => $product->id,
            'payment_method' => 'stripe',
        ]);

        $response->assertSessionHasErrors('billing_first_name');
        $response->assertSessionHasErrors('billing_address');

        $user->refresh();
        $this->assertFalse($user->hasBillingDetails());
    }

    public function test_pay_persists_billing_details()
    {
        $settings = new UserSettings();
        $settings->require_billing_details_on_purchase = true;
        $settings->save();

        $user = $this->makeUser();
        $product = $this->makeProduct();

        $this->actingAs($user)->post(route('payment.pay'), [
            'product_id' => $product->id,
            'payment_method' => 'stripe',
            'billing_first_name' => 'John',
            'billing_last_name' => 'Doe',
            'billing_phone' => '+49 30 123456',
            'billing_address' => 'Main Street 1',
            'billing_city' => 'Berlin',
            'billing_state' => 'Berlin',
            'billing_postal_code' => '10115',
            'billing_country' => 'DE',
        ]);

        $user->refresh();
        $this->assertTrue($user->hasBillingDetails());
        $this->assertSame('John Doe', $user->getBillingName());
        $this->assertSame('DE', $user->country);
    }

    public function test_profile_billing_update_persists_details()
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->post(route('profile.billing.update'), [
            'billing_is_company' => 0,
            'billing_first_name' => 'Jane',
            'billing_last_name' => 'Smith',
            'billing_phone' => '+1 555 000 1111',
            'billing_address' => '42 Wallaby Way',
            'billing_city' => 'Sydney',
            'billing_state' => 'NSW',
            'billing_postal_code' => '2000',
            'billing_country' => 'AU',
        ]);

        $response->assertRedirect(route('profile.index'));

        $user->refresh();
        $this->assertTrue($user->hasBillingDetails());
        $this->assertSame('Jane Smith', $user->getBillingName());
        $this->assertSame('AU', $user->country);
    }

    public function test_profile_billing_update_validates_company_requirements()
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->post(route('profile.billing.update'), [
            'billing_is_company' => 1,
            'billing_company_name' => '',
            'billing_address' => '42 Wallaby Way',
            'billing_city' => 'Sydney',
            'billing_postal_code' => '2000',
            'billing_country' => 'AU',
        ]);

        $response->assertSessionHasErrors('billing_company_name');
    }
}