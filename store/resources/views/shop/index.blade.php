@extends('layouts.app')

@section('content')
    @include('partials.product-row', ['title' => auth()->check() ? 'Recommended for you' : 'You might like', 'result' => $picks])

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('shop.index') }}" class="rounded-full border px-3 py-1 {{ request('category') ? '' : 'bg-stone-900 text-white' }}">All</a>
        @foreach($categories as $category)
            <a href="{{ route('shop.index', ['category' => $category->slug]) }}"
               class="rounded-full border px-3 py-1 {{ request('category') === $category->slug ? 'bg-stone-900 text-white' : '' }}">{{ $category->name }}</a>
        @endforeach
    </div>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        @forelse($products as $product)
            @include('partials.product-card', ['product' => $product])
        @empty
            <p class="col-span-full text-stone-500">No products found.</p>
        @endforelse
    </div>

    <div class="mt-6">{{ $products->links() }}</div>
@endsection
