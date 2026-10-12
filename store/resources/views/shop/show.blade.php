@extends('layouts.app')

@section('title', $product->name.' | ShopSense')

@section('content')
    <div class="grid gap-8 md:grid-cols-2">
        <img src="{{ $product->image }}" alt="{{ $product->name }}" class="w-full rounded-lg object-cover">
        <div>
            <div class="text-sm text-stone-500">{{ $product->category->name }}</div>
            <h1 class="mb-2 text-2xl font-bold">{{ $product->name }}</h1>
            <div class="mb-4 text-xl">₹{{ number_format($product->price, 2) }}</div>
            <p class="mb-6 text-stone-700">{{ $product->description }}</p>
            @if($product->stock > 0)
                <form method="POST" action="{{ route('cart.add', $product) }}">@csrf
                    <button class="rounded bg-stone-900 px-5 py-2 text-white">Add to cart</button>
                    <span class="ml-3 text-sm text-stone-500">{{ $product->stock }} in stock</span>
                </form>
            @else
                <div class="text-red-700">Out of stock</div>
            @endif
        </div>
    </div>

    <div class="mt-10">
        @include('partials.product-row', ['title' => 'Customers also looked at', 'result' => $similar])
    </div>
@endsection
