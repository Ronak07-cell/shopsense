@extends('layouts.app')

@section('content')
    <h1 class="mb-4 text-2xl font-bold">Your cart</h1>
    @if($lines->isEmpty())
        <p class="text-stone-500">Your cart is empty. <a class="underline" href="{{ route('shop.index') }}">Keep shopping</a></p>
    @else
        <div class="divide-y rounded-lg border bg-white">
            @foreach($lines as $line)
                <div class="flex flex-wrap items-center gap-4 p-4">
                    <img src="{{ $line['product']->image }}" class="h-16 w-16 rounded object-cover" alt="">
                    <div class="flex-1">
                        <a href="{{ route('shop.show', $line['product']) }}" class="font-medium">{{ $line['product']->name }}</a>
                        <div class="text-sm text-stone-500">₹{{ number_format($line['product']->price, 2) }}</div>
                    </div>
                    <form method="POST" action="{{ route('cart.update', $line['product']) }}" class="flex items-center gap-2">
                        @csrf @method('PATCH')
                        <input type="number" name="quantity" min="0" max="50" value="{{ $line['quantity'] }}" class="w-16 rounded border px-2 py-1">
                        <button class="text-sm underline">Update</button>
                    </form>
                    <div class="w-24 text-right">₹{{ number_format($line['subtotal'], 2) }}</div>
                    <form method="POST" action="{{ route('cart.remove', $line['product']) }}">@csrf @method('DELETE')
                        <button class="text-sm text-red-700">Remove</button>
                    </form>
                </div>
            @endforeach
        </div>
        <div class="mt-4 flex items-center justify-between">
            <div class="text-lg font-semibold">Total ₹{{ number_format($total, 2) }}</div>
            <a href="{{ route('checkout.create') }}" class="rounded bg-stone-900 px-5 py-2 text-white">Checkout</a>
        </div>
    @endif
@endsection
