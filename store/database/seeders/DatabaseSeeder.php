<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Interaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo data with real structure: every shopper prefers one or two categories,
 * so the recommender has something to learn.
 *
 * Logins: admin@shopsense.test / password   and   demo@shopsense.test / password
 */
class DatabaseSeeder extends Seeder
{
    private const CATALOGUE = [
        'Electronics' => ['headphones', 'charger', 'speaker', 'keyboard', 'mouse', 'webcam', 'powerbank', 'monitor'],
        'Books' => ['novel', 'biography', 'cookbook', 'textbook', 'comic', 'poetry', 'guide', 'journal'],
        'Fashion' => ['shirt', 'jeans', 'jacket', 'sneakers', 'scarf', 'cap', 'kurta', 'watch'],
        'Home' => ['lamp', 'cushion', 'bottle', 'bedsheet', 'clock', 'mug', 'organizer', 'vase'],
        'Sports' => ['yoga mat', 'dumbbell', 'cricket bat', 'football', 'skipping rope', 'shaker', 'racket', 'gloves'],
        'Beauty' => ['face wash', 'moisturizer', 'perfume', 'lip balm', 'shampoo', 'sunscreen', 'hair oil', 'serum'],
    ];

    public function run(): void
    {
        mt_srand(42); // same demo data every time

        User::create(['name' => 'Admin', 'email' => 'admin@shopsense.test', 'password' => 'password'])
            ->forceFill(['is_admin' => true])->save();
        User::create(['name' => 'Demo Shopper', 'email' => 'demo@shopsense.test', 'password' => 'password']);

        $byCategory = [];
        $n = 0;
        foreach (self::CATALOGUE as $name => $nouns) {
            $category = Category::create(['name' => $name, 'slug' => Str::slug($name)]);
            foreach ($nouns as $noun) {
                $n++;
                $title = ucwords($noun).($n % 2 ? ' Pro' : ' Classic');
                $product = Product::create([
                    'category_id' => $category->id,
                    'name' => $title,
                    'slug' => Str::slug($title).'-'.$n,
                    'description' => "A reliable {$noun} for everyday ".strtolower($name).' needs.',
                    'price' => 199 + ($n * 37) % 1800,
                    'stock' => 500,
                ]);
                $byCategory[$name][] = $product->id;
            }
        }

        $allIds = array_merge(...array_values($byCategory));
        $hot = array_rand(array_flip($allIds), 6); // a few best sellers
        $categories = array_keys($byCategory);
        $now = now();

        for ($u = 1; $u <= 200; $u++) {
            $user = User::create([
                'name' => "Shopper {$u}",
                'email' => "shopper{$u}@example.test",
                'password' => 'password',
            ]);

            $favourites = (array) array_rand(array_flip($categories), mt_rand(1, 2));
            $events = [];
            $bought = [];

            for ($i = 0, $count = mt_rand(8, 21); $i < $count; $i++) {
                if (mt_rand(1, 100) <= 80) {
                    $cat = $favourites[array_rand($favourites)];
                    $pid = $byCategory[$cat][array_rand($byCategory[$cat])];
                } else {
                    $pid = $allIds[array_rand($allIds)];
                }
                if (! in_array($pid, $hot, true) && mt_rand(1, 100) <= 15) {
                    $pid = $hot[array_rand($hot)];
                }

                $roll = mt_rand(1, 100);
                $type = $roll <= 70 ? Interaction::VIEW : ($roll <= 88 ? Interaction::CART : Interaction::PURCHASE);
                if ($type === Interaction::PURCHASE) {
                    $bought[$pid] = ($bought[$pid] ?? 0) + 1;
                }

                $events[] = [
                    'user_id' => $user->id,
                    'session_id' => null,
                    'product_id' => $pid,
                    'type' => $type,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            Interaction::insert($events);

            if ($bought) {
                $order = Order::create([
                    'user_id' => $user->id,
                    'total' => 0,
                    'status' => 'paid',
                    'shipping_address' => 'Demo address, New Delhi',
                ]);
                $total = 0;
                foreach ($bought as $pid => $qty) {
                    $price = Product::find($pid)->price;
                    $order->items()->create(['product_id' => $pid, 'quantity' => $qty, 'unit_price' => $price]);
                    $total += $price * $qty;
                }
                $order->update(['total' => $total]);
            }
        }
    }
}
