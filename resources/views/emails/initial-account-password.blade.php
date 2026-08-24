<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>AIssessment Account</title>
</head>
<body style="font-family: Arial, sans-serif; color: #001550; line-height: 1.5;">
    <h1 style="font-size: 20px; color: #030351;">AIssessment account created</h1>

    <p>Hello {{ $user->displayName() }},</p>

    <p>Your AIssessment account has been created. Use the password below for your first login:</p>

    <p style="font-size: 22px; font-weight: bold; letter-spacing: 1px;">{{ $initialPassword }}</p>

    <p>You will be asked to create a new password after signing in.</p>

    <p>
        <a href="{{ route('login') }}" style="color: #2ea3f2; font-weight: bold;">Open AIssessment</a>
    </p>
</body>
</html>
