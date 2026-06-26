@extends('layouts.admin')

@section('title', 'Failed Job #'.$job->id)
@section('heading', 'Failed job #'.$job->id)
@section('subheading', $job->job_name.' · '.$job->failed_at)

@section('content')
    <p style="margin-bottom:1rem"><a href="{{ route('admin.system.queue') }}">← Queue monitor</a></p>

    <section class="panel" style="margin-bottom:1rem">
        <div class="panel-header"><h2>Summary</h2></div>
        <div class="dl-row"><dt>Job</dt><dd class="text-mono">{{ $job->job_name }}</dd></div>
        <div class="dl-row"><dt>Queue</dt><dd>{{ $job->queue }}</dd></div>
        <div class="dl-row"><dt>Connection</dt><dd>{{ $job->connection }}</dd></div>
        <div class="dl-row"><dt>Failed at</dt><dd>{{ $job->failed_at }}</dd></div>
        <div class="dl-row"><dt>Error</dt><dd>{{ $job->exception_summary }}</dd></div>
    </section>

    <section class="panel" style="margin-bottom:1rem">
        <div class="panel-header"><h2>Exception</h2></div>
        <pre class="pre-block">{{ $job->exception }}</pre>
    </section>

    <section class="panel">
        <div class="panel-header"><h2>Payload</h2></div>
        <pre class="pre-block">{{ $job->payload }}</pre>
    </section>
@endsection
