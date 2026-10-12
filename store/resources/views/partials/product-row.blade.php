{{-- $title: heading, $result: array from RecommenderClient --}}
@if($result['products']->isNotEmpty())
    <section class="mb-8">
        <h2 class="mb-1 text-lg font-semibold">{{ $title }}</h2>
        <p class="mb-3 text-xs text-stone-500">
            {{ $result['source'] === 'recommender' ? 'Picked for you by our recommendation engine' : 'Best sellers right now' }}
        </p>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach($result['products'] as $product)
                @include('partials.product-card', ['product' => $product])
            @endforeach
        </div>
    </section>
@endif
