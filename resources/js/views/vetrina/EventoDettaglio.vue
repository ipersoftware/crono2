<template>
  <div class="evento-dettaglio">
    <div v-if="loading" class="loading">Caricamento…</div>
    <div v-else-if="!evento" class="empty">Evento non trovato.</div>
    <template v-else>
      <!-- Header evento -->
      <div class="evento-hero">
        <div class="container">
          <div class="back-link-wrap">
            <router-link :to="`/vetrina/${shopUrl}`" class="back-link">← Torna alla vetrina</router-link>
          </div>
          <div class="evento-tags">
            <span
              v-for="t in evento.tags"
              :key="t.id"
              class="tag"
              :style="{ background: t.colore || '#3498db' }"
            >{{ t.nome }}</span>
          </div>
          <h1>{{ evento.titolo }}</h1>
          <p v-if="evento.descrizione_breve" class="subtitle">{{ evento.descrizione_breve }}</p>
        </div>
      </div>

      <div class="container corpo">
        <div class="left">
          <!-- Descrizione -->
          <div class="card" v-if="evento.descrizione">
            <h2>📋 Descrizione</h2>
            <div class="descrizione" v-html="evento.descrizione"></div>
          </div>

          <!-- Luoghi -->
          <div class="card" v-if="evento.luoghi?.length">
            <h2>📍 Luoghi</h2>
            <ul class="luoghi-list">
              <li v-for="l in evento.luoghi" :key="l.id">
                <strong>{{ l.nome }}</strong>
                <span v-if="l.indirizzo" class="muted"> — {{ l.indirizzo }}</span>
                <a v-if="l.maps_url" :href="l.maps_url" target="_blank" class="maps-link">Mappa</a>
              </li>
            </ul>
          </div>
        </div>

        <div class="right">
          <!-- Sessioni disponibili -->
          <div class="card">
            <h2>🗓 Sessioni disponibili</h2>
            <div v-if="evento.sessioni?.length === 0" class="empty-small">
              Nessuna sessione aperta al momento.
            </div>
            <div v-else>
              <div
                v-for="s in evento.sessioni"
                :key="s.id"
                class="sessione-row"
              >
                <div class="sessione-data">
                  <strong>{{ formatDateTime(s.inizio_at) }}</strong>
                  <span v-if="s.fine_at" class="muted"> → {{ formatDateTime(s.fine_at) }}</span>
                </div>
                <div class="sessione-info">
                  <span v-if="s.posti_totali">
                    {{ s.posti_totali - s.posti_prenotati - s.posti_riservati }} posti disponibili
                  </span>
                  <span v-else>Posti illimitati</span>
                </div>
                <!-- Tipologie -->
                <div v-if="s.tipologie_disponibili?.length" class="tipologie">
                  <span
                    v-for="t in s.tipologie_disponibili"
                    :key="t.id"
                    class="tipologia-chip"
                  >
                    {{ t.tipologia_posto?.nome }}
                    <em v-if="!t.tipologia_posto?.gratuita">€{{ Number(t.tipologia_posto?.costo).toFixed(2) }}</em>
                    <em v-else>Gratuito</em>
                  </span>
                </div>
                <router-link
                  :to="`/vetrina/${shopUrl}/prenota/${evento.slug}/${s.id}`"
                  class="btn btn-primary btn-prenota"
                >Prenota →</router-link>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { vetrinaApi } from '@/api/vetrina'
import { onMounted, ref } from 'vue'
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

const formatDateTime = (d) => {
  if (!d) return '–'
  return new Date(d).toLocaleString('it-IT', { weekday: 'short', day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

onMounted(carica)
</script>

<style scoped>
.evento-hero { background: linear-gradient(135deg, #1a252f, #2c3e50); color: white; padding: 3rem 0 2.5rem; }
.container { max-width: 1100px; margin: 0 auto; padding: 0 1rem; }
.back-link-wrap { margin-bottom: 1rem; }
.back-link { color: rgba(255,255,255,.75); text-decoration: none; font-size: .9rem; }
.evento-tags { display: flex; gap: .4rem; margin-bottom: .8rem; flex-wrap: wrap; }
.tag { padding: .2rem .7rem; border-radius: 10px; color: white; font-size: .75rem; }
.evento-hero h1 { font-size: 2rem; margin: 0 0 .5rem 0; }
.subtitle { opacity: .85; margin: 0; font-size: 1.05rem; }
.corpo { display: grid; grid-template-columns: 1fr 380px; gap: 1.5rem; padding-top: 2rem; padding-bottom: 3rem; align-items: start; }
.left, .right { display: flex; flex-direction: column; gap: 1rem; }
.card h2 { margin-bottom: .9rem; font-size: 1.1rem; }
.descrizione { line-height: 1.7; }
.luoghi-list { list-style: none; padding: 0; }
.luoghi-list li { margin: .4rem 0; }
.muted { color: #999; }
.maps-link { margin-left: .5rem; color: #3498db; font-size: .85rem; text-decoration: none; }
.empty-small { color: #aaa; font-size: .9rem; }
.sessione-row { border: 1px solid #eef; border-radius: 8px; padding: .9rem; margin-bottom: .75rem; }
.sessione-data { font-size: .95rem; margin-bottom: .3rem; }
.sessione-info { font-size: .85rem; color: #666; margin-bottom: .5rem; }
.tipologie { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: .7rem; }
.tipologia-chip { background: #f0f4f8; border-radius: 8px; padding: .2rem .6rem; font-size: .8rem; }
.tipologia-chip em { color: #27ae60; margin-left: .3rem; font-style: normal; }
.btn-prenota { display: block; text-align: center; padding: .6rem; border-radius: 6px; text-decoration: none; font-weight: 600; }
.loading, .empty { padding: 3rem; text-align: center; color: #aaa; }
@media (max-width: 720px) {
  .corpo { grid-template-columns: 1fr; }
}
</style>
