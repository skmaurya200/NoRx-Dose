<x-mail::message>
# Sign-in verification

Someone entered the correct password for a **{{ $siteName }}** admin panel account and needs this code to finish signing in.

<x-mail::panel>
Your login verification OTP is **{{ $code }}**. This OTP will expire in {{ $expiresMinutes }} {{ Str::plural('minute', $expiresMinutes) }}.
</x-mail::panel>

<x-mail::table>
| | |
|:--|:--|
| Account | {{ $account->name }} |
| Username | {{ $account->username ?? $account->email }} |
| Role | {{ $account->roleLabel() }} |
| Requested | {{ $requestedAt->format('j M Y, H:i:s T') }} |
| IP address | {{ $ipAddress ?? 'Unknown' }} |
</x-mail::table>

**Security warning:** never share this code. Staff will never ask you for it. If you did not attempt to log in, please contact the administrator and change the account's password.

{{ $siteName }}
</x-mail::message>
