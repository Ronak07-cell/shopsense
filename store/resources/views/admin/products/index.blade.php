@extends('layouts.app')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-2xl font-bold">Products</h1>
        <a href="{{ route('admin.products.create') }}" class="rounded bg-stone-900 px-4 py-2 text-sm text-white">New product</a>
    </div>
    <table class="w-full rounded-lg border bg-white text-sm">
        <thead class="bg-stone-100 text-left"><tr><th class="p-2">Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach($products as $product)
            <tr class="border-t">
                <td class="p-2">{{ $product->name }}</td>
                <td>{{ $product->category->name }}</td>
                <td>₹{{ number_format($product->price, 2) }}</td>
                <td>{{ $product->stock }}</td>
                <td>{{ $product->is_active ? 'Live' : 'Hidden' }}</td>
                <td class="space-x-2 text-right">
                    <a class="underline" href="{{ route('admin.products.edit', $product) }}">Edit</a>
                    <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="inline">@csrf @method('DELETE')
                        <button class="text-red-700">Hide</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="mt-4">{{ $products->links() }}</div>
@endsection
