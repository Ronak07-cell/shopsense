<?php

namespace App\Http\Controllers;

use App\Models\Interaction;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function show(Request $request)
    {
        [$lines, $total] = self::lines($request);

        return view('cart.show', compact('lines', 'total'));
    }

    public function add(Request $request, Product $product)
    {
        abort_unless($product->is_active, 404);

        $cart = $request->session()->get('cart', []);
        $qty = ($cart[$product->id] ?? 0) + 1;
        $cart[$product->id] = min($qty, max($product->stock, 1));
        $request->session()->put('cart', $cart);

        Interaction::record($product->id, Interaction::CART);

        return redirect()->route('cart.show')->with('status', "{$product->name} added to your cart.");
    }

    public function update(Request $request, Product $product)
    {
        $qty = (int) $request->validate(['quantity' => 'required|integer|min:0|max:50'])['quantity'];
        $cart = $request->session()->get('cart', []);

        if ($qty === 0) {
            unset($cart[$product->id]);
        } else {
            $cart[$product->id] = min($qty, max($product->stock, 1));
        }
        $request->session()->put('cart', $cart);

        return redirect()->route('cart.show');
    }

    public function remove(Request $request, Product $product)
    {
        $cart = $request->session()->get('cart', []);
        unset($cart[$product->id]);
        $request->session()->put('cart', $cart);

        return redirect()->route('cart.show');
    }

    /** @return array{0: \Illuminate\Support\Collection, 1: float} cart lines and total */
    public static function lines(Request $request): array
    {
        $cart = $request->session()->get('cart', []);
        $products = Product::active()->whereIn('id', array_keys($cart))->get();

        $lines = $products->map(fn (Product $p) => [
            'product' => $p,
            'quantity' => $cart[$p->id],
            'subtotal' => $p->price * $cart[$p->id],
        ]);

        return [$lines, (float) $lines->sum('subtotal')];
    }
}
