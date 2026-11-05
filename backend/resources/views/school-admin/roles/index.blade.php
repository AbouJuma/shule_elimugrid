@extends('layouts.portal')

@section('title', 'Roles — ElimuGrid')
@section('page-title', __('Roles & Permissions'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'roles'])
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ __(session('status')) }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ __($errors->first()) }}</div>
    @endif

    @include('school-admin.roles._roles_panel')
@endsection
