@extends('layouts.admin')

@section('title', 'AI Log #'.$aiLog->id)
@section('heading', 'AI Log #'.$aiLog->id)
@section('subheading', $aiLog->analysis_type.' · '.$aiLog->provider)

@section('content')
    <p style="margin-bottom:1rem"><a href="{{ route('admin.ai-logs.index') }}">← AI logs</a></p>

    @foreach([
        'Input' => json_encode($aiLog->input_json, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),
        'System prompt' => $aiLog->system_prompt,
        'User prompt' => $aiLog->user_prompt,
        'Output' => $aiLog->output_json ? json_encode($aiLog->output_json, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) : ($aiLog->error_message ?? null),
    ] as $title => $content)
        @if ($content)
            <section class="panel" style="margin-bottom:1rem">
                <div class="panel-header"><h2>{{ $title }}</h2></div>
                <pre class="pre-block">{{ $content }}</pre>
            </section>
        @endif
    @endforeach
@endsection
