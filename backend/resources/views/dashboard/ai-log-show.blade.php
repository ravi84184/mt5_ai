@extends('layouts.dashboard')

@section('title', 'AI Log #'.$aiLog->id)
@section('heading', 'AI Log #'.$aiLog->id)
@section('subheading', $aiLog->analysis_type.' · '.$aiLog->provider.' · '.$aiLog->created_at)

@section('content')
    <div class="mb-4">
        <a href="{{ route('dashboard.ai-logs') }}" class="text-sm text-sky-400 hover:text-sky-300">← Back to AI logs</a>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                <p class="text-xs uppercase text-slate-500">{{ $label }}</p>
                <p class="mt-1 text-sm text-white">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    @if ($aiLog->status === 'error')
        <section class="mb-6 rounded-xl border border-rose-500/30 bg-rose-500/10 p-4">
            <h2 class="mb-2 text-sm font-medium text-rose-200">Error</h2>
            <pre class="overflow-x-auto whitespace-pre-wrap text-sm text-rose-100">{{ $aiLog->error_message }}</pre>
        </section>
    @endif

    @foreach([
        'Input Context' => json_encode($aiLog->input_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        'System Prompt' => $aiLog->system_prompt,
        'User Prompt' => $aiLog->user_prompt,
        'AI Output' => $aiLog->output_json ? json_encode($aiLog->output_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null,
    ] as $title => $content)
        @if ($content)
            <section class="mb-6 rounded-xl border border-slate-800 bg-slate-900">
                <div class="border-b border-slate-800 px-4 py-3">
                    <h2 class="text-sm font-medium text-white">{{ $title }}</h2>
                </div>
                <pre class="max-h-96 overflow-auto p-4 text-xs leading-relaxed text-slate-300">{{ $content }}</pre>
            </section>
        @endif
    @endforeach
@endsection
