@extends('layouts.admin')

@section('title', 'Queue Monitor')
@section('heading', 'Queue monitor')
@section('subheading', 'Track pending jobs, slow workers, and failures')

@section('content')
    <p style="margin-bottom:1rem"><a href="{{ route('admin.system.index') }}">← System</a></p>

    <div class="grid-stats" style="margin-bottom:1.5rem">
        <div class="card"><p class="card-label">Pending</p><p class="card-value">{{ $pending }}</p></div>
        <div class="card"><p class="card-label">Failed</p><p class="card-value">{{ $failed }}</p></div>
    </div>

    @if ($pending > 0 && $pendingJobs->where('waiting_seconds', '>', 120)->isNotEmpty())
        <div class="alert" style="border-color:rgba(245,158,11,0.4);background:rgba(245,158,11,0.12);color:#fde68a;margin-bottom:1rem">
            Some jobs have been waiting over 2 minutes. Check that the queue worker is running
            (<code>sudo supervisorctl status</code>) or that a job is not stuck on a slow AI API call (up to ~120s per job).
        </div>
    @endif

    <section class="panel" style="margin-bottom:1.5rem">
        <div class="panel-header"><h2>Pending jobs</h2></div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Job</th>
                    <th>Status</th>
                    <th>Waiting</th>
                    <th>Attempts</th>
                    <th>Queued at</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pendingJobs as $job)
                    <tr>
                        <td>{{ $job->id }}</td>
                        <td class="text-mono">{{ $job->job_name }}</td>
                        <td>@include('components.status-badge', ['status' => $job->status === 'running' ? 'EXECUTED' : 'PENDING'])</td>
                        <td>{{ $job->waiting_seconds }}s</td>
                        <td>{{ $job->attempts }}</td>
                        <td class="text-dim">{{ $job->created_at }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No pending jobs</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="panel-footer text-muted" style="font-size:0.875rem">
            AI analysis jobs often take 30–120 seconds while calling OpenAI / Anthropic / Gemini.
        </div>
    </section>

    <div style="margin-bottom:1rem;display:flex;gap:0.5rem;flex-wrap:wrap">
        <form method="POST" action="{{ route('admin.system.queue.retry-all') }}">@csrf<button class="btn">Retry all failed</button></form>
        <form method="POST" action="{{ route('admin.system.queue.flush-failed') }}" onsubmit="return confirm('Clear all failed jobs?')">@csrf<button class="btn" style="background:#be123c">Flush failed</button></form>
        <a href="{{ route('admin.ai-logs.index') }}" class="btn">AI logs</a>
    </div>

    <section class="panel">
        <div class="panel-header"><h2>Failed jobs</h2></div>
        <table>
            <thead><tr><th>ID</th><th>Job</th><th>Failed at</th><th>Error</th><th></th></tr></thead>
            <tbody>
                @forelse ($failedJobs as $job)
                    <tr>
                        <td>{{ $job->id }}</td>
                        <td class="text-mono">{{ $job->job_name }}</td>
                        <td class="text-dim">{{ $job->failed_at }}</td>
                        <td class="truncate text-muted" title="{{ $job->exception_summary }}">{{ $job->exception_summary }}</td>
                        <td><a href="{{ route('admin.system.queue.failed', $job->id) }}">Details</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No failed jobs</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="panel-footer text-muted" style="font-size:0.875rem">
            AI API errors are also logged under <a href="{{ route('admin.ai-logs.index') }}">AI Logs</a> with full prompts and responses.
        </div>
    </section>
@endsection
