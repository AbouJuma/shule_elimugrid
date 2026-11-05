@extends('layouts.portal')

@section('title', 'Settings — ElimuGrid')
@section('page-title', __('Settings'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'settings'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ __($errors->first()) }}</div>
    @endif

    <h2 class="h5 mb-3">{{ __('Roles & Permissions') }}</h2>
    @include('school-admin.roles._roles_panel')

    <h2 class="h5 mb-3 mt-4">{{ __('Staff Role Assignment') }}</h2>
    @include('school-admin.settings._staff_roles_panel')
@endsection
