@extends('layouts.app')

@section('content')
    <h1 class="mb-4 text-2xl font-bold">{{ $product->exists ? 'Edit product' : 'New product' }}</h1>
    <form method="POST" class="max-w-lg space-y-3"
          action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}">
        @csrf
        @if($product->exists) @method('PUT') @endif
        <select name="category_id" class="w-full rounded border p-2" required>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <input name="name" value="{{ old('name', $product->name) }}" placeholder="Name" class="w-full rounded border p-2" required>
        <textarea name="description" rows="4" placeholder="Description" class="w-full rounded border p-2">{{ old('description', $product->description) }}</textarea>
        <input name="price" type="number" step="0.01" value="{{ old('price', $product->price) }}" placeholder="Price" class="w-full rounded border p-2" required>
        <input name="stock" type="number" value="{{ old('stock', $product->stock ?? 0) }}" placeholder="Stock" class="w-full rounded border p-2" required>
        <input name="image_url" value="{{ old('image_url', $product->image_url) }}" placeholder="Image URL (optional)" class="w-full rounded border p-2">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active))> Visible in shop
        </label>
        <button class="rounded bg-stone-900 px-5 py-2 text-white">Save</button>
    </form>
@endsection
