<?php

namespace Tests\Feature;

use App\Models\Interaction;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CartCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(); // the recommender is not under test here
    }

    public function test_adding_to_cart_records_an_interaction(): void
    {
        $product = Product::factory()->create();

        $this->post("/cart/{$product->id}")->assertRedirect('/cart');

        $this->assertSame([$product->id => 1], session('cart'));
        $this->assertDatabaseHas('interactions', ['product_id' => $product->id, 'type' => Interaction::CART]);
    }

    public function test_checkout_creates_an_order_reduces_stock_and_records_a_purchase(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5, 'price' => 100]);

        $this->actingAs($user)->withSession(['cart' => [$product->id => 2]])
            ->post('/checkout', ['shipping_address' => '221B Baker Street, Delhi'])
            ->assertRedirect('/orders');

        $this->assertDatabaseHas('orders', ['user_id' => $user->id, 'total' => 200]);
        $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'quantity' => 2]);
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertDatabaseHas('interactions', ['user_id' => $user->id, 'product_id' => $product->id, 'type' => Interaction::PURCHASE]);
    }

    public function test_checkout_refuses_more_than_the_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 1]);

        $this->actingAs($user)->withSession(['cart' => [$product->id => 3]])
            ->post('/checkout', ['shipping_address' => '221B Baker Street, Delhi'])
            ->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_checkout_requires_login(): void
    {
        $this->get('/checkout')->assertRedirect('/login');
    }

    public function test_admin_area_is_closed_to_normal_users(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/products')->assertForbidden();
    }
}
