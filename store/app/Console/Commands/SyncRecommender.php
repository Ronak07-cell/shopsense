<?php

namespace App\Console\Commands;

use App\Models\Interaction;
use App\Models\Product;
use App\Services\RecommenderClient;
use Illuminate\Console\Command;

class SyncRecommender extends Command
{
    protected $signature = 'recommender:sync';

    protected $description = 'Send the catalogue and shopper interactions to the recommender and retrain it';

    public function handle(RecommenderClient $client): int
    {
        $products = Product::active()->with('category')->get()->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'description' => $p->description,
            'category' => $p->category?->name,
            'price' => (float) $p->price,
        ])->all();

        // Only products that exist and are active can be recommended.
        $interactions = Interaction::query()
            ->whereIn('product_id', array_column($products, 'id'))
            ->get(['user_id', 'product_id', 'type'])
            ->map(fn ($i) => ['user_id' => $i->user_id, 'product_id' => $i->product_id, 'type' => $i->type])
            ->all();

        if (! $products) {
            $this->error('No products to train on.');

            return self::FAILURE;
        }

        try {
            $result = $client->train(['products' => $products, 'interactions' => $interactions]);
        } catch (\Throwable $e) {
            $this->error('Training failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Trained on %d products, %d shoppers, %d interactions.',
            $result['products'] ?? 0,
            $result['users'] ?? 0,
            $result['interactions'] ?? 0,
        ));

        return self::SUCCESS;
    }
}
