<template>
  <div class="vetrina">
    <div v-if="loading" class="loading">Caricamento…</div>
    <div v-else-if="!ente" class="empty">Vetrina non trovata.</div>
    <template v-else>
      <!-- Hero -->
      <div class="hero" :style="ente.copertina ? { backgroundImage: `url(${ente.copertina})` } : {}">
        <div class="hero-overlay">
          <h1>{{ ente.nome }}</h1>
        </div>
      </div>

      <div class="container">
        <!-- Contenuto vetrina -->
        <div v-if="ente.contenuto_vetrina" class="card descrizione" v-html="ente.contenuto_vetrina"></div>

        <!-- Ricerca + filtri -->
        <div class="filtri card">
          <div class="filtri-row">
            <input v-model="filtri.q" @input="caricaEventi" placeholder="Cerca evento…" class="input" />
            <select v-model="filtri.tag_id" @change="caricaEventi" class="input">
              <option value="">Tutti i tag</option>
              <option v-for="tag in tags" :key="tag.id" :value="tag.id">{{ tag.nome }}</option>
            </select>
          </div>
        </div>

        <!-- Eventi in evidenza -->
        <section v-if="inEvidenza.length" class="sezione">
          <h2>⭐ In evidenza</h2>
          <div class="eventi-grid">
            <router-link
              v-for="ev in inEvidenza"
              :key="ev.id"
              :to="`/vetrina/${shopUrl}/eventi/${ev.slug}`"
              class="evento-card"
            >
              <div class="evento-card-body">
                <div class="evento-tags">
                  <span
                    v-for="t in ev.tags"
                    :key="t.id"
                    class="tag"
                    :style="{ background: t.colore || '#3498db' }"
                  >{{ t.nome }}</span>
                </div>
                <h3>{{ ev.titolo }}</h3>
                <p class="evento-desc">{{ ev.descrizione_breve }}</p>
              </div>
            </router-link>
          </div>
        </section>

        <!-- Tutti gli eventi -->
        <section class="sezione">
          <h2>📋 Tutti gli eventi</h2>
          <div v-if="loadingEventi" class="loading">Caricamento…</div>
          <div v-else-if="eventi.length === 0" class="empty">Nessun evento disponibile.</div>
          <div v-else class="eventi-grid">
            <router-link
              v-for="ev in eventi"
              :key="ev.id"
              :to="`/vetrina/${shopUrl}/eventi/${ev.slug}`"
              class="evento-card"
            >
              <div class="evento-card-body">
                <div class="evento-tags">
                  <span
                    v-for="t in ev.tags"
                    :key="t.id"
                    class="tag"
                    :style="{ background: t.colore || '#3498db' }"
                  >{{ t.nome }}</span>
                </div>
                <h3>{{ ev.titolo }}</h3>
                <p class="evento-desc">{{ ev.descrizione_breve }}</p>
                <div class="evento-meta">{{ ev.sessioni_count }} sessioni disponibili</div>
              </div>
            </router-link>
          </div>

          <!-- Paginazione -->
          <div v-if="meta?.last_page > 1" class="paginazione">
            <button
              v-for="n in meta.last_page"
              :key="n"
              :class="['btn', 'btn-sm', n === meta.current_page ? 'btn-primary' : 'btn-secondary']"
              @click="pagina = n; caricaEventi()"
            >{{ n }}</button>
          </div>
        </section>
      </div>
    </template>
  </div>
</template>

<script setup>
import { vetrinaApi } from '@/api/vetrina'
import { onMounted, reactive, ref } from 'vue'
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
.vetrina { min-height: 100vh; background: #f8f9fa; }
.hero { height: 240px; background: linear-gradient(135deg, #2c3e50, #3498db); background-size: cover; background-position: center; }
.hero-overlay { height: 100%; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,.35); }
.hero-overlay h1 { color: white; font-size: 2.2rem; text-align: center; }
.container { max-width: 1100px; margin: 0 auto; padding: 2rem 1rem; }
.descrizione { margin-bottom: 1.5rem; }
.filtri { margin-bottom: 1.5rem; }
.filtri-row { display: flex; gap: .75rem; flex-wrap: wrap; }
.input { padding: .45rem .75rem; border: 1px solid #ddd; border-radius: 6px; font-size: .9rem; flex: 1; min-width: 160px; }
.sezione { margin-bottom: 2.5rem; }
.sezione h2 { margin-bottom: 1rem; }
.eventi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem; }
.evento-card { background: white; border-radius: 10px; text-decoration: none; color: inherit; box-shadow: 0 2px 6px rgba(0,0,0,.07); transition: transform .15s, box-shadow .15s; display: block; }
.evento-card:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0,0,0,.12); }
.evento-card-body { padding: 1.2rem; }
.evento-tags { display: flex; flex-wrap: wrap; gap: .3rem; margin-bottom: .6rem; }
.tag { padding: .15rem .55rem; border-radius: 10px; color: white; font-size: .72rem; }
.evento-card h3 { margin: 0 0 .4rem 0; font-size: 1.05rem; }
.evento-desc { color: #666; font-size: .88rem; margin: 0 0 .6rem 0; }
.evento-meta { font-size: .78rem; color: #aaa; }
.loading, .empty { padding: 2rem; text-align: center; color: #aaa; }
.paginazione { display: flex; gap: .4rem; justify-content: center; margin-top: 1.5rem; }
.btn-sm { padding: .3rem .65rem; font-size: .82rem; border: none; border-radius: 4px; cursor: pointer; }
.btn-secondary { background: #ecf0f1; color: #2c3e50; }
</style>
