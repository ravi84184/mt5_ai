<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Super Admin Login — MT5 AI</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
    <div class="login-page">
        <div class="login-box">
            <h1>MT5 AI Super Admin</h1>
            <p>Full platform control panel</p>

            @if ($errors->any())
                <div class="error-box" style="margin-top:1.5rem">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('admin.login.submit') }}" style="margin-top:1.5rem">
                @csrf
                <div class="form-group">
                    <label for="password">Admin password</label>
                    <input id="password" name="password" type="password" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%">Sign in</button>
            </form>
        </div>
    </div>
</body>
</html>
