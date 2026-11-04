{{--
    A small live CSS mock of one ID card layout — not the real PDF markup,
    just enough of its look (colours, band/bar placement, photo position) to
    tell designs apart in a gallery, at a fraction of the cost of rendering
    an actual PDF per thumbnail.

    Expects: $layout (string key), $primary, $secondary (hex), $photoPosition ('left'|'right'|'center')
--}}
@php
    $photoPosition = $photoPosition ?? 'left';
    $photoBox = '<div style="width:26px;height:30px;background:#eef0f3;border:1px solid #cfd4dc;flex-shrink:0;"></div>';
    $lines = '<div style="height:6px;width:70%;background:#c7ccd6;border-radius:2px;margin-bottom:3px;"></div>'
        .'<div style="height:5px;width:50%;background:#d9dce2;border-radius:2px;"></div>';
    $row = $photoPosition === 'right'
        ? '<div style="flex:1">'.$lines.'</div>'.$photoBox
        : ($photoPosition === 'center'
            ? '<div style="text-align:center">'.$photoBox.'<div style="margin-top:4px">'.$lines.'</div></div>'
            : $photoBox.'<div style="flex:1">'.$lines.'</div>');
    $rowStyle = $photoPosition === 'center' ? 'display:block;' : 'display:flex;align-items:flex-start;gap:6px;';
@endphp

<div style="width:100%;aspect-ratio:1.6/1;border-radius:4px;overflow:hidden;font-family:inherit;border:1px solid #dfe3ea;
    @if ($layout === 'dark_executive') background:{{ $primary }};
    @else background:#fff;
    @endif
">
    @if ($layout === 'wave_header')
        <div style="background:{{ $primary }};padding:5px 8px;"><div style="height:6px;width:60%;background:#fff;border-radius:2px;opacity:.9;"></div></div>
        <div style="padding:7px 8px;{{ $rowStyle }}">{!! $row !!}</div>
    @elseif ($layout === 'side_bar')
        <div style="display:flex;height:100%;">
            <div style="width:8px;background:{{ $primary }};flex-shrink:0;"></div>
            <div style="padding:7px 8px;{{ $rowStyle }}">{!! $row !!}</div>
        </div>
    @elseif ($layout === 'minimal_mono')
        <div style="border:2px solid {{ $primary }};height:100%;box-sizing:border-box;padding:7px 8px;">
            <div style="height:5px;width:50%;background:#e2e5ea;border-radius:2px;margin-bottom:6px;"></div>
            <div style="{{ $rowStyle }}">{!! $row !!}</div>
        </div>
    @elseif ($layout === 'dark_executive')
        <div style="padding:7px 8px;">
            <div style="height:6px;width:55%;background:{{ $secondary }};border-radius:2px;margin-bottom:6px;"></div>
            <div style="display:flex;align-items:flex-start;gap:6px;">
                <div style="width:26px;height:30px;background:#2a2d38;border:1px solid {{ $secondary }};flex-shrink:0;"></div>
                <div style="flex:1"><div style="height:6px;width:70%;background:{{ $secondary }};opacity:.6;border-radius:2px;margin-bottom:3px;"></div><div style="height:5px;width:50%;background:#4a4e5c;border-radius:2px;"></div></div>
            </div>
        </div>
    @else {{-- classic --}}
        <div style="border:2px solid {{ $primary }};height:100%;box-sizing:border-box;padding:7px 8px;">
            <div style="height:5px;width:50%;background:{{ $secondary }};border-radius:2px;margin-bottom:6px;"></div>
            <div style="{{ $rowStyle }}">{!! $row !!}</div>
        </div>
    @endif
</div>
