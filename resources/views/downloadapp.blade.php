<!DOCTYPE html>
<html>

<head>
    <title>Download Dayli App</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #fffaf5;
            color: #222;
        }

        .page {
            min-height: 100vh;
            padding: 30px 20px;
            text-align: center;
        }

        .logo {
            font-size: 32px;
            font-weight: bold;
            color: #ff7a1a;
            margin-top: 40px;
        }

        .title {
            font-size: 26px;
            font-weight: bold;
            margin-top: 25px;
        }

        .subtitle {
            font-size: 16px;
            color: #666;
            margin-top: 12px;
            line-height: 1.5;
        }

        .download-btn {
            display: inline-block;
            margin-top: 50px;
            background: #ff7a1a;
            color: white;
            padding: 15px 30px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: bold;
            box-shadow: 0 8px 20px rgba(255, 122, 26, 0.35);
        }
    </style>
</head>

<body>

    <div class="page">
        <div class="logo">Dayli</div>

        <div class="title">Download Dayli App</div>

        <div class="subtitle">
            Order milk, groceries, pooja items and manage your daily needs from one app.
        </div>
        <a class="download-btn"
            href="{{ asset('downloadapp/dayli-delivery-20260602.apk') }}"
            download>
            Download App
        </a>

    </div>

</body>

</html>