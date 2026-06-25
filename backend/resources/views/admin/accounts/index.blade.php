@extends('layouts.admin')

@section('title', 'Accounts')
@section('heading', 'Accounts')
@section('subheading', 'Manage AI provider, symbols, and trading per MT5 account')

@section('content')
    <section class="panel">
        <table>
            <thead>
                <tr>
                    <th>MT5 Login</th>
                    <th>AI Provider</th>
                    <th>Symbols</th>
                    <th>Trading</th>
                    <th>Balance</th>
                    <th>Open</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($accounts as $account)
                    <tr>
                        <td class="text-mono">
                            <a href="{{ route('admin.accounts.show', $account) }}">{{ $account->mt5_login }}</a>
                        </td>
                        <td class="text-muted">{{ $account->resolvedAiProvider() }}</td>
                        <td class="truncate" title="{{ implode(', ', $account->configuredSymbols()) }}">
                            @if ($account->hasSymbolRestrictions())
                                {{ implode(', ', $account->configuredSymbols()) }}
                            @else
                                <span class="text-dim">Not configured</span>
                            @endif
                        </td>
                        <td>@include('components.status-badge', ['status' => $account->trading_enabled ? 'OPEN' : 'CLOSED'])</td>
                        <td>{{ number_format((float) $account->balance, 2) }}</td>
                        <td>{{ $account->open_trades_count }}</td>
                        <td style="white-space:nowrap">
                            <a href="{{ route('admin.accounts.edit', $account) }}">Settings</a>
                            ·
                            <form method="POST" action="{{ route('admin.accounts.toggle-trading', $account) }}" style="display:inline">
                                @csrf
                                <button type="submit" class="btn btn-ghost" style="padding:0;font-size:0.875rem">
                                    {{ $account->trading_enabled ? 'Disable' : 'Enable' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No accounts yet. Attach EA in MT5 to register.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($accounts->hasPages())
            <div class="pagination">{{ $accounts->links() }}</div>
        @endif
    </section>
@endsection
