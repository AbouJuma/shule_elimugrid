{{--
    A small live CSS mock of one certificate layout — see id-cards/partials/preview.blade.php
    for why this exists as a cheap CSS mock rather than a real per-thumbnail PDF render.

    Expects: $layout (string key), $primary, $secondary (hex)
--}}
<div style="width:100%;aspect-ratio:1.4/1;border-radius:4px;overflow:hidden;background:#fff;border:1px solid #dfe3ea;
    display:flex;align-items:center;justify-content:center;text-align:center;position:relative;padding:10px;box-sizing:border-box;
">
    @if ($layout === 'gold_ribbon')
        <div style="position:absolute;inset:5px;border:3px double {{ $secondary }};"></div>
        <div>
            <div style="height:5px;width:80px;background:{{ $primary }};border-radius:2px;margin:0 auto 6px;"></div>
            <div style="height:8px;width:110px;background:{{ $secondary }};border-radius:2px;margin:0 auto 8px;"></div>
            <div style="width:16px;height:16px;border-radius:50%;border:2px solid {{ $secondary }};margin:0 auto;"></div>
        </div>
    @elseif ($layout === 'modern_diagonal')
        <div style="position:absolute;top:-14px;right:-14px;width:0;height:0;border-style:solid;border-width:30px 30px 0 0;border-color:{{ $primary }} transparent transparent transparent;"></div>
        <div style="position:absolute;inset:5px;border:1px solid {{ $primary }};"></div>
        <div>
            <div style="height:5px;width:80px;background:{{ $primary }};border-radius:2px;margin:0 auto 6px;"></div>
            <div style="height:8px;width:110px;background:{{ $secondary }};border-radius:2px;margin:0 auto;"></div>
        </div>
    @elseif ($layout === 'elegant_serif')
        <div style="position:absolute;inset:5px;border:1px solid {{ $secondary }};"></div>
        <div style="position:absolute;top:14px;left:10px;right:10px;border-top:1px solid {{ $secondary }};"></div>
        <div style="position:absolute;bottom:14px;left:10px;right:10px;border-top:1px solid {{ $secondary }};"></div>
        <div>
            <div style="height:5px;width:70px;background:{{ $primary }};border-radius:2px;margin:0 auto 8px;"></div>
            <div style="height:8px;width:100px;background:{{ $primary }};border-radius:2px;margin:0 auto;"></div>
        </div>
    @elseif ($layout === 'minimal_thin')
        <div>
            <div style="height:4px;width:60px;background:{{ $secondary }};border-radius:2px;margin:0 auto 6px;"></div>
            <div style="width:30px;border-top:2px solid {{ $primary }};margin:0 auto 6px;"></div>
            <div style="height:8px;width:100px;background:{{ $primary }};border-radius:2px;margin:0 auto;"></div>
        </div>
    @else {{-- classic --}}
        <div style="position:absolute;inset:5px;border:2px double {{ $primary }};"></div>
        <div style="position:absolute;inset:9px;border:1px solid {{ $secondary }};"></div>
        <div>
            <div style="height:5px;width:80px;background:{{ $primary }};border-radius:2px;margin:0 auto 6px;"></div>
            <div style="height:8px;width:110px;background:{{ $secondary }};border-radius:2px;margin:0 auto;"></div>
        </div>
    @endif
</div>
