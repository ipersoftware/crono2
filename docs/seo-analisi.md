# Analisi SEO — Pagina Evento (Vetrina pubblica)

## Stato attuale

### Architettura
Crono2 è una **SPA Vue 3** servita da una singola Blade view (`resources/views/app.blade.php`) tramite una catch-all Laravel:

```php
Route::get('/{any}', fn() => view('app'))->where('any', '.*');
```

Il template Blade emette sempre e solo:
```html
<title>Crono</title>
<div id="app"></div>
```

Tutto il rendering avviene nel browser (CSR — Client Side Rendering). **I crawler che non eseguono JavaScript (Googlebot esegue JS, ma con ritardo e limitazioni) vedono una pagina vuota.**

### Route pubblica evento
```
/vetrina/{shopUrl}/eventi/{slug}
```
Esempi reali:
```
/vetrina/comune-esempio/eventi/concerto-di-primavera-2026
```

### Campi nel modello Evento già disponibili e utili per SEO
| Campo | Note |
|---|---|
| `titolo` | ✅ disponibile — ideale per `<title>` e `og:title` |
| `descrizione_breve` | ✅ disponibile — ideale per `<meta description>` e `og:description` |
| `descrizione` | ✅ HTML rich — utile per schema.org |
| `immagine` | ✅ URL immagine — ideale per `og:image` e `twitter:image` |
| `slug` | ✅ URL-friendly già generato |
| `colore_primario` | secondario |
| `tags` | ✅ array — utili come keyword |
| `luoghi` | ✅ nome + indirizzo — utili per schema.org `location` |
| sessioni: `data_inizio`, `data_fine` | ✅ utili per schema.org `startDate/endDate` |
| `ente_info.nome` | ✅ utile per `og:site_name` e schema.org `organizer` |

---

## Problemi SEO attivi

### 1. `<title>` sempre statico ("Crono")
**Impatto: critico.**  
Tutti gli eventi condividono lo stesso title. Google non differenzia le pagine. Nei risultati di ricerca appare "Crono" invece del nome dell'evento.

### 2. Assenza di meta description
**Impatto: alto.**  
Google genera uno snippet autonomamente dal testo della pagina, ma spesso lo fa male. La `descrizione_breve` è disponibile nel dato ma mai iniettata nell'`<head>`.

### 3. Assenza di Open Graph e Twitter Card
**Impatto: alto per condivisione social.**  
Quando un link `/vetrina/.../eventi/...` viene condiviso su Facebook, WhatsApp, LinkedIn, Twitter, non mostra né titolo né immagine — appare solo l'URL grezzo.

### 4. Assenza di dati strutturati (schema.org `Event`)
**Impatto: alto.**  
Google mostra "rich results" per gli eventi (box con data, luogo, disponibilità) nei risultati di ricerca se la pagina espone `application/ld+json` con schema `Event`. Questo aumenta visibilità e CTR in modo significativo, ed è l'uso più naturale per una pagina evento.

### 5. `robots.txt` concede accesso indiscriminato a tutto
**Impatto: medio.**  
Il file attuale è:
```
User-agent: *
Disallow:
```
Le route admin (`/admin/*`), le route di booking (`/vetrina/*/prenota/*`) e le API (`/api/*`) sono indicizzabili. Non è comportamento desiderato.

### 6. Assenza di sitemap
**Impatto: medio.**  
Google non sa dove sono le pagine degli eventi. L'unico modo per scoprirle è seguire i link interni dalla home vetrina. Una sitemap XML velocizza l'indicizzazione degli eventi nuovi.

### 7. Struttura URL — nessun problema
Gli URL degli eventi sono già parlanti e SEO-friendly:
```
https://www.ente.it/vetrina/agispiemonte/eventi/concerto-di-primavera-2026
```
Contengono ente e slug — nessun intervento necessario su questo fronte.

### 8. Nessun tag canonical
**Impatto: basso.**  
Se lo stesso evento fosse raggiungibile con più URL (es. con/senza trailing slash, caso maiuscolo/minuscolo), Google potrebbe creare duplicati. Un tag `<link rel="canonical">` previene il problema.

---

## Soluzioni raccomandate

### Priorità 1 — Meta tag dinamici lato client (implementazione rapida)

Installare `@unhead/vue` (libreria ufficiale Unhead, raccomandata dal team Vue Router):

```bash
npm install @unhead/vue
```

Configurare in `resources/js/app.js`:
```js
import { createHead } from '@unhead/vue'
const head = createHead()
app.use(head)
```

Poi in `EventoDettaglio.vue`, dopo che `evento.value` è caricato:
```js
import { useHead } from '@unhead/vue'

// Dentro il setup, dopo carica():
watchEffect(() => {
  if (!evento.value) return
  const ev = evento.value
  const ogImage = ev.immagine ?? `${location.origin}/og-default.jpg`
  const url     = location.href

  useHead({
    title:       `${ev.titolo} — ${ev.ente_info?.nome ?? ''}`,
    meta: [
      { name:     'description',       content: ev.descrizione_breve ?? ev.titolo },
      { property: 'og:type',           content: 'event' },
      { property: 'og:title',          content: ev.titolo },
      { property: 'og:description',    content: ev.descrizione_breve ?? '' },
      { property: 'og:image',          content: ogImage },
      { property: 'og:url',            content: url },
      { property: 'og:site_name',      content: ev.ente_info?.nome ?? 'Crono' },
      { name:     'twitter:card',      content: 'summary_large_image' },
      { name:     'twitter:title',     content: ev.titolo },
      { name:     'twitter:image',     content: ogImage },
    ],
    link: [
      { rel: 'canonical', href: url },
    ],
  })
})
```

**Limitazione**: i meta tag vengono iniettati dopo l'esecuzione JS. Funziona per Googlebot (che esegue JS) ma non per i crawler social (Facebook, WhatsApp, LinkedIn) che **non eseguono JavaScript** — vedono sempre la Blade statica senza meta.

---

### Priorità 2 — SSR parziale per le route vetrina pubblica (soluzione definitiva)

Per risolvere completamente il problema dei crawler social, la soluzione più pulita senza riscrivere l'intera app è un **controller Laravel che genera i meta tag OG server-side solo per le route vetrina**, lasciando il resto della SPA invariato.

#### Approccio: Blade dedicata per la vetrina con meta pre-iniettati

**Aggiungere una route specifica in `routes/web.php` PRIMA della catch-all:**

```php
// SEO: pagina evento con meta tag server-side per social crawler
Route::get('/vetrina/{shopUrl}/eventi/{slug}', [VetrinaMetaController::class, 'evento']);
Route::get('/vetrina/{shopUrl}',               [VetrinaMetaController::class, 'home']);
```

**Creare `app/Http/Controllers/VetrinaMetaController.php`:**

```php
<?php
namespace App\Http\Controllers;

use App\Models\Ente;
use App\Models\Evento;
use Illuminate\Http\Request;

class VetrinaMetaController extends Controller
{
    public function evento(string $shopUrl, string $slug)
    {
        $ente   = Ente::where('shop_url', $shopUrl)->firstOrFail();
        $evento = Evento::where('ente_id', $ente->id)
                        ->where('slug', $slug)
                        ->where('pubblico', true)
                        ->firstOrFail();

        $meta = [
            'title'       => "{$evento->titolo} — {$ente->nome}",
            'description' => $evento->descrizione_breve ?? $evento->titolo,
            'image'       => $evento->immagine ?? asset('og-default.jpg'),
            'url'         => url("/vetrina/{$shopUrl}/eventi/{$slug}"),
            'type'        => 'event',
        ];

        return view('app', compact('meta'));
    }

    public function home(string $shopUrl)
    {
        $ente = Ente::where('shop_url', $shopUrl)->firstOrFail();

        $meta = [
            'title'       => "Eventi — {$ente->nome}",
            'description' => "Scopri gli eventi di {$ente->nome} e prenota il tuo posto.",
            'image'       => asset('og-default.jpg'),
            'url'         => url("/vetrina/{$shopUrl}"),
            'type'        => 'website',
        ];

        return view('app', compact('meta'));
    }
}
```

**Modificare `resources/views/app.blade.php`:**

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $meta['title'] ?? config('app.name', 'Crono') }}</title>

    @isset($meta)
    <meta name="description"       content="{{ $meta['description'] }}">
    <meta property="og:type"       content="{{ $meta['type'] ?? 'website' }}">
    <meta property="og:title"      content="{{ $meta['title'] }}">
    <meta property="og:description"content="{{ $meta['description'] }}">
    <meta property="og:image"      content="{{ $meta['image'] }}">
    <meta property="og:url"        content="{{ $meta['url'] }}">
    <meta name="twitter:card"      content="summary_large_image">
    <meta name="twitter:title"     content="{{ $meta['title'] }}">
    <meta name="twitter:image"     content="{{ $meta['image'] }}">
    <link rel="canonical"          href="{{ $meta['url'] }}">
    @endisset

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
```

Questo approccio è:
- **Trasparente per la SPA**: il Vue Router continua a gestire la navigazione client-side normalmente
- **Efficace per i social crawler**: leggono i meta già nell'HTML iniziale
- **Efficace per Googlebot**: riceve sia i meta statici sia il contenuto renderizzato da Vue
- **Zero refactoring**: non tocca nulla del frontend esistente

---

### Priorità 3 — Schema.org `Event` (JSON-LD)

Aggiungere nel `<head>` (via controller o via `useHead`) i dati strutturati per Google:

```json
{
  "@context": "https://schema.org",
  "@type": "Event",
  "name": "Concerto di Primavera 2026",
  "description": "Descrizione breve dell'evento",
  "image": "https://...",
  "url": "https://www.ente.it/vetrina/.../eventi/...",
  "organizer": {
    "@type": "Organization",
    "name": "Comune di Esempio"
  },
  "location": {
    "@type": "Place",
    "name": "Auditorium Comunale",
    "address": "Via Roma 1, Torino"
  },
  "startDate": "2026-04-10T10:00:00+02:00",
  "endDate":   "2026-04-10T12:00:00+02:00",
  "eventStatus": "https://schema.org/EventScheduled",
  "eventAttendanceMode": "https://schema.org/OfflineEventAttendanceMode",
  "offers": {
    "@type": "Offer",
    "url": "https://www.ente.it/vetrina/.../prenota/...",
    "availability": "https://schema.org/InStock"
  }
}
```

Per eventi con più sessioni, si può usare un array di oggetti `Event` (uno per sessione), oppure il tipo `EventSeries`.

---

### Priorità 4 — Sitemap XML dinamica

Aggiungere una route Laravel che genera la sitemap degli eventi pubblici:

```php
Route::get('/sitemap.xml', [SitemapController::class, 'index']);
```

```php
public function index()
{
    $eventi = Evento::where('pubblico', true)
        ->with('ente')
        ->get();

    $xml = view('sitemap', compact('eventi'))->render();
    return response($xml, 200)->header('Content-Type', 'application/xml');
}
```

La sitemap dovrebbe includere:
- Home vetrina: `/vetrina/{shopUrl}` (priority: 0.8)
- Ogni evento: `/vetrina/{shopUrl}/eventi/{slug}` (priority: 1.0, changefreq: weekly)

---

### Priorità 5 — `robots.txt` corretto

Il file `public/robots.txt` attuale è permissivo. Versione raccomandata:

```
User-agent: *
Disallow: /admin/
Disallow: /api/
Disallow: /vetrina/*/prenota/
Disallow: /vetrina/*/conferma/
Disallow: /auth/

Allow: /vetrina/

Sitemap: https://www.tuo-dominio.it/sitemap.xml
```

---

## Riepilogo interventi e priorità

| # | Intervento | Beneficio ottenuto | Sforzo impl. | Risultato |
|---|---|---|---|---|
| 1 | Meta tag dinamici via `@unhead/vue` in `EventoDettaglio.vue` | Alto | **Basso** — aggiunta tag + lib | `<title>` e description corretti su Google |
| 2 | `VetrinaMetaController` + Blade con `$meta` | Alto | Medio — nuovo controller + blade | Anteprima social (immagine+titolo) funzionante |
| 3 | Schema.org `Event` JSON-LD | Alto | **Basso** — aggiunta script JSON nel `<head>` | Rich results su Google (box evento con data/luogo) |
| 4 | Sitemap XML dinamica | Medio | Medio — controller + view XML | Indicizzazione più rapida degli eventi nuovi |
| 5 | `robots.txt` aggiornato | Medio | **Basso** — modifica file statico | Blocco pagine admin/api dall'indicizzazione |

---

## Note sull'architettura SSR completa

Se in futuro il SEO diventasse un requisito primario (es. Crono2 usato come piattaforma pubblica con obiettivi di posizionamento organico), la soluzione definitiva sarebbe migrare la vetrina pubblica a **Nuxt 3** o **Inertia.js** (SSR nativo). Tuttavia, per il caso d'uso attuale (enti pubblici e comunità locali che usano la vetrina come pagina di atterraggio per eventi già noti), le soluzioni ai punti 1-5 sono sufficienti e molto meno costose.
