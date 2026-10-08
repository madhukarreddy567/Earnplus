@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@php($epMailHeader = branding_url('branding_email_header'))
@if ($epMailHeader)
<img src="{{ $epMailHeader }}" alt="{{ setting('site_name', 'EarnPlus') }}" style="max-width:600px;width:100%;height:auto;border:0;">
@else
{{ setting('site_name', 'EarnPlus') }}
@endif
</a>
</td>
</tr>
