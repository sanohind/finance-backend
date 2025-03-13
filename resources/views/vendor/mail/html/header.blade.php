@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@php
    $logoPath = public_path('storage/public/Logo-sanoh.png');
    $logoBase64 = '';
    if (file_exists($logoPath)) {
        $logoData = file_get_contents($logoPath);
        $logoBase64 = base64_encode($logoData);
    }
@endphp
<img class="logo" src="data:image/png;base64,{{ $logoBase64 }}" alt="Sanoh Logo" />
</a>
</td>
</tr>
