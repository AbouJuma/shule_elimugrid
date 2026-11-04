<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'ElimuGrid')</title>
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>
    @php $schoolForLayout = auth()->user()->school ?? null; @endphp
    <div class="d-flex">
        <aside class="portal-sidebar p-3 d-none d-md-flex flex-column">
            <div class="brand fs-4 px-2 py-3 d-flex align-items-center gap-2">
                @if ($schoolForLayout?->logo_path)
                    <img src="{{ route('school.logo', ['v' => $schoolForLayout->updated_at?->timestamp]) }}" alt="" style="height: 34px; width: 34px; object-fit: contain; background: #fff; border-radius: 6px; padding: 2px;">
                @endif
                <span class="text-truncate" title="{{ $schoolForLayout?->name }}">{{ $schoolForLayout?->name ?? 'ElimuGrid' }}</span>
            </div>
            <nav class="nav flex-column gap-1">
                @yield('sidebar')
            </nav>
            <div class="mt-auto px-2 pb-2 pt-3">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm w-100">
                        <i class="bi bi-box-arrow-right me-1"></i> {{ __('Sign out') }}
                    </button>
                </form>
            </div>
        </aside>

        <main class="flex-grow-1 portal-main">
            <header class="bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center gap-3">
                <h1 class="h5 mb-0">@yield('page-title', __('Dashboard'))</h1>
                <div class="d-flex align-items-center gap-3">
                    <div class="btn-group btn-group-sm" role="group" aria-label="Language">
                        @foreach (config('app.supported_locales') as $code => $label)
                            <form method="POST" action="{{ route('language.update', $code) }}" class="d-inline">
                                @csrf
                                <button type="submit" title="{{ $label }}"
                                        class="btn {{ app()->getLocale() === $code ? 'btn-gold' : 'btn-outline-secondary' }} {{ $loop->first ? 'rounded-end-0' : 'rounded-start-0' }}">
                                    {{ strtoupper($code) }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                    <a href="{{ route('school-admin.account.password.edit') }}" class="text-end small text-decoration-none text-reset">
                        <div class="fw-semibold">{{ auth()->user()->name }}</div>
                        <div class="text-secondary">
                            {{ auth()->user()->roles->pluck('name')->join(', ') ?: __('No role assigned') }}
                            · <span class="text-primary">{{ __('Change password') }}</span>
                        </div>
                    </a>
                </div>
            </header>

            <div class="p-4">
                @if (session('onboarded'))
                    @php $onboarded = session('onboarded'); @endphp
                    <div class="alert alert-success">
                        <strong>{{ $onboarded['school'] }}</strong> {{ __('created. Administrator') }} <strong>{{ $onboarded['admin_email'] }}</strong> {{ __('— temporary password:') }} <code>{{ $onboarded['temporary_password'] }}</code>
                        <div class="small mt-1">{{ __('Store this now — it will not be shown again. Automatic delivery via SMS/email lands in a later phase.') }}</div>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>
    @stack('scripts')
</body>
</html>
