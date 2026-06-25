@extends('layouts.dashboard')

@section('title', 'AI Logs')
@section('heading', 'AI Logs')
@section('subheading', 'Prompts, responses, and errors from AI analysis')

@section('content')
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <select name="type" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white">
            <option value="">All types</option>
            @foreach (['entry', 'position'] as $t)
                <option value="{{ $t }}" @selected(request('type') === $t)>{{ $t }}</option>
            @endforeach
        </select>
        <select name="status" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white">
            <option value="">All statuses</option>
            @foreach (['success', 'error'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <input type="text" name="symbol" value="{{ request('symbol') }}" placeholder="Symbol"
               class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white">
        <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Filter</button>
        <a href="{{ route('dashboard.ai-logs') }}" class="rounded-lg px-4 py-2 text-sm text-slate-400 hover:text-white">Clear</a>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-950/50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Symbol</th>
                        <th class="px-4 py-3">Provider</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">Duration</th>
                        <th class="px-4 py-3">Created</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="px-4 py-3 font-mono text-slate-400">#{{ $log->id }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ $log->analysis_type }}</td>
                            <td class="px-4 py-3 text-white">{{ $log->symbol ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $log->provider }}</td>
                            <td class="px-4 py-3">@include('components.status-badge', ['status' => strtoupper($log->status)])</td>
                            <td class="px-4 py-3 text-slate-300">{{ $log->output_json['action'] ?? ($log->status === 'error' ? 'ERROR' : '—') }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $log->duration_ms ? $log->duration_ms.'ms' : '—' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $log->created_at }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('dashboard.ai-logs.show', $log) }}" class="text-sky-400 hover:text-sky-300">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-10 text-center text-slate-500">No AI logs found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            <div class="border-t border-slate-800 px-4 py-3">{{ $logs->links() }}</div>
        @endif
    </div>
@endsection
