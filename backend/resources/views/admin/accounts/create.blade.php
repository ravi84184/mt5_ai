@extends('layouts.admin')

@section('title', 'Add Account')
@section('heading', 'Add MT5 account')
@section('subheading', 'Create account details and generate an API token for the EA')

@section('content')
    <p style="margin-bottom:1rem"><a href="{{ route('admin.accounts.index') }}">← Back to accounts</a></p>

    <section class="panel" style="max-width:40rem">
        <form method="POST" action="{{ route('admin.accounts.store') }}" style="padding:1rem">
            @csrf
            @include('admin.accounts.partials.form', ['account' => null])

            <div class="form-group" style="margin-top:1rem">
                <label><input type="checkbox" name="generate_api_token" value="1" checked> Generate API token now</label>
                <p class="text-muted" style="margin:0.25rem 0 0;font-size:0.875rem">Recommended — copy token into MT5 <code>InpApiToken</code> after save.</p>
            </div>

            <button type="submit" class="btn btn-primary">Create account</button>
        </form>
    </section>
@endsection
