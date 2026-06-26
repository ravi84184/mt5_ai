@extends('layouts.admin')

@section('title', 'Edit Account')
@section('heading', 'Edit account '.$account->mt5_login)
@section('subheading', 'AI provider, symbols, risk overrides')

@section('content')
    <p style="margin-bottom:1rem"><a href="{{ route('admin.accounts.show', $account) }}">← Back to account</a></p>

    <section class="panel" style="max-width:40rem">
        <form method="POST" action="{{ route('admin.accounts.update', $account) }}" style="padding:1rem">
            @csrf
            @method('PUT')
            @include('admin.accounts.partials.form', ['account' => $account])
            <button type="submit" class="btn btn-primary">Save settings</button>
        </form>
    </section>
@endsection
