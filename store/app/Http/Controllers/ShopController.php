<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Interaction;
use App\Models\Product;
use App\Services\RecommenderClient;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request, RecommenderClient $recommender)
    {
        $products = Product::active()
            ->with('category')
            ->search($request->query('q'))
            ->when($request->query('category'), fn ($q, $slug) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $picks = $recommender->forShopper($request->user(), $request->session()->get('recent_views', []));

        return view('shop.index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(),
            'picks' => $picks,
        ]);
    }

    public function show(Request $request, Product $product, RecommenderClient $recommender)
    {
        abort_unless($product->is_active, 404);

        Interaction::record($product->id, Interaction::VIEW);

        $recent = collect($request->session()->get('recent_views', []))
            ->prepend($product->id)->unique()->take(10)->values()->all();
        $request->session()->put('recent_views', $recent);

        return view('shop.show', [
            'product' => $product->load('category'),
            'similar' => $recommender->similarTo($product),
        ]);
    }
}
