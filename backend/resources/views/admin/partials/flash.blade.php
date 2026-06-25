@if (session('status'))
    <div class="alert" style="border-color:rgba(16,185,129,0.3);background:rgba(16,185,129,0.1);color:#a7f3d0;margin-bottom:1rem">
        {{ session('status') }}
    </div>
@endif

@if ($errors->any())
    <div class="error-box" style="margin-bottom:1rem">{{ $errors->first() }}</div>
@endif
