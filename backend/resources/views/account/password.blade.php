@extends('layouts.portal')

@section('title', 'Change Password — ElimuGrid')
@section('page-title', __('Change Password'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => null])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ __($e) }}</li>@endforeach</ul></div>
    @endif

    <div class="card" style="max-width: 460px;">
        <div class="card-body">
            <p class="text-secondary small">
                {{ __('If this is your first time signing in, your password was your phone number — change it to something only you know.') }}
            </p>
            <form method="POST" action="{{ route('school-admin.account.password.update') }}">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label">{{ __('Current password') }}</label>
                    <input type="password" name="current_password" class="form-control" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('New password') }}</label>
                    <input type="password" name="password" class="form-control" required minlength="8">
                    <div class="form-text">{{ __('At least 8 characters.') }}</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('Confirm new password') }}</label>
                    <input type="password" name="password_confirmation" class="form-control" required minlength="8">
                </div>
                <button type="submit" class="btn btn-gold">{{ __('Change Password') }}</button>
            </form>
        </div>
    </div>
@endsection
