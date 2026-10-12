<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index()
    {
        return view('admin.products.index', ['products' => Product::with('category')->latest()->paginate(20)]);
    }

    public function create()
    {
        return view('admin.products.form', ['product' => new Product(['is_active' => true]), 'categories' => Category::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        Product::create($data);

        return redirect()->route('admin.products.index')->with('status', 'Product created.');
    }

    public function edit(Product $product)
    {
        return view('admin.products.form', ['product' => $product, 'categories' => Category::orderBy('name')->get()]);
    }

    public function update(Request $request, Product $product)
    {
        $product->update($this->validated($request));

        return redirect()->route('admin.products.index')->with('status', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        // Soft-retire instead of deleting so past orders keep their product.
        $product->update(['is_active' => false]);

        return redirect()->route('admin.products.index')->with('status', 'Product hidden from the shop.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'price' => 'required|numeric|min:0|max:9999999',
            'stock' => 'required|integer|min:0|max:100000',
            'image_url' => 'nullable|url|max:500',
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    protected function uniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $candidate = $slug;
        $i = 2;
        while (Product::where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$i++;
        }

        return $candidate;
    }
}
