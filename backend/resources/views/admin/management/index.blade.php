@extends('layouts.admin')

@section('title', 'Management Queue')
@section('heading', 'Position management queue')
@section('subheading', 'Pending CLOSE / MOVE_SL actions for EA')

@section('content')
    <form method="GET" class="filters">
        <select name="status">
            <option value="">Pending + Fetched</option>
            <option value="PENDING" @selected(request('status')==='PENDING')>PENDING</option>
            <option value="FETCHED" @selected(request('status')==='FETCHED')>FETCHED</option>
            <option value="APPLIED" @selected(request('status')==='APPLIED')>APPLIED</option>
            <option value="CANCELLED" @selected(request('status')==='CANCELLED')>CANCELLED</option>
        </select>
        <button type="submit" class="btn">Filter</button>
    </form>

    <section class="panel">
        <table>
            <thead><tr><th>ID</th><th>Account</th><th>Ticket</th><th>Action</th><th>New SL</th><th>Status</th><th>Reason</th><th></th></tr></thead>
            <tbody>
                @forelse ($decisions as $d)
                    <tr>
                        <td>#{{ $d->id }}</td>
                        <td>{{ $d->account?->mt5_login }}</td>
                        <td class="text-mono">{{ $d->ticket }}</td>
                        <td>@include('components.status-badge', ['status' => $d->action])</td>
                        <td>{{ $d->new_sl ?? '—' }}</td>
                        <td>@include('components.status-badge', ['status' => $d->status])</td>
                        <td class="truncate text-muted">{{ $d->reason ?? '—' }}</td>
                        <td>
                            @if (in_array($d->status, ['PENDING', 'FETCHED']))
                                <form method="POST" action="{{ route('admin.management.cancel', $d) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn" style="padding:0.2rem 0.5rem;font-size:0.75rem">Cancel</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">No management actions</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($decisions->hasPages())<div class="pagination">{{ $decisions->links() }}</div>@endif
    </section>
@endsection
