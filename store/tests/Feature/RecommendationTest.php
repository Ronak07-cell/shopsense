<?php

namespace Tests\Feature;

use App\Models\Interaction;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_shows_picks_from_the_recommender(): void
    {
        $products = Product::factory()->count(3)->create();
        $user = User::factory()->create();

        Http::fake(['*/recommend/user/*' => Http::response([
            'strategy' => 'hybrid',
            'items' => [
                ['product_id' => $products[2]->id, 'score' => 0.9],
                ['product_id' => $products[0]->id, 'score' => 0.5],
            ],
        ])]);

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('Recommended for you')
            ->assertSee('recommendation engine')
            ->assertSeeInOrder([$products[2]->name, $products[0]->name]);
    }

    public function test_store_falls_back_to_best_sellers_when_the_service_is_down(): void
    {
        Product::factory()->count(3)->create();
        Http::fake(['*' => Http::response('boom', 500)]);

        $this->actingAs(User::factory()->create())->get('/')
            ->assertOk()
            ->assertSee('Best sellers right now');
    }

    public function test_guest_without_history_gets_best_sellers_without_calling_the_service(): void
    {
        Product::factory()->count(2)->create();
        Http::fake();

        $this->get('/')->assertOk()->assertSee('Best sellers right now');

        Http::assertNothingSent();
    }

    public function test_product_page_records_a_view_and_shows_similar_items(): void
    {
        [$a, $b] = Product::factory()->count(2)->create();
        Http::fake(['*/similar/*' => Http::response(['items' => [['product_id' => $b->id, 'score' => 0.8]]])]);

        $this->get("/products/{$a->slug}")->assertOk()->assertSee($b->name);

        $this->assertDatabaseHas('interactions', ['product_id' => $a->id, 'type' => Interaction::VIEW]);
    }
}
