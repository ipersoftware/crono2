<template>
  <div class="edettaglio">
    <div v-if="loading" class="loading-full"><div class="loading-spinner"></div></div>
    <div v-else-if="!evento" class="empty-full">Evento non trovato.</div>
    <template v-else>

      <!-- Toast posti tornati disponibili -->
      <transition name="avviso-fade">
        <div v-if="avviso" class="avviso-posti">{{ avviso }}</div>
      </transition>

      <!-- Navbar -->
      <header class="vetnav">
        <div class="vetnav-inner">
          <router-link :to="`/vetrina/${shopUrl}`" class="vetnav-brand">
            {{ evento.ente_info?.nome ?? shopUrl }}
          </router-link>
          <nav class="vetnav-links">
            <router-link :to="`/vetrina/${shopUrl}`" class="vetnav-link">← Tutti gli eventi</router-link>
            <router-link to="/login" class="vetnav-link">Accedi</router-link>
            <router-link to="/register" class="vetnav-btn">Registrati</router-link>
          </nav>
        </div>
      </header>

      <!-- Hero -->
      <section
        class="ev-hero"
        :class="{ 'ev-hero--img': evento.immagine }"
        :style="heroStyle"
      >
        <div class="ev-hero-overlay">
          <div class="container ev-hero-content">
            <div class="ev-hero-tags">
              <span
                v-for="t in evento.tags"
                :key="t.id"
                class="tag"
                :style="{ background: t.colore || '#6c63ff' }"
              >{{ t.nome }}</span>
            </div>
            <h1>{{ evento.titolo }}</h1>
            <p v-if="evento.descrizione_breve" class="ev-hero-sub">{{ evento.descrizione_breve }}</p>
          </div>
        </div>
      </section>

      <!-- Corpo -->
      <div class="container corpo">
        <div class="col-left">
          <div class="card" v-if="evento.descrizione">
            <h2 class="card-section-title">📋 Descrizione</h2>
            <div class="descrizione" v-html="evento.descrizione"></div>
          </div>
          <div class="card" v-if="evento.luoghi?.length">
            <h2 class="card-section-title">📍 Dove</h2>
            <div v-for="l in evento.luoghi" :key="l.id" class="luogo-item">
              <div class="luogo-nome">{{ l.nome }}</div>
              <div v-if="l.indirizzo" class="luogo-indirizzo">{{ l.indirizzo }}</div>
              <a v-if="l.maps_url" :href="l.maps_url" target="_blank" class="maps-link">→ Vedi su mappa</a>
            </div>
          </div>
        </div>

        <div class="col-right">
          <div class="card">
            <h2 class="card-section-title">🗓 Scegli la data</h2>
            <div v-if="!evento.sessioni?.length" class="empty-sessioni">
              Nessuna sessione aperta al momento.
            </div>
            <div v-else class="sessioni-list">
              <div v-for="s in evento.sessioni" :key="s.id" class="sessione-card">
                <div class="sessione-card-head">
                  <div class="sessione-data-wrap">
                    <span class="sessione-giorno">{{ formatGiorno(s.data_inizio) }}</span>
                    <span class="sessione-ora">🕒 {{ formatOra(s.data_inizio) }}<span v-if="s.data_fine"> → {{ formatOra(s.data_fine) }}</span></span>
                  </div>
                  <div v-if="s.visualizza_disponibili && s.posti_totali > 0" class="sessione-posti">
                    {{ Math.max(0, s.posti_disponibili - (s.posti_riservati ?? 0)) }} posti
                  </div>
                </div>
                <div v-if="s.tipologie_posto?.length" class="tipologie-list">
                  <span v-for="t in s.tipologie_posto" :key="t.id" class="tipologia-chip">
                    {{ t.tipologia_posto?.nome }}
                    <strong v-if="!t.tipologia_posto?.gratuita" class="chip-prezzo">€{{ Number(t.tipologia_posto?.costo).toFixed(2) }}</strong>
                    <strong v-else class="chip-prezzo chip-gratuito">Gratuito</strong>
                  </span>
                </div>
                <div v-if="s.luoghi?.length" class="sessione-luoghi">
                  <span v-for="l in s.luoghi" :key="l.id" class="sessione-luogo-chip">
                    📍
                    <a v-if="l.maps_url" :href="l.maps_url" target="_blank" rel="noopener" class="luogo-maps-link">{{ l.nome }}</a>
                    <span v-else>{{ l.nome }}</span>
                    <span v-if="l.indirizzo" class="luogo-indirizzo-inline">, {{ l.indirizzo }}</span>
                  </span>
                </div>
                <router-link
                  v-if="sessionePrenotabile(s)"
                  :to="`/vetrina/${shopUrl}/prenota/${evento.slug}/${s.id}`"
                  class="sessione-prenota"
                >Prenota →</router-link>
                <div v-else class="sessione-prenota-closed">{{ sessioneMessaggio(s) }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <footer class="vetfooter">
        <div class="vetfooter-inner">
          <div class="vetfooter-col">
            <div class="vetfooter-brand">{{ evento.ente_info?.nome }}</div>
            <div v-if="evento.ente_info?.indirizzo" class="vetfooter-info">{{ evento.ente_info.indirizzo }}</div>
            <div v-if="evento.ente_info?.citta" class="vetfooter-info">
              {{ evento.ente_info.citta }}{{ evento.ente_info.provincia ? ` (${evento.ente_info.provincia})` : '' }}
            </div>
            <div v-if="evento.ente_info?.email" class="vetfooter-info">Email: {{ evento.ente_info.email }}</div>
          </div>
          <div class="vetfooter-col">
            <div class="vetfooter-col-title">Link utili</div>
            <router-link :to="`/vetrina/${shopUrl}`" class="vetfooter-link">Home</router-link>
            <router-link to="/login" class="vetfooter-link">Accedi</router-link>
            <router-link to="/register" class="vetfooter-link">Registrati</router-link>
          </div>
        </div>
        <div class="vetfooter-bottom">
          © {{ new Date().getFullYear() }} {{ evento.ente_info?.nome }}. Powered by Crono.
        </div>
      </footer>

    </template>
  </div>
</template>

<script setup>
import { vetrinaApi } from '@/api/vetrina'
import { useHead } from '@unhead/vue'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute } from 'vue-router'

