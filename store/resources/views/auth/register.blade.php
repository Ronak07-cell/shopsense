@extends('layouts.app')

@section('content')
    <h1 class="mb-4 text-2xl font-bold">Create an account</h1>
    <form method="POST" action="{{ route('register') }}" class="max-w-sm space-y-3">@csrf
        <input name="name" value="{{ old('name') }}" placeholder="Name" class="w-full rounded border p-2" required>
        <input type="email" name="email" value="{{ old('email') }}" placeholder="Email" class="w-full rounded border p-2" required>
        <input type="password" name="password" placeholder="Password (8+ characters)" class="w-full rounded border p-2" required>
        <input type="password" name="password_confirmation" placeholder="Confirm password" class="w-full rounded border p-2" required>
        <button class="rounded bg-stone-900 px-5 py-2 text-white">Register</button>
    </form>
@endsection
