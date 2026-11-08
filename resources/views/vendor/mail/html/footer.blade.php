<tr>
<td align="center" style="padding-top: 24px;">
<table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 auto; text-align: center;">
    <tr>
        <td style="text-align: center; padding-bottom: 12px;">
            <a href="{{ config('app.url') }}/terms" style="color: #64748B; font-size: 13px; text-decoration: none; margin: 0 10px; font-weight: 500;">Terms & Conditions</a>
            <span style="color: #CBD5E1;">•</span>
            <a href="{{ config('app.url') }}/privacy" style="color: #64748B; font-size: 13px; text-decoration: none; margin: 0 10px; font-weight: 500;">Privacy Policy</a>
            <span style="color: #CBD5E1;">•</span>
            <a href="mailto:{{ config('mail.support_address', 'support@livebids.ng') }}" style="color: #64748B; font-size: 13px; text-decoration: none; margin: 0 10px; font-weight: 500;">Support</a>
        </td>
    </tr>
    <tr>
        <td style="text-align: center; color: #94A3B8; font-size: 12px; line-height: 18px; padding-bottom: 8px;">
            &copy; {{ date('Y') }} {{ config('app.name', 'LiveBids') }}. All rights reserved.
        </td>
    </tr>
    <tr>
        <td style="text-align: center; color: #94A3B8; font-size: 11px; line-height: 16px;">
            This is an automated security notification. If you didn't initiate this request, please contact our security team immediately at {{ config('mail.support_address', 'support@livebids.ng') }}.
        </td>
    </tr>
</table>
</td>
</tr>

