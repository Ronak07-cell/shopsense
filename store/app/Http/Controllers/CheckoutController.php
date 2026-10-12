<?php

namespace App\Http\Controllers;

use App\Models\Interaction;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function create(Request $request)
    {
        [$lines, $total] = CartController::lines($request);

        if ($lines->isEmpty()) {
            return redirect()->route('shop.index')->with('status', 'Your cart is empty.');
        }

        return view('checkout.create', compact('lines', 'total'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['shipping_address' => 'required|string|min:10|max:500']);
        $cart = $request->session()->get('cart', []);

        if (! $cart) {
            return redirect()->route('shop.index');
        }

        $order = DB::transaction(function () use ($request, $cart, $data) {
            $products = Product::active()->whereIn('id', array_keys($cart))->lockForUpdate()->get();
            $total = 0;

            foreach ($products as $product) {
                if ($product->stock < $cart[$product->id]) {
                    throw ValidationException::withMessages([
                        'cart' => "Only {$product->stock} left of {$product->name}.",
                    ]);
                }
                $total += $product->price * $cart[$product->id];
            }

            $order = Order::create([
                'user_id' => $request->user()->id,
                'total' => $total,
                'status' => 'paid', // payment is simulated in this starter
                'shipping_address' => $data['shipping_address'],
            ]);

            foreach ($products as $product) {
                $qty = $cart[$product->id];
                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $product->price,
                ]);
                $product->decrement('stock', $qty);
                Interaction::record($product->id, Interaction::PURCHASE);
            }

            return $order;
        });

        $request->session()->forget('cart');

        return redirect()->route('orders.index')->with('status', "Order #{$order->id} placed.");
    }
}
