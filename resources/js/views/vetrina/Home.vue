<template>
  <div class="vetrina-page">
    <div v-if="loading" class="loading-full"><div class="loading-spinner"></div></div>
    <div v-else-if="!ente" class="empty-full">Vetrina non trovata.</div>
    <template v-else>

      <!-- Navbar -->
      <header class="vetnav">
        <div class="vetnav-inner">
          <router-link :to="`/vetrina/${shopUrl}`" class="vetnav-brand">{{ ente.nome }}</router-link>
          <nav class="vetnav-links">
            <router-link :to="`/vetrina/${shopUrl}`" class="vetnav-link">Home</router-link>
            <router-link to="/login" class="vetnav-link">Accedi</router-link>
            <router-link to="/register" class="vetnav-btn">Registrati</router-link>
          </nav>
        </div>
      </header>

      <!-- Hero -->
      <section
        class="hero"
        :class="{ 'hero--img': ente.copertina }"
        :style="ente.copertina ? { backgroundImage: `url(${ente.copertina})` } : {}"
      >
        <div class="hero-overlay">
          <div class="hero-content">
            <h1>{{ ente.nome }}</h1>
            <p v-if="heroSubtitle" class="hero-sub">{{ heroSubtitle }}</p>
            <a href="#eventi" class="hero-cta">→ Scopri gli eventi</a>
          </div>
        </div>
      </section>

      <!-- Contenuto principale -->
      <div class="container" id="eventi">

        <!-- Ricerca + filtri -->
        <div class="filtri-wrap">
          <div class="filtri-inner">
            <div class="search-box">
              <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
              </svg>
              <input v-model="filtri.q" @input="caricaEventi" placeholder="Cerca evento…" class="search-input" />
            </div>
            <select v-model="filtri.tag_id" @change="caricaEventi" class="tag-select">
              <option value="">Tutti i tag</option>
              <option v-for="tag in tags" :key="tag.id" :value="tag.id">{{ tag.nome }}</option>
            </select>
          </div>
        </div>

        <!-- In evidenza -->
        <section v-if="inEvidenza.length" class="sezione">
          <div class="sezione-header">
            <h2 class="sezione-title">⭐ In evidenza</h2>
          </div>
          <div class="eventi-grid">
            <router-link
              v-for="ev in inEvidenza"
              :key="ev.id"
              :to="`/vetrina/${shopUrl}/eventi/${ev.slug}`"
              class="ev-card"
            >
              <div class="ev-card-img" :style="evImgStyle(ev)">
                <div class="ev-card-badge">In evidenza</div>
                <div class="ev-card-tags">
                  <span v-for="t in ev.tags" :key="t.id" class="tag" :style="{ background: t.colore || '#6c63ff' }">{{ t.nome }}</span>
                </div>
              </div>
              <div class="ev-card-body">
                <h3 class="ev-card-title">{{ ev.titolo }}</h3>
                <p v-if="ev.descrizione_breve" class="ev-card-desc">{{ ev.descrizione_breve }}</p>
                <div class="ev-card-foot">
                  <span v-if="ev.sessioni?.length" class="ev-card-meta">{{ formatDataBreve(ev.sessioni[0].data_inizio) }}</span>
                  <span class="ev-card-cta">Prenota →</span>
                </div>
              </div>
            </router-link>
          </div>
        </section>

        <!-- Tutti gli eventi -->
        <section class="sezione">
          <div class="sezione-header">
            <h2 class="sezione-title">Tutti gli eventi</h2>
            <span v-if="meta?.total" class="sezione-count">{{ meta.total }} eventi</span>
          </div>
          <div v-if="loadingEventi" class="loading-eventi"><div class="loading-spinner"></div></div>
          <div v-else-if="eventi.length === 0" class="empty-eventi">
            <div class="empty-icon">🗓</div>
            <p>Nessun evento disponibile al momento.</p>
          </div>
          <div v-else class="eventi-grid">
            <router-link
              v-for="ev in eventi"
              :key="ev.id"
              :to="`/vetrina/${shopUrl}/eventi/${ev.slug}`"
              class="ev-card"
            >
              <div class="ev-card-img" :style="evImgStyle(ev)">
                <div class="ev-card-tags">
                  <span v-for="t in ev.tags" :key="t.id" class="tag" :style="{ background: t.colore || '#6c63ff' }">{{ t.nome }}</span>
                </div>
                <div v-if="evPrezzo(ev)" class="ev-card-prezzo">{{ evPrezzo(ev) }}</div>
              </div>
              <div class="ev-card-body">
                <h3 class="ev-card-title">{{ ev.titolo }}</h3>
                <p v-if="ev.descrizione_breve" class="ev-card-desc">{{ ev.descrizione_breve }}</p>
                <div class="ev-card-foot">
                  <span class="ev-card-meta">{{ ev.sessioni_count }} sessioni</span>
                  <span class="ev-card-cta">Prenota →</span>
                </div>
              </div>
            </router-link>
          </div>
          <div v-if="meta?.last_page > 1" class="paginazione">
            <button
              v-for="n in meta.last_page"
              :key="n"
              :class="['pag-btn', n === meta.current_page ? 'pag-btn--active' : '']"
              @click="pagina = n; caricaEventi()"
            >{{ n }}</button>
          </div>
        </section>
      </div>

      <!-- Footer -->
      <footer class="vetfooter">
        <div class="vetfooter-inner">
          <div class="vetfooter-col">
            <div class="vetfooter-brand">{{ ente.nome }}</div>
            <div v-if="ente.indirizzo" class="vetfooter-info">{{ ente.indirizzo }}</div>
            <div v-if="ente.citta" class="vetfooter-info">{{ ente.citta }}{{ ente.provincia ? ` (${ente.provincia})` : '' }}</div>
            <div v-if="ente.email" class="vetfooter-info">Email: {{ ente.email }}</div>
          </div>
          <div class="vetfooter-col">
            <div class="vetfooter-col-title">Link utili</div>
            <router-link :to="`/vetrina/${shopUrl}`" class="vetfooter-link">Home</router-link>
            <router-link to="/login" class="vetfooter-link">Accedi</router-link>
            <router-link to="/register" class="vetfooter-link">Registrati</router-link>
          </div>
          <div v-if="ente.privacy_url" class="vetfooter-col">
            <div class="vetfooter-col-title">Informazioni</div>
            <a :href="ente.privacy_url" target="_blank" rel="noopener" class="vetfooter-link">Privacy Policy</a>
          </div>
        </div>
        <div class="vetfooter-bottom">
          © {{ new Date().getFullYear() }} {{ ente.nome }}. Powered by Crono.
        </div>
      </footer>

    </template>
  </div>
