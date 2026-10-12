<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to the Python recommender. If the service is slow or down, the shop
 * keeps working and shows best sellers instead.
 */
class RecommenderClient
{
    /**
     * Picks for a shopper. Logged-in users get personal picks; guests get picks
     * based on the products they viewed in this session.
     *
     * @param  array<int>  $recentProductIds
     * @return array{products: Collection<int, Product>, source: string}
     */
    public function forShopper(?User $user, array $recentProductIds, int $k = 8): array
    {
        if ($user) {
            $path = "/recommend/user/{$user->id}";
            $query = ['k' => $k, 'exclude' => implode(',', $recentProductIds)];
            $key = "rec:user:{$user->id}:{$k}:".md5($query['exclude']);
        } elseif ($recentProductIds) {
            $path = '/recommend/seed';
            $query = ['k' => $k, 'product_ids' => implode(',', $recentProductIds)];
            $key = "rec:seed:{$k}:".md5($query['product_ids']);
        } else {
            return $this->fallback($k);
        }

        return $this->fetch($key, $path, $query, $k);
    }

    /** Products similar to the one being viewed. */
    public function similarTo(Product $product, int $k = 6): array
    {
        return $this->fetch("rec:similar:{$product->id}:{$k}", "/similar/{$product->id}", ['k' => $k], $k, [$product->id]);
    }

    /**
     * Send the catalogue and every recorded interaction to the service to retrain.
     *
     * @param  array{products: array, interactions: array}  $payload
     */
    public function train(array $payload): array
    {
        $response = Http::timeout(120)->acceptJson()->post($this->url('/train'), $payload);
        $response->throw();

        return $response->json();
    }

    /** @return array{products: Collection<int, Product>, source: string} */
    protected function fetch(string $key, string $path, array $query, int $k, array $exclude = []): array
    {
        $ids = Cache::get($key);

        if ($ids === null) {
            $ids = $this->request($path, $query);
            if ($ids !== null) {
                Cache::put($key, $ids, config('recommender.cache_ttl'));
            }
        }

        if (! $ids) {
            return $this->fallback($k, $exclude);
        }

        return ['products' => $this->hydrate($ids), 'source' => 'recommender'];
    }

    /** @return array<int>|null product ids in ranked order, or null when the service failed */
    protected function request(string $path, array $query): ?array
    {
        try {
            $response = Http::timeout(config('recommender.timeout'))
                ->acceptJson()
                ->get($this->url($path), $query);
        } catch (ConnectionException $e) {
            Log::warning('Recommender unreachable: '.$e->getMessage());

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Recommender returned '.$response->status());

            return null;
        }

        return collect($response->json('items', []))->pluck('product_id')->map(fn ($id) => (int) $id)->all();
    }

    /** @param array<int> $ids */
    protected function hydrate(array $ids): Collection
    {
        $products = Product::active()->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $products->get($id))->filter()->values();
    }

    /** @return array{products: Collection<int, Product>, source: string} */
    protected function fallback(int $k, array $exclude = []): array
    {
        $products = Product::active()
            ->whereNotIn('id', $exclude)
            ->withSum('orderItems as units_sold', 'quantity')
            ->orderByDesc('units_sold')
            ->latest()
            ->limit($k)
            ->get();

        return ['products' => $products, 'source' => 'best_sellers'];
    }

    protected function url(string $path): string
    {
        return rtrim(config('recommender.url'), '/').$path;
    }
}
