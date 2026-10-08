<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $siteName }} — Temporarily locked</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #1f2937 0%, #111827 100%); color: #fff;
            padding: 24px; text-align: center;
        }
        .card {
            background: rgba(255,255,255,.08); backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,.18); border-radius: 24px;
            padding: 48px 40px; max-width: 480px;
            animation: rise .6s ease-out;
        }
        @keyframes rise { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: none; } }
        .emoji { font-size: 56px; margin-bottom: 16px; }
        h1 { font-size: 24px; margin-bottom: 12px; }
        p { opacity: .85; line-height: 1.6; }
        .brand { margin-top: 24px; font-weight: 700; letter-spacing: 1px; opacity: .7; }
    </style>
</head>
<body>
    <div class="card">
        <div class="emoji">🔐</div>
        <h1>This app is temporarily locked</h1>
        <p>The owner has paused this installation. Please check back later.</p>
        <div class="brand">{{ $siteName }}</div>
    </div>
</body>
</html>
