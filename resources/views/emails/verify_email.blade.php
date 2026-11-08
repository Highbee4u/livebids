<x-mail::message>
<h2 style="color: #0F172A; font-size: 20px; font-weight: 700; margin-top: 0; margin-bottom: 16px;">
    Verify Your Email Address
</h2>

<p style="color: #334155; font-size: 15px; line-height: 24px; margin-bottom: 20px;">
    Hello {{ $name }},
</p>

<p style="color: #334155; font-size: 15px; line-height: 24px; margin-bottom: 24px;">
    Thank you for registering on <strong>{{ config('app.name', 'LiveBids') }}</strong>. Please use the verification code below to verify your email address and activate your account:
</p>

<div style="text-align: center; margin: 32px 0;">
    <div style="display: inline-block; background-color: #F1F5F9; border: 1px dashed #CBD5E1; border-radius: 12px; padding: 18px 36px;">
        <span style="font-family: 'Courier New', Courier, monospace; font-size: 34px; font-weight: 800; letter-spacing: 8px; color: #2563EB;">{{ $otp }}</span>
    </div>
    <p style="color: #64748B; font-size: 13px; margin-top: 12px; margin-bottom: 0;">
        ⏱️ This code expires in <strong>{{ $expiresInMinutes }} minutes</strong>.
    </p>
</div>

<p style="color: #475569; font-size: 14px; line-height: 22px; margin-bottom: 20px;">
    <strong>Security reminder:</strong> Never share your verification code with anyone. LiveBids staff will never ask for your code via phone, chat, or email.
</p>

<p style="color: #64748B; font-size: 13px; line-height: 20px; margin-bottom: 28px;">
    If you did not register for a LiveBids account, you can safely ignore this email.
</p>

<p style="color: #334155; font-size: 14px; line-height: 22px; margin-bottom: 0;">
    Best regards,<br>
    <strong>The {{ config('app.name', 'LiveBids') }} Team</strong>
</p>
</x-mail::message>

