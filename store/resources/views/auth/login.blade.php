@extends('layouts.app')

@section('content')
    <h1 class="mb-4 text-2xl font-bold">Log in</h1>
    <form method="POST" action="{{ route('login') }}" class="max-w-sm space-y-3">@csrf
        <input type="email" name="email" value="{{ old('email') }}" placeholder="Email" class="w-full rounded border p-2" required>
        <input type="password" name="password" placeholder="Password" class="w-full rounded border p-2" required>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember"> Remember me</label>
        <button class="rounded bg-stone-900 px-5 py-2 text-white">Log in</button>
    </form>
@endsection
