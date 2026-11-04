@extends('layouts.guest')

@section('title', 'Sign in — ElimuGrid')

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-body p-4 p-md-5">
        <h1 class="h3 fw-bold text-navy mb-1">{{ __('ElimuGrid') }}</h1>
        <p class="text-secondary mb-4">{{ __('Sign in to the platform portal') }}</p>

        @if ($errors->any())
            <div class="alert alert-danger py-2 small" role="alert">
                {{ __($errors->first()) }}
            </div>
        @endif

        @if (session('status'))
            <div class="alert alert-success py-2 small" role="alert">
                {{ __(session('status')) }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}">
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label">{{ __('Email or phone number') }}</label>
                <input type="text" class="form-control @error('email') is-invalid @enderror"
                       id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">{{ __('Password') }}</label>
                <input type="password" class="form-control @error('password') is-invalid @enderror"
                       id="password" name="password" required>
            </div>

            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" id="remember" name="remember">
                <label class="form-check-label small text-secondary" for="remember">{{ __('Remember me') }}</label>
            </div>

            <button type="submit" class="btn btn-primary w-100">{{ __('Sign in') }}</button>
        </form>
    </div>
</div>
@endsection
