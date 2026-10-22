@props(['url'])
{{-- The logo is attached to each email and referenced by content ID (cid:logo),
     so it shows even when the site itself is not publicly reachable. --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<img src="cid:logo" width="64" height="64" alt="" style="display: block; margin: 0 auto 8px; width: 64px; height: 64px; border: 0;">
<span style="color: #102d43; font-size: 18px; font-weight: bold;">{!! $slot !!}</span>
</a>
</td>
</tr>
