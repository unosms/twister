<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Two-Factor Authentication Code</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f6f6f8; padding:24px;">
    <div style="max-width:520px; margin:0 auto; background:#ffffff; padding:24px; border-radius:12px; border:1px solid #e2e8f0;">
        <h2 style="margin:0 0 8px; color:#0f172a;">Your login code</h2>
        <p style="margin:0 0 16px; color:#475569;">Use this code to finish signing in to the admin dashboard.</p>
        <div style="font-size:28px; font-weight:700; letter-spacing:6px; color:#135bec; margin:16px 0;">
            {{ $code }}
        </div>
        <p style="margin:16px 0 0; color:#64748b; font-size:12px;">This code expires in 10 minutes. If you didn’t request this, you can ignore this email.</p>
    </div>
</body>
</html>
