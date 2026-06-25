@extends('layouts.admin')

@section('title', 'Queue Monitor')
@section('heading', 'Queue monitor')
@section('subheading', 'Pending and failed background jobs')

@section('content')
    <p style="margin-bottom:1rem"><a href="{{ route('admin.system.index') }}">← System</a></p>

    <div class="grid-stats" style="margin-bottom:1.5rem">
        <div class="card"><p class="card-label">Pending</p><p class="card-value">{{ $pending }}</p></div>
        <div class="card"><p class="card-label">Failed (shown)</p><p class="card-value">{{ $failedJobs->count() }}</p></div>
    </div>

    <div style="margin-bottom:1rem;display:flex;gap:0.5rem">
        <form method="POST" action="{{ route('admin.system.queue.retry-all') }}">@csrf<button class="btn">Retry all failed</button></form>
        <form method="POST" action="{{ route('admin.system.queue.flush-failed') }}" onsubmit="return confirm('Clear all failed jobs?')">@csrf<button class="btn" style="background:#be123c">Flush failed</button></form>
    </div>

    <section class="panel">
        <table>
            <thead><tr><th>ID</th><th>Queue</th><th>Failed at</th><th>Exception</th></tr></thead>
            <tbody>
                @forelse ($failedJobs as $job)
                    <tr>
                        <td>{{ $job->id }}</td>
                        <td>{{ $job->queue }}</td>
                        <td class="text-dim">{{ $job->failed_at }}</td>
                        <td class="truncate text-muted" title="{{ $job->exception }}">{{ \Illuminate\Support\Str::limit($job->exception, 80) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">No failed jobs</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection
