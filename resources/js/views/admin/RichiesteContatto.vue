<template>
  <div>
    <div class="page-header">
      <h1>✉️ Richieste di contatto</h1>
      <span v-if="meta?.total" class="badge-count">{{ meta.total }} totali</span>
    </div>

    <div class="card">
      <div v-if="loading" class="loading">Caricamento…</div>
      <div v-else-if="richieste.length === 0" class="empty">
        Nessuna richiesta ricevuta ancora.
      </div>
      <div v-else>
        <table class="table">
          <thead>
            <tr>
              <th></th>
              <th>Data</th>
              <th>Nome</th>
              <th>Email</th>
              <th>Telefono</th>
              <th>Messaggio</th>
              <th>Azioni</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="r in richieste"
              :key="r.id"
              :class="{ 'row-non-letta': !r.letta }"
            >
              <td>
                <span
                  :title="r.letta ? 'Segnato come letto' : 'Non letto'"
                  class="dot"
                  :class="r.letta ? 'dot--letta' : 'dot--nuova'"
                ></span>
              </td>
              <td data-label="Data" class="muted nowrap">{{ formatData(r.created_at) }}</td>
              <td data-label="Nome"><strong>{{ r.nome }}</strong></td>
              <td data-label="Email">
                <a :href="`mailto:${r.email}`" class="link-email">{{ r.email }}</a>
              </td>
              <td data-label="Telefono" class="muted">{{ r.telefono || '—' }}</td>
              <td data-label="Messaggio">
                <div class="messaggio-wrap" :class="{ expanded: espanso === r.id }">
                  {{ r.messaggio }}
                </div>
                <button
                  v-if="r.messaggio.length > 120"
                  class="btn-testo"
                  @click="espanso = espanso === r.id ? null : r.id"
                >{{ espanso === r.id ? 'Riduci' : 'Leggi tutto' }}</button>
              </td>
              <td data-label="Azioni" class="actions">
                <button
                  @click="toggleLetta(r)"
                  class="btn btn-sm"
                  :class="r.letta ? 'btn-secondary' : 'btn-primary'"
                  :title="r.letta ? 'Segna come non letta' : 'Segna come letta'"
                >{{ r.letta ? 'Non letta' : '✓ Letta' }}</button>
                <button @click="elimina(r)" class="btn btn-sm btn-danger">Elimina</button>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Paginazione -->
        <div v-if="meta?.last_page > 1" class="paginazione">
          <button
            v-for="n in meta.last_page"
            :key="n"
            :class="['btn', 'btn-sm', n === meta.current_page ? 'btn-primary' : 'btn-secondary']"
            @click="pagina = n; carica()"
          >{{ n }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { richiesteContattoApi } from '@/api/admin'
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'

const route   = useRoute()
const enteId  = Number(route.params.enteId)

const richieste = ref([])
const meta      = ref(null)
const loading   = ref(true)
const pagina    = ref(1)
const espanso   = ref(null)

const carica = async () => {
  loading.value = true
  try {
    const res = await richiesteContattoApi.index(enteId, { page: pagina.value })
    richieste.value = res.data.data
    meta.value      = res.data
  } finally { loading.value = false }
}

const toggleLetta = async (r) => {
  const res = await richiesteContattoApi.segnaLetta(enteId, r.id)
  const idx = richieste.value.findIndex(x => x.id === r.id)
  if (idx !== -1) richieste.value[idx] = res.data
}

const elimina = async (r) => {
  if (!confirm(`Eliminare la richiesta di ${r.nome}?`)) return
  await richiesteContattoApi.destroy(enteId, r.id)
  richieste.value = richieste.value.filter(x => x.id !== r.id)
  if (meta.value) meta.value.total = Math.max(0, meta.value.total - 1)
}

const formatData = (d) => {
  if (!d) return '—'
  return new Date(d).toLocaleString('it-IT', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

onMounted(carica)
</script>

<style scoped>
.page-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
.badge-count { background: #6c63ff; color: white; font-size: .78rem; font-weight: 700; padding: .2rem .65rem; border-radius: 20px; }
.row-non-letta { background: #f8f7ff; }
.dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; }
.dot--nuova { background: #6c63ff; box-shadow: 0 0 0 3px rgba(108,99,255,.2); }
.dot--letta  { background: #ddd; }
.nowrap { white-space: nowrap; }
.link-email { color: #6c63ff; text-decoration: none; }
.link-email:hover { text-decoration: underline; }
.messaggio-wrap { font-size: .88rem; color: #444; max-height: 3.4em; overflow: hidden; line-height: 1.7; transition: max-height .2s; }
.messaggio-wrap.expanded { max-height: 500px; }
.btn-testo { background: none; border: none; color: #6c63ff; font-size: .78rem; cursor: pointer; padding: 0; margin-top: .2rem; font-weight: 600; }
.btn-testo:hover { text-decoration: underline; }
.actions { display: flex; gap: .4rem; }
.paginazione { display: flex; gap: .4rem; justify-content: center; margin-top: 1.5rem; }
</style>
