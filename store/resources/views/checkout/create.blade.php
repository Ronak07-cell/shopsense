@extends('layouts.app')

@section('content')
    <h1 class="mb-4 text-2xl font-bold">Checkout</h1>
    <ul class="mb-4 rounded-lg border bg-white p-4 text-sm">
        @foreach($lines as $line)
            <li class="flex justify-between py-1"><span>{{ $line['product']->name }} × {{ $line['quantity'] }}</span><span>₹{{ number_format($line['subtotal'], 2) }}</span></li>
        @endforeach
        <li class="mt-2 flex justify-between border-t pt-2 font-semibold"><span>Total</span><span>₹{{ number_format($total, 2) }}</span></li>
    </ul>
    <form method="POST" action="{{ route('checkout.store') }}" class="max-w-lg">@csrf
        <label class="mb-1 block text-sm">Shipping address</label>
        <textarea name="shipping_address" rows="3" class="mb-3 w-full rounded border p-2" required>{{ old('shipping_address') }}</textarea>
        <p class="mb-3 text-xs text-stone-500">Payment is simulated in this demo. No card details are collected.</p>
        <button class="rounded bg-stone-900 px-5 py-2 text-white">Place order</button>
    </form>
@endsection
