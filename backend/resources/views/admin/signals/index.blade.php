@extends('layouts.admin')

@section('title', 'Signals')
@section('heading', 'Signals')
@section('subheading', 'AI and manual entry decisions')

@section('content')
    <form method="GET" class="filters">
        <select name="status">
            <option value="">All statuses</option>
            @foreach (['PENDING', 'EXECUTED', 'REJECTED', 'CLOSED'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <select name="action">
            <option value="">All actions</option>
            @foreach (['BUY', 'SELL', 'WAIT'] as $a)
                <option value="{{ $a }}" @selected(request('action') === $a)>{{ $a }}</option>
            @endforeach
        </select>
        <input type="text" name="symbol" value="{{ request('symbol') }}" placeholder="Symbol">
        <button type="submit" class="btn">Filter</button>
        <a href="{{ route('admin.signals.index') }}" class="btn btn-ghost">Clear</a>
    </form>

    <section class="panel">
        <table>
            <thead>
                <tr>
                    <th>ID</th><th>Account</th><th>Symbol</th><th>Action</th><th>Conf</th>
                    <th>Status</th><th>Provider</th><th>Created</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($signals as $signal)
                    <tr>
                        <td><a href="{{ route('admin.signals.show', $signal) }}">#{{ $signal->id }}</a></td>
                        <td class="text-mono">{{ $signal->account?->mt5_login }}</td>
                        <td>{{ $signal->symbol }}</td>
                        <td>@include('components.status-badge', ['status' => $signal->action])</td>
                        <td>{{ $signal->confidence }}%</td>
                        <td>@include('components.status-badge', ['status' => $signal->status])</td>
                        <td class="text-muted">{{ $signal->ai_provider ?? '—' }}</td>
                        <td class="text-dim">{{ $signal->created_at }}</td>
                        <td>
                            @if (($signal->status->value ?? $signal->status) === 'PENDING')
                                <form method="POST" action="{{ route('admin.signals.cancel', $signal) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn" style="padding:0.2rem 0.5rem;font-size:0.75rem">Cancel</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty">No signals</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($signals->hasPages())<div class="pagination">{{ $signals->links() }}</div>@endif
    </section>
@endsection
