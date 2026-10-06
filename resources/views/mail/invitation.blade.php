<!DOCTYPE html>
<html lang="{{ $mailLocale }}" dir="{{ $dir }}">
<head><meta charset="utf-8"></head>
<body style="font-family: Tahoma, Arial, sans-serif; background: #f6f7f5; padding: 24px; margin: 0;">
    <div style="max-width: 520px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e5e1; border-radius: 12px; padding: 24px; text-align: start;">
        <p style="color: #0f6e56; font-weight: bold; margin: 0 0 12px;">{{ $schoolName }}</p>
        <p style="color: #1c2326; font-size: 16px; line-height: 1.7;">{{ __('Hello :name,', ['name' => $invitation->name], $mailLocale) }}</p>
        <p style="color: #1c2326; font-size: 16px; line-height: 1.7;">{{ __('You have been invited to join :school on Madrasa.', ['school' => $schoolName], $mailLocale) }}</p>
        <p><a href="{{ $url }}" style="display: inline-block; background: #0f6e56; color: #ffffff; padding: 10px 20px; border-radius: 8px; text-decoration: none;">{{ __('Accept invitation', [], $mailLocale) }}</a></p>
        <p style="color: #5d676b; font-size: 13px;">{{ __('This link expires in 7 days.', [], $mailLocale) }}</p>
    </div>
</body>
</html>