</template>

<script setup>
import { vetrinaApi } from '@/api/vetrina'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'

const route   = useRoute()
const shopUrl = route.params.shopUrl

const ente        = ref(null)
const inEvidenza  = ref([])
const eventi      = ref([])
const tags        = ref([])
const meta        = ref(null)
const loading     = ref(false)
const loadingEventi = ref(false)
const pagina      = ref(1)
const filtri      = reactive({ q: '', tag_id: '' })

// Subtitle hero: testo plain estratto dal contenuto_vetrina, max 140 car.
const heroSubtitle = computed(() => {
  if (!ente.value?.contenuto_vetrina) return ''
  const plain = ente.value.contenuto_vetrina.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
  return plain.length > 140 ? plain.slice(0, 140) + '…' : plain
})

// Gradiente deterministico per eventi senza immagine
const gradients = [
  'linear-gradient(135deg,#667eea 0%,#764ba2 100%)',
  'linear-gradient(135deg,#f093fb 0%,#f5576c 100%)',
  'linear-gradient(135deg,#4facfe 0%,#00f2fe 100%)',
  'linear-gradient(135deg,#43e97b 0%,#38f9d7 100%)',
  'linear-gradient(135deg,#fa709a 0%,#fee140 100%)',
  'linear-gradient(135deg,#a18cd1 0%,#fbc2eb 100%)',
]

const evImgStyle = (ev) => {
  if (ev.immagine) return { backgroundImage: `url(${ev.immagine})`, backgroundSize: 'cover', backgroundPosition: 'center' }
  return { background: gradients[ev.id % gradients.length] }
}

const evPrezzo = (ev) => {
  if (ev.costo == null || Number(ev.costo) === 0) return 'Gratuito'
  return `€ ${Number(ev.costo).toFixed(2)}`
}

const formatDataBreve = (d) => {
  if (!d) return ''
  return new Date(d).toLocaleDateString('it-IT', { day: '2-digit', month: 'short', year: 'numeric' })
}

