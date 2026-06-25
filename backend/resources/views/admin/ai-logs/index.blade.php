@extends('layouts.admin')

@section('title', 'AI Logs')
@section('heading', 'AI interaction logs')
@section('subheading', 'Prompts, responses, and errors')

@section('content')
    <form method="GET" class="filters">
        <select name="type"><option value="">All types</option><option value="entry">entry</option><option value="position">position</option></select>
        <select name="status"><option value="">All</option><option value="success">success</option><option value="error">error</option></select>
        <input type="text" name="symbol" value="{{ request('symbol') }}" placeholder="Symbol">
        <button type="submit" class="btn">Filter</button>
    </form>

    <section class="panel">
        <table>
            <thead><tr><th>ID</th><th>Type</th><th>Symbol</th><th>Provider</th><th>Status</th><th>Action</th><th>ms</th><th></th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>#{{ $log->id }}</td>
                        <td>{{ $log->analysis_type }}</td>
                        <td>{{ $log->symbol ?? '—' }}</td>
                        <td>{{ $log->provider }}</td>
                        <td>@include('components.status-badge', ['status' => strtoupper($log->status)])</td>
                        <td>{{ $log->output_json['action'] ?? '—' }}</td>
                        <td>{{ $log->duration_ms ?? '—' }}</td>
                        <td><a href="{{ route('admin.ai-logs.show', $log) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">No logs</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($logs->hasPages())<div class="pagination">{{ $logs->links() }}</div>@endif
    </section>
@endsection