const route   = useRoute()
const shopUrl = route.params.shopUrl
const slug    = route.params.slug

const evento  = ref(null)
const loading = ref(true)

const carica = async () => {
  try {
    const res = await vetrinaApi.evento(shopUrl, slug)
    evento.value = res.data
  } finally { loading.value = false }
}

// ── WebSocket: posti tornati disponibili ──────────────────────────────────
const avviso      = ref('')
let   avvisoTimer = null
const channelNames = []

const sottoscriviCanali = () => {
  if (!window.Echo || !evento.value?.sessioni) return
  evento.value.sessioni.forEach(s => {
    const ch = `sessione.${s.id}`
    channelNames.push(ch)
    window.Echo.channel(ch).listen('.posti.disponibili', (data) => {
      // Aggiorna disponibilità locale
      const idx = evento.value.sessioni.findIndex(x => x.id === data.sessione_id)
      if (idx !== -1) {
        evento.value.sessioni[idx].posti_disponibili = data.posti_liberi
        evento.value.sessioni[idx].posti_riservati   = 0
      }
      clearTimeout(avvisoTimer)
      avviso.value = '🎉 Posti tornati disponibili! Prenota ora prima che si esauriscano.'
      avvisoTimer  = setTimeout(() => { avviso.value = '' }, 6000)
    })
  })
}

const heroStyle = computed(() => {
  const ev = evento.value
  if (!ev) return {}
  if (ev.immagine) return { backgroundImage: `url(${ev.immagine})` }
  if (ev.colore_primario && ev.colore_secondario)
    return { background: `linear-gradient(135deg, ${ev.colore_primario} 0%, ${ev.colore_secondario} 100%)` }
  if (ev.colore_primario)
    return { background: `linear-gradient(135deg, ${ev.colore_primario} 0%, #3a8ef6 100%)` }
  if (ev.colore_secondario)
    return { background: `linear-gradient(135deg, #4a1fa8 0%, ${ev.colore_secondario} 100%)` }
  return {}
})

