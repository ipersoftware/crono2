<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

    {{-- Home vetrina di ogni ente attivo --}}
    @foreach($enti as $ente)
    <url>
        <loc>{{ url('/vetrina/' . $ente->shop_url) }}</loc>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
        @if($ente->updated_at)
        <lastmod>{{ $ente->updated_at->toAtomString() }}</lastmod>
        @endif
    </url>
    @endforeach

    {{-- Pagina di ogni evento pubblico --}}
    @foreach($eventi as $ev)
    @if($ev->ente)
    <url>
        <loc>{{ url('/vetrina/' . $ev->ente->shop_url . '/eventi/' . $ev->slug) }}</loc>
        <changefreq>weekly</changefreq>
        <priority>1.0</priority>
        @if($ev->updated_at)
        <lastmod>{{ $ev->updated_at->toAtomString() }}</lastmod>
        @endif
    </url>
    @endif
    @endforeach

</urlset>