const carica = async () => {
  loading.value = true
  try {
    const [homeRes, tagsRes] = await Promise.all([
      vetrinaApi.index(shopUrl),
      vetrinaApi.tags(shopUrl),
    ])
    ente.value       = homeRes.data.ente
    inEvidenza.value = homeRes.data.eventi_in_evidenza ?? []
    tags.value       = tagsRes.data
    await caricaEventi()
  } finally { loading.value = false }
}

const caricaEventi = async () => {
  loadingEventi.value = true
  try {
    const res = await vetrinaApi.eventi(shopUrl, { ...filtri, page: pagina.value })
    eventi.value = res.data.data
    meta.value   = res.data
  } finally { loadingEventi.value = false }
}

onMounted(carica)
</script>

<style scoped>
/* ── Reset & base ── */
.vetrina-page { min-height: 100vh; background: #f4f5f7; }
.loading-full { display: flex; justify-content: center; align-items: center; height: 60vh; }
.empty-full { padding: 4rem; text-align: center; color: #aaa; font-size: 1.1rem; }

/* ── Spinner ── */
.loading-spinner { width: 36px; height: 36px; border: 3px solid #e0e0e0; border-top-color: #6c63ff; border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Navbar ── */
.vetnav { background: white; box-shadow: 0 2px 12px rgba(0,0,0,.08); position: sticky; top: 0; z-index: 100; }
.vetnav-inner { max-width: 1100px; margin: 0 auto; padding: .9rem 1.5rem; display: flex; align-items: center; justify-content: space-between; }
.vetnav-brand { font-size: 1.1rem; font-weight: 800; color: #1a1a2e; text-decoration: none; letter-spacing: -.01em; }
.vetnav-links { display: flex; align-items: center; gap: 1.75rem; }
.vetnav-link { color: #555; text-decoration: none; font-size: .9rem; font-weight: 500; transition: color .15s; }
.vetnav-link:hover, .vetnav-link.router-link-active { color: #6c63ff; }
.vetnav-btn { background: #6c63ff; color: white !important; padding: .42rem 1.1rem; border-radius: 20px; text-decoration: none; font-size: .88rem; font-weight: 700; transition: background .15s; }
.vetnav-btn:hover { background: #574fd6; }

/* ── Hero ── */
.hero { min-height: 320px; background: linear-gradient(135deg,#4a1fa8 0%,#6c63ff 55%,#3a8ef6 100%); background-size: cover; background-position: center; position: relative; }
.hero--img .hero-overlay { background: rgba(0,0,0,.45); }
.hero-overlay { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(40,20,90,.38); }
.hero-content { text-align: center; color: white; padding: 2rem 1.5rem; max-width: 640px; }
.hero-content h1 { font-size: 2.6rem; font-weight: 900; margin-bottom: .55rem; letter-spacing: -.025em; text-shadow: 0 2px 10px rgba(0,0,0,.3); }
.hero-sub { font-size: 1.05rem; opacity: .9; margin-bottom: 1.75rem; line-height: 1.5; }
.hero-cta { display: inline-block; background: #00c97a; color: white; padding: .75rem 2rem; border-radius: 30px; text-decoration: none; font-weight: 700; font-size: 1rem; transition: background .15s, transform .1s; box-shadow: 0 4px 15px rgba(0,201,122,.4); }
.hero-cta:hover { background: #00ae69; transform: translateY(-2px); }

/* ── Container ── */
.container { max-width: 1100px; margin: 0 auto; padding: 2.5rem 1.5rem 3.5rem; }

/* ── Filtri ── */
.filtri-wrap { margin-bottom: 2.25rem; }
.filtri-inner { display: flex; gap: .75rem; flex-wrap: wrap; }
.search-box { position: relative; flex: 1; min-width: 220px; }
.search-icon { position: absolute; left: .85rem; top: 50%; transform: translateY(-50%); width: 15px; height: 15px; color: #aaa; pointer-events: none; }
.search-input { width: 100%; padding: .6rem .75rem .6rem 2.4rem; border: 1.5px solid #e2e2e8; border-radius: 25px; font-size: .92rem; background: white; outline: none; transition: border .15s, box-shadow .15s; }
.search-input:focus { border-color: #6c63ff; box-shadow: 0 0 0 3px rgba(108,99,255,.13); }
.tag-select { padding: .55rem 1rem; border: 1.5px solid #e2e2e8; border-radius: 25px; font-size: .88rem; background: white; cursor: pointer; outline: none; transition: border .15s; }
.tag-select:focus { border-color: #6c63ff; }

/* ── Sezioni ── */
.sezione { margin-bottom: 3rem; }
.sezione-header { display: flex; align-items: baseline; gap: .85rem; margin-bottom: 1.35rem; padding-bottom: .6rem; border-bottom: 2px solid #eceef2; }
.sezione-title { font-size: 1.35rem; font-weight: 800; color: #1a1a2e; margin: 0; }
.sezione-count { font-size: .82rem; color: #999; font-weight: 500; }

/* ── Griglia eventi ── */
.eventi-grid { display: grid; grid-template-columns: repeat(auto-fill,minmax(290px,1fr)); gap: 1.35rem; }

/* ── Card ── */
.ev-card { background: white; border-radius: 16px; text-decoration: none; color: inherit; box-shadow: 0 2px 14px rgba(0,0,0,.07); transition: transform .2s, box-shadow .2s; display: flex; flex-direction: column; overflow: hidden; }
.ev-card:hover { transform: translateY(-6px); box-shadow: 0 14px 32px rgba(0,0,0,.13); }
.ev-card-img { height: 180px; position: relative; display: flex; flex-direction: column; justify-content: space-between; padding: .8rem; }
.ev-card-badge { align-self: flex-end; background: #f5a623; color: white; font-size: .68rem; font-weight: 800; padding: .22rem .6rem; border-radius: 8px; text-transform: uppercase; letter-spacing: .06em; }
.ev-card-tags { display: flex; flex-wrap: wrap; gap: .3rem; align-self: flex-start; }
.tag { padding: .18rem .55rem; border-radius: 9px; color: white; font-size: .7rem; font-weight: 600; }
.ev-card-prezzo { align-self: flex-end; background: rgba(0,0,0,.52); color: white; font-size: .8rem; font-weight: 700; padding: .25rem .65rem; border-radius: 8px; backdrop-filter: blur(4px); }
.ev-card-body { padding: 1.1rem 1.15rem 1.2rem; display: flex; flex-direction: column; flex: 1; }
.ev-card-title { font-size: 1.03rem; font-weight: 700; color: #1a1a2e; margin: 0 0 .45rem; line-height: 1.35; }
.ev-card-desc { font-size: .85rem; color: #666; margin: 0 0 .9rem; flex: 1; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.ev-card-foot { display: flex; align-items: center; justify-content: space-between; margin-top: auto; }
.ev-card-meta { font-size: .78rem; color: #aaa; }
.ev-card-cta { background: #00c97a; color: white; padding: .42rem 1.1rem; border-radius: 18px; font-size: .82rem; font-weight: 700; white-space: nowrap; }

/* ── Loading / empty  ── */
.loading-eventi { display: flex; justify-content: center; padding: 3rem; }
.empty-eventi { text-align: center; padding: 3rem; color: #999; }
.empty-icon { font-size: 3rem; margin-bottom: .75rem; }

/* ── Paginazione ── */
.paginazione { display: flex; gap: .4rem; justify-content: center; margin-top: 2rem; }
.pag-btn { padding: .45rem .9rem; border: 1.5px solid #e2e2e8; border-radius: 7px; background: white; cursor: pointer; font-size: .88rem; color: #555; transition: all .15s; }
.pag-btn:hover { border-color: #6c63ff; color: #6c63ff; }
.pag-btn--active { background: #6c63ff; color: white; border-color: #6c63ff; font-weight: 700; }

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
  .vetfooter-inner { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 768px) {
  .hero-content h1 { font-size: 1.9rem; }
  .hero-sub { font-size: .95rem; }
  .eventi-grid { grid-template-columns: 1fr; }
  .filtri-inner { flex-direction: column; }
  .search-box { min-width: unset; }
  .tag-select { width: 100%; }
  .vetnav-links { gap: 1rem; }
  .vetfooter-inner { grid-template-columns: 1fr; gap: 1.5rem; }
  .container { padding: 1.75rem 1rem 2.5rem; }
}
@media (max-width: 480px) {
  .hero { min-height: 250px; }
  .hero-content h1 { font-size: 1.5rem; }
  .vetnav-btn { display: none; }
  .ev-card-img { height: 155px; }
}
</style>
