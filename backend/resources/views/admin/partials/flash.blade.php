@if (session('api_token'))
    <div class="alert" style="border-color:rgba(245,158,11,0.4);background:rgba(245,158,11,0.12);color:#fde68a;margin-bottom:1rem">
        <p style="margin:0 0 0.5rem"><strong>New API token generated</strong></p>
        @include('admin.accounts.partials.api-token-display', ['token' => session('api_token')])
        <p style="margin:0.75rem 0 0;font-size:0.875rem">Paste into MT5 EA <strong>InpApiToken</strong>. You can also view it anytime on the account page.</p>
    </div>
@endif

@if (session('status'))
    <div class="alert" style="border-color:rgba(16,185,129,0.3);background:rgba(16,185,129,0.1);color:#a7f3d0;margin-bottom:1rem">
        {{ session('status') }}
    </div>
@endif

@if ($errors->any())
    <div class="error-box" style="margin-bottom:1rem">{{ $errors->first() }}</div>
@endif
