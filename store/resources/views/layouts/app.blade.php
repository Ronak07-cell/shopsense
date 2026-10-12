<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ShopSense')</title>
    {{-- Tailwind via CDN keeps this starter free of a Node build step. --}}
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-stone-50 text-stone-900">
<header class="border-b bg-white">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-4 px-4 py-3">
        <a href="{{ route('shop.index') }}" class="text-xl font-bold">ShopSense</a>
        <form action="{{ route('shop.index') }}" method="GET" class="flex-1">
            <input name="q" value="{{ request('q') }}" placeholder="Search products"
                   class="w-full rounded border px-3 py-2 text-sm">
        </form>
        <a href="{{ route('cart.show') }}" class="text-sm">
            Cart ({{ array_sum(session('cart', [])) }})
        </a>
        @auth
            <a href="{{ route('orders.index') }}" class="text-sm">Orders</a>
            @if(auth()->user()->is_admin)
                <a href="{{ route('admin.products.index') }}" class="text-sm">Admin</a>
            @endif
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="text-sm text-stone-600">Log out</button>
            </form>
        @else
            <a href="{{ route('login') }}" class="text-sm">Log in</a>
            <a href="{{ route('register') }}" class="text-sm">Register</a>
        @endauth
    </div>
</header>

<main class="mx-auto max-w-6xl px-4 py-6">
    @if(session('status'))
        <div class="mb-4 rounded bg-green-100 px-4 py-2 text-sm text-green-800">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded bg-red-100 px-4 py-2 text-sm text-red-800">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif
    @yield('content')
</main>
</body>
</html>
