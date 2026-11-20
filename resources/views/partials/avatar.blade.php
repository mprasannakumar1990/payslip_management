{{-- Employee avatar: photo, or coloured initials. $e = Employee, $size = px (default 36), $ring = optional extra class --}}
@php($sz = $size ?? 36)
@if ($e?->photo_path)
    <img src="{{ $e->photoUrl() }}" alt="{{ $e->name }}" class="emp-avatar {{ $ring ?? '' }}" style="width:{{ $sz }}px;height:{{ $sz }}px" loading="lazy">
@else
    <span class="emp-avatar emp-initials {{ $ring ?? '' }}" style="width:{{ $sz }}px;height:{{ $sz }}px;font-size:{{ round($sz * .38) }}px;--h:{{ $e?->avatarHue() ?? 260 }}" aria-hidden="true">{{ $e?->initials() ?? '?' }}</span>
@endif
