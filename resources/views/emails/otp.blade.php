<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication Code</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f4f6f8;
            color: #333333;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 560px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #e1e4e8;
            padding: 36px 32px;
        }
        .logo {
            text-align: center;
            margin-bottom: 24px;
        }
        .logo h2 {
            margin: 0;
            color: #0b2545;
            font-size: 24px;
            letter-spacing: 0.5px;
        }
        .title {
            font-size: 20px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 12px;
            text-align: center;
        }
        .description {
            font-size: 15px;
            line-height: 1.5;
            color: #4b5563;
            text-align: center;
            margin-bottom: 24px;
        }
        .code-box {
            background-color: #f0f4f8;
            border: 2px dashed #0b2545;
            border-radius: 8px;
            padding: 18px;
            text-align: center;
            letter-spacing: 8px;
            font-size: 32px;
            font-weight: 700;
            color: #0b2545;
            font-family: 'Courier New', Courier, monospace;
            margin: 20px 0;
        }
        .warning {
            font-size: 13px;
            line-height: 1.4;
            color: #6b7280;
            background: #f9fafb;
            border-left: 3px solid #f59e0b;
            padding: 12px 14px;
            margin-top: 24px;
            border-radius: 0 4px 4px 0;
        }
        .footer {
            margin-top: 32px;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h2>i-Sense V3</h2>
        </div>
        <div class="title">Two-Factor Authentication</div>
        <p class="description">
            Hello {{ $user->first_name ?? 'User' }},<br>
            Please use the following 6-digit verification code to complete your login:
        </p>
        <div class="code-box">
            {{ $otpCode }}
        </div>
        <p class="description" style="margin-bottom: 0;">
            This code will expire in <strong>5 minutes</strong> and can only be used once.
        </p>
        <div class="warning">
            <strong>Security Notice:</strong> If you did not initiate this login request, your password may be compromised. Please change your password immediately or contact your administrator.
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} i-Sense. All rights reserved.
        </div>
    </div>
</body>
</html>

