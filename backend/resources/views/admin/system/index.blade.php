@extends('layouts.admin')

@section('title', 'System')
@section('heading', 'System configuration')
@section('subheading', 'Global AI, risk settings, and API health')

@section('content')
    <div style="margin-bottom:1rem">
        <a href="{{ route('admin.system.settings') }}" class="btn btn-primary">Edit trading settings</a>
    </div>

    <div class="grid-2">
        <section class="panel">
            <div class="panel-header"><h2>Application</h2></div>
            @foreach($config as $key => $value)
                <div class="dl-row"><dt>{{ str_replace('_', ' ', ucfirst($key)) }}</dt><dd>{{ is_bool($value) ? ($value ? 'Yes' : 'No') : $value }}</dd></div>
            @endforeach
        </section>

        <section class="panel">
            <div class="panel-header"><h2>Queue</h2></div>
            <div class="dl-row"><dt>Pending jobs</dt><dd>{{ $queue['pending'] }}</dd></div>
            <div class="dl-row"><dt>Failed jobs</dt><dd>{{ $queue['failed'] }}</dd></div>
            <div style="padding:1rem;display:flex;gap:0.5rem;flex-wrap:wrap">
                <a href="{{ route('admin.system.queue') }}" class="btn">Queue monitor</a>
            </div>
        </section>
    </div>

    <section class="panel" style="margin-top:1.5rem">
        <div class="panel-header"><h2>Settings</h2></div>
        <div class="panel-footer">
            AI keys, symbols, risk limits, and trading sessions are managed in
            <a href="{{ route('admin.system.settings') }}">Trading settings</a>
            (not <code>.env</code>).
        </div>
    </section>

    <section class="panel" style="margin-top:1.5rem">
        <div class="panel-header"><h2>CLI tools</h2></div>
        <div class="panel-footer">
            <code>php artisan mt5:diagnose</code> ·
            <code>php artisan ai:logs --limit=10</code> ·
            <code>php artisan mt5:routes-fix</code>
        </div>
    </section>
@endsection
