@extends('layouts.dashboard')

@section('title', 'AI Log #'.$aiLog->id)
@section('heading', 'AI Log #'.$aiLog->id)
@section('subheading', $aiLog->analysis_type.' · '.$aiLog->provider.' · '.$aiLog->created_at)

@section('content')
    <p style="margin-bottom:1rem"><a href="{{ route('dashboard.ai-logs') }}">← Back to AI logs</a></p>

    <div class="grid-stats" style="margin-bottom:1.5rem">
        @foreach([
            'Type' => $aiLog->analysis_type,
            'Symbol' => $aiLog->symbol ?? '—',
            'Status' => $aiLog->status,
            'Duration' => $aiLog->duration_ms ? $aiLog->duration_ms.'ms' : '—',
            'Provider' => $aiLog->provider,
            'Model' => $aiLog->model ?? '—',
            'Account' => $aiLog->account?->mt5_login ?? '—',
            'Ticket' => $aiLog->ticket ?? '—',
        ] as $label => $value)
            <div class="card">
                <p class="card-label">{{ $label }}</p>
                <p style="margin:0.25rem 0 0;font-size:0.875rem">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    @if ($aiLog->status === 'error')
        <section class="panel" style="margin-bottom:1.5rem;border-color:rgba(244,63,94,0.3)">
            <div class="panel-header"><h2>Error</h2></div>
            <pre class="pre-block">{{ $aiLog->error_message }}</pre>
        </section>
    @endif

    @foreach([
        'Input Context' => json_encode($aiLog->input_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        'System Prompt' => $aiLog->system_prompt,
        'User Prompt' => $aiLog->user_prompt,
        'AI Output' => $aiLog->output_json ? json_encode($aiLog->output_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null,
    ] as $title => $content)
        @if ($content)
            <section class="panel" style="margin-bottom:1.5rem">
                <div class="panel-header"><h2>{{ $title }}</h2></div>
                <pre class="pre-block">{{ $content }}</pre>
            </section>
        @endif
    @endforeach
@endsection
