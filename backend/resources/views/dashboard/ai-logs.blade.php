@extends('layouts.dashboard')

@section('title', 'AI Logs')
@section('heading', 'AI Logs')
@section('subheading', 'Prompts, responses, and errors from AI analysis')

@section('content')
    <form method="GET" class="filters">
        <select name="type">
            <option value="">All types</option>
            @foreach (['entry', 'position'] as $t)
                <option value="{{ $t }}" @selected(request('type') === $t)>{{ $t }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="">All statuses</option>
            @foreach (['success', 'error'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <input type="text" name="symbol" value="{{ request('symbol') }}" placeholder="Symbol">
        <button type="submit" class="btn">Filter</button>
        <a href="{{ route('dashboard.ai-logs') }}" class="btn btn-ghost">Clear</a>
    </form>

    <section class="panel">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Symbol</th>
                    <th>Provider</th>
                    <th>Status</th>
                    <th>Action</th>
                    <th>Duration</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-mono text-dim">#{{ $log->id }}</td>
                        <td>{{ $log->analysis_type }}</td>
                        <td>{{ $log->symbol ?? '—' }}</td>
                        <td class="text-muted">{{ $log->provider }}</td>
                        <td>@include('components.status-badge', ['status' => strtoupper($log->status)])</td>
                        <td>{{ $log->output_json['action'] ?? ($log->status === 'error' ? 'ERROR' : '—') }}</td>
                        <td class="text-muted">{{ $log->duration_ms ? $log->duration_ms.'ms' : '—' }}</td>
                        <td class="text-dim">{{ $log->created_at }}</td>
                        <td><a href="{{ route('dashboard.ai-logs.show', $log) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty">No AI logs found</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($logs->hasPages())
            <div class="pagination">{{ $logs->links() }}</div>
        @endif
    </section>
@endsection
