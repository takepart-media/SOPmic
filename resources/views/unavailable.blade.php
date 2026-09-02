{{--
    Served with a 503 when the SOP database throws. Deliberately standalone:
    no statamic::layout, no CP assets, no view composers, nothing that could
    touch the thing that just failed. The logout link is the escape hatch —
    /cp/auth/logout sits outside the gated middleware group.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ __('sop::messages.unavailable.title') }}</title>
    <style>
        :root { color-scheme: light dark; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: #f4f4f5;
            color: #18181b;
            font: 16px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        .card {
            max-width: 32rem;
            width: 100%;
            padding: 2rem;
            border: 1px solid #d4d4d8;
            border-radius: .75rem;
            background: #fff;
        }
        h1 { margin: 0 0 .75rem; font-size: 1.25rem; }
        p { margin: 0 0 1.5rem; }
        a { color: inherit; }
        @media (prefers-color-scheme: dark) {
            body { background: #18181b; color: #e4e4e7; }
            .card { background: #27272a; border-color: #3f3f46; }
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ __('sop::messages.unavailable.title') }}</h1>
        <p>{{ __('sop::messages.unavailable.body') }}</p>
        <a href="{{ cp_route('logout') }}">{{ __('sop::messages.unavailable.logout') }}</a>
    </div>
</body>
</html>
