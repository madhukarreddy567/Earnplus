<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 — Something went wrong</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #0f9d58 0%, #0b6e3f 100%); color: #fff;
            padding: 24px; text-align: center;
        }
        .card {
            background: rgba(255,255,255,.12); backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,.25); border-radius: 24px;
            padding: 48px 40px; max-width: 480px;
            animation: rise .6s ease-out;
        }
        @keyframes rise { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: none; } }
        .emoji { font-size: 56px; margin-bottom: 16px; }
        h1 { font-size: 24px; margin-bottom: 12px; }
        p { opacity: .9; line-height: 1.6; margin-bottom: 24px; }
        a.btn {
            display: inline-block; background: #fff; color: #0b6e3f; font-weight: 700;
            padding: 12px 28px; border-radius: 999px; text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="emoji">⚠️</div>
        <h1>Something went wrong</h1>
        <p>We hit an unexpected error. Our team has been notified — please try again shortly.</p>
        <a class="btn" href="/">Go home</a>
    </div>
</body>
</html>
