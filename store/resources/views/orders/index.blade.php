@extends('layouts.app')

@section('content')
    <h1 class="mb-4 text-2xl font-bold">Your orders</h1>
    @forelse($orders as $order)
        <div class="mb-4 rounded-lg border bg-white p-4">
            <div class="mb-2 flex justify-between text-sm">
                <span class="font-medium">Order #{{ $order->id }} · {{ $order->created_at->format('d M Y') }}</span>
                <span>₹{{ number_format($order->total, 2) }} · {{ ucfirst($order->status) }}</span>
            </div>
            <ul class="text-sm text-stone-700">
                @foreach($order->items as $item)
                    <li>{{ $item->product->name }} × {{ $item->quantity }}</li>
                @endforeach
            </ul>
        </div>
    @empty
        <p class="text-stone-500">No orders yet.</p>
    @endforelse
@endsection
