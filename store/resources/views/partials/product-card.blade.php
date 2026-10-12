<a href="{{ route('shop.show', $product) }}" class="block overflow-hidden rounded-lg border bg-white hover:shadow">
    <img src="{{ $product->image }}" alt="{{ $product->name }}" class="h-40 w-full object-cover" loading="lazy">
    <div class="p-3">
        <div class="truncate text-sm font-medium">{{ $product->name }}</div>
        <div class="text-sm text-stone-600">₹{{ number_format($product->price, 2) }}</div>
    </div>
</a>
