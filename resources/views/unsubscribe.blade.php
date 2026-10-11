<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $done ? 'Unsubscribed' : 'Unsubscribe' }} - {{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background: #f3f4f6; color: #111827; margin: 0; display: flex; min-height: 100vh; align-items: center; justify-content: center; }
        main { background: #fff; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); padding: 2rem; max-width: 26rem; width: 100%; box-sizing: border-box; margin: 1rem; }
        h1 { font-size: 1.25rem; margin: 0 0 0.75rem; }
        p { line-height: 1.5; margin: 0 0 1.25rem; color: #4b5563; }
        button { background: #111827; color: #fff; border: 0; border-radius: 0.5rem; padding: 0.625rem 1rem; font-size: 0.9rem; cursor: pointer; }
    </style>
</head>
<body>
<main>
    @if ($done)
        <h1>You have been unsubscribed</h1>
        <p>
            You will no longer receive {{ strtolower($channelTitle) }} notifications
            @if ($eventName) for "{{ $eventName }}" @else from {{ config('app.name') }} @endif.
        </p>
    @else
        <h1>Unsubscribe</h1>
        <p>
            Stop receiving {{ strtolower($channelTitle) }} notifications
            @if ($eventName) for "{{ $eventName }}" @else from {{ config('app.name') }} @endif?
        </p>
        <form method="POST" action="{{ $action }}">
            <button type="submit">Confirm unsubscribe</button>
        </form>
    @endif
</main>
</body>
</html>