const formatGiorno = (d) => {
  if (!d) return '–'
  return new Date(d).toLocaleDateString('it-IT', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' })
}

const formatOra = (d) => {
  if (!d) return ''
  return new Date(d).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' })
}

const prenotazioneAperta = computed(() => {
  const ev = evento.value
  if (!ev) return false
  const now = new Date()
  if (ev.prenotabile_dal && new Date(ev.prenotabile_dal) > now) return false
  if (ev.prenotabile_al && new Date(ev.prenotabile_al) < now) return false
  return true
})

// Controlla se una sessione specifica è prenotabile (date evento + posti disponibili)
const sessionePrenotabile = (s) => {
  if (!prenotazioneAperta.value) return false
  // 1. Posti a livello sessione (autoritativo quando definito)
  if (s.posti_totali > 0 && (s.posti_disponibili - (s.posti_riservati ?? 0)) <= 0) return false
  // 2. Posti per-tipologia: controlla solo STP che hanno un limite proprio
  if (s.tipologie_posto?.length) {
    const stpConLimite = s.tipologie_posto.filter(t => t.posti_totali > 0)
    // Se esiste almeno una tipologia illimitata (posti_totali === 0), la sessione è sempre prenotabile
    const haIllimitate = s.tipologie_posto.some(t => t.posti_totali === 0)
    if (stpConLimite.length > 0 && !haIllimitate) {
      const haPostiLiberi = stpConLimite.some(t =>
        (t.posti_disponibili - (t.posti_riservati ?? 0)) > 0
      )
      if (!haPostiLiberi) return false
    }
  }
  return true
}

const sessioneMessaggio = (s) => {
  if (!prenotazioneAperta.value) return prenotabileMessage.value
  return 'Posti esauriti'
}

const prenotabileMessage = computed(() => {
  const ev = evento.value
  if (!ev) return ''
  const now = new Date()
  if (ev.prenotabile_dal && new Date(ev.prenotabile_dal) > now)
    return `Prenotazioni aperte dal ${new Date(ev.prenotabile_dal).toLocaleString('it-IT', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })}`
  if (ev.prenotabile_al && new Date(ev.prenotabile_al) < now)
    return 'Prenotazioni chiuse'
  return ''
})

// ── SEO: meta tag dinamici + schema.org ─────────────────────────────────────
const seoTitle = computed(() => {
  const ev = evento.value
  if (!ev) return 'Crono'
  return `${ev.titolo}${ev.ente_info?.nome ? ' — ' + ev.ente_info.nome : ''}`
})

const seoDesc = computed(() => evento.value?.descrizione_breve || evento.value?.titolo || '')
const seoImage = computed(() => evento.value?.immagine || '')
const seoUrl = computed(() => location.href)

const seoJsonLd = computed(() => {
  const ev = evento.value
  if (!ev) return null
  return JSON.stringify({
    '@context':            'https://schema.org',
    '@type':               ev.sessioni?.length > 1 ? 'EventSeries' : 'Event',
    name:                  ev.titolo,
    description:           seoDesc.value,
    ...(seoImage.value ? { image: seoImage.value } : {}),
    url:                   seoUrl.value,
    organizer: ev.ente_info ? {
      '@type': 'Organization',
      name:    ev.ente_info.nome,
    } : undefined,
    ...(ev.luoghi?.length ? {
      location: {
        '@type':  'Place',
        name:     ev.luoghi[0].nome,
        address:  ev.luoghi[0].indirizzo ?? ev.luoghi[0].nome,
      }
    } : {}),
    ...(ev.sessioni?.length ? {
      startDate: ev.sessioni[0].data_inizio,
      endDate:   ev.sessioni[ev.sessioni.length - 1].data_fine ?? ev.sessioni[ev.sessioni.length - 1].data_inizio,
    } : {}),
    eventStatus:         'https://schema.org/EventScheduled',
    eventAttendanceMode: 'https://schema.org/OfflineEventAttendanceMode',
    offers: {
      '@type':       'Offer',
      url:           seoUrl.value,
      availability:  'https://schema.org/InStock',
    },
  })
})

// useHead va chiamato una volta sola in setup(), con valori computed reattivi
useHead({
  title: seoTitle,
  meta: computed(() => [
    { name:     'description',         content: seoDesc.value },
    { property: 'og:type',             content: 'event' },
    { property: 'og:title',            content: evento.value?.titolo ?? '' },
    { property: 'og:description',      content: seoDesc.value },
    ...(seoImage.value ? [{ property: 'og:image', content: seoImage.value }] : []),
    { property: 'og:url',              content: seoUrl.value },
    { property: 'og:site_name',        content: evento.value?.ente_info?.nome ?? 'Crono' },
    { name:     'twitter:card',        content: 'summary_large_image' },
    { name:     'twitter:title',       content: evento.value?.titolo ?? '' },
    { name:     'twitter:description', content: seoDesc.value },
    ...(seoImage.value ? [{ name: 'twitter:image', content: seoImage.value }] : []),
  ]),
  link:   computed(() => [{ rel: 'canonical', href: seoUrl.value }]),
  script: computed(() => seoJsonLd.value
    ? [{ type: 'application/ld+json', innerHTML: seoJsonLd.value }]
    : []
  ),
})

// Ricarica i dati se l'utente torna sulla tab (copre i casi in cui il WebSocket drop ha fatto perdere l'evento)
const handleVisibility = () => { if (!document.hidden) carica() }

onMounted(async () => {
  await carica()
  sottoscriviCanali()
  document.addEventListener('visibilitychange', handleVisibility)
})

onUnmounted(() => {
  clearTimeout(avvisoTimer)
  if (window.Echo) channelNames.forEach(ch => window.Echo.leave(ch))
  document.removeEventListener('visibilitychange', handleVisibility)
})
</script>

<style scoped>
/* ── Base ── */
.edettaglio { min-height: 100vh; background: #f4f5f7; }
.loading-full { display: flex; justify-content: center; align-items: center; height: 60vh; }
.empty-full { padding: 4rem; text-align: center; color: #aaa; }
.loading-spinner { width: 36px; height: 36px; border: 3px solid #e0e0e0; border-top-color: #6c63ff; border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Navbar ── */
.vetnav { background: white; box-shadow: 0 2px 12px rgba(0,0,0,.08); position: sticky; top: 0; z-index: 100; }
.vetnav-inner { max-width: 1100px; margin: 0 auto; padding: .9rem 1.5rem; display: flex; align-items: center; justify-content: space-between; }
.vetnav-brand { font-size: 1.1rem; font-weight: 800; color: #1a1a2e; text-decoration: none; }
.vetnav-links { display: flex; align-items: center; gap: 1.75rem; }
.vetnav-link { color: #555; text-decoration: none; font-size: .9rem; font-weight: 500; transition: color .15s; }
.vetnav-link:hover { color: #6c63ff; }
.vetnav-btn { background: #6c63ff; color: white !important; padding: .42rem 1.1rem; border-radius: 20px; text-decoration: none; font-size: .88rem; font-weight: 700; transition: background .15s; }
.vetnav-btn:hover { background: #574fd6; }

/* ── Hero ── */
.ev-hero { min-height: 280px; background: linear-gradient(135deg,#4a1fa8 0%,#6c63ff 55%,#3a8ef6 100%); background-size: cover; background-position: center; position: relative; }
.ev-hero--img .ev-hero-overlay { background: rgba(0,0,0,.50); }
.ev-hero-overlay { position: absolute; inset: 0; display: flex; align-items: center; background: rgba(40,20,90,.40); }
.ev-hero-content { color: white; padding: 2.5rem 1.5rem; max-width: 760px; }
.ev-hero-tags { display: flex; flex-wrap: wrap; gap: .35rem; margin-bottom: .9rem; }
.tag { padding: .2rem .6rem; border-radius: 9px; color: white; font-size: .72rem; font-weight: 600; }
.ev-hero-content h1 { font-size: 2.2rem; font-weight: 900; margin: 0 0 .6rem; letter-spacing: -.025em; text-shadow: 0 2px 10px rgba(0,0,0,.3); }
.ev-hero-sub { font-size: 1rem; opacity: .9; margin: 0; line-height: 1.5; }

/* ── Container / corpo ── */
.container { max-width: 1100px; margin: 0 auto; padding: 0 1.5rem; }
.corpo { display: grid; grid-template-columns: 1fr 400px; gap: 1.75rem; padding: 2.5rem 1.5rem 3.5rem; align-items: start; }
.col-left, .col-right { display: flex; flex-direction: column; gap: 1.25rem; }

/* ── Card generica ── */
.card { background: white; border-radius: 16px; padding: 1.5rem; box-shadow: 0 2px 14px rgba(0,0,0,.06); }
.card-section-title { font-size: 1.05rem; font-weight: 700; color: #1a1a2e; margin: 0 0 1.1rem; padding-bottom: .65rem; border-bottom: 2px solid #eceef2; }
.descrizione { line-height: 1.75; color: #444; font-size: .95rem; }

/* ── Luoghi ── */
.luogo-item { padding: .6rem 0; border-bottom: 1px solid #f0f0f5; }
.luogo-item:last-child { border-bottom: none; }
.luogo-nome { font-weight: 700; color: #1a1a2e; margin-bottom: .15rem; }
.luogo-indirizzo { font-size: .85rem; color: #888; }
.maps-link { font-size: .82rem; color: #6c63ff; text-decoration: none; font-weight: 600; display: inline-block; margin-top: .25rem; }
.maps-link:hover { text-decoration: underline; }

/* ── Sessioni ── */
.empty-sessioni { color: #aaa; font-size: .92rem; text-align: center; padding: 1.5rem 0; }
.sessioni-list { display: flex; flex-direction: column; gap: .9rem; }
.sessione-card { border: 1.5px solid #eceef2; border-radius: 12px; padding: 1rem 1.1rem; transition: border-color .15s, box-shadow .15s; }
.sessione-card:hover { border-color: #6c63ff; box-shadow: 0 4px 16px rgba(108,99,255,.12); }
.sessione-card-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: .7rem; gap: .5rem; }
.sessione-data-wrap { display: flex; flex-direction: column; gap: .15rem; }
.sessione-giorno { font-weight: 700; color: #1a1a2e; font-size: .9rem; text-transform: capitalize; }
.sessione-ora { font-size: .82rem; color: #888; }
.sessione-posti { background: #e8f9f0; color: #00a86b; font-size: .78rem; font-weight: 700; padding: .22rem .6rem; border-radius: 8px; white-space: nowrap; }
.tipologie-list { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: .85rem; }
.tipologia-chip { background: #f4f5f9; border-radius: 8px; padding: .22rem .7rem; font-size: .8rem; color: #444; }
.chip-prezzo { color: #6c63ff; margin-left: .3rem; font-size: .78rem; }
.chip-gratuito { color: #00a86b; }
.sessione-luoghi { display: flex; flex-wrap: wrap; gap: .35rem; margin-bottom: .75rem; }
.sessione-luogo-chip { font-size: .8rem; color: #6b5c20; background: #fef9e7; border: 1px solid #f9e79f; border-radius: 8px; padding: .2rem .6rem; }
.luogo-maps-link { color: #2980b9; text-decoration: underline; font-weight: 600; }
.luogo-maps-link:hover { color: #1a5276; }
.luogo-indirizzo-inline { color: #999; font-size: .75rem; }
.sessione-prenota { display: block; text-align: center; background: #00c97a; color: white; font-weight: 700; padding: .65rem; border-radius: 10px; text-decoration: none; font-size: .92rem; transition: background .15s, transform .1s; }
.sessione-prenota:hover { background: #00ae69; transform: translateY(-1px); }
.sessione-prenota-closed { display: block; text-align: center; background: #f0f0f5; color: #999; font-weight: 600; padding: .65rem; border-radius: 10px; font-size: .88rem; }

/* ── Footer ── */
.vetfooter { background: #1a1a2e; color: rgba(255,255,255,.75); padding: 3rem 1.5rem 0; }
.vetfooter-inner { max-width: 1100px; margin: 0 auto; display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 2.5rem; padding-bottom: 2.5rem; }
.vetfooter-brand { font-size: 1.05rem; font-weight: 800; color: white; margin-bottom: .65rem; }
.vetfooter-info { font-size: .84rem; line-height: 1.9; color: rgba(255,255,255,.55); }
.vetfooter-col-title { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: rgba(255,255,255,.45); margin-bottom: .85rem; }
.vetfooter-link { display: block; color: rgba(255,255,255,.65); text-decoration: none; font-size: .88rem; line-height: 2.1; transition: color .15s; }
.vetfooter-link:hover { color: white; }
.vetfooter-bottom { max-width: 1100px; margin: 0 auto; border-top: 1px solid rgba(255,255,255,.09); padding: 1.3rem 0; text-align: center; font-size: .78rem; color: rgba(255,255,255,.35); }

/* ── Responsive ── */
@media (max-width: 900px) {
  .corpo { grid-template-columns: 1fr; }
  .vetfooter-inner { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 768px) {
  .ev-hero-content h1 { font-size: 1.65rem; }
  .corpo { padding: 1.75rem 1rem 2.5rem; }
  .vetnav-links { gap: 1rem; }
  .vetfooter-inner { grid-template-columns: 1fr; gap: 1.5rem; }
}
@media (max-width: 480px) {
  .ev-hero { min-height: 220px; }
  .ev-hero-content h1 { font-size: 1.35rem; }
  .vetnav-btn { display: none; }
}
.avviso-posti { position: fixed; top: 1.2rem; left: 50%; transform: translateX(-50%); background: #27ae60; color: #fff; padding: .7rem 1.4rem; border-radius: 8px; font-weight: 600; z-index: 9999; box-shadow: 0 2px 12px rgba(0,0,0,.2); white-space: nowrap; }
.avviso-fade-enter-active, .avviso-fade-leave-active { transition: opacity .4s; }
.avviso-fade-enter-from, .avviso-fade-leave-to { opacity: 0; }
</style>
