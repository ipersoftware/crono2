<template>
  <div>
    <h1>📋 Le mie prenotazioni</h1>

    <div class="card">
      <div v-if="loading" class="loading">Caricamento…</div>
      <div v-else-if="prenotazioni.length === 0" class="empty">
        Non hai ancora prenotazioni.
        <router-link to="/" class="link">Esplora gli eventi</router-link>
      </div>
      <table v-else class="table">
        <thead>
          <tr>
            <th>Codice</th>
            <th>Evento</th>
            <th>Data sessione</th>
            <th>Stato</th>
            <th>Azioni</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in prenotazioni" :key="p.id">
            <td class="mono">{{ p.codice }}</td>
            <td>{{ p.sessione?.evento?.titolo ?? '–' }}</td>
            <td>{{ formatDateTime(p.sessione?.inizio_at) }}</td>
            <td>
              <span :class="['badge', `badge-${statoClass(p.stato)}`]">{{ p.stato }}</span>
            </td>
            <td class="actions">
              <router-link :to="`/prenotazioni/${p.codice}`" class="btn btn-sm btn-primary">Dettaglio</router-link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { prenotazioniApi } from '@/api/prenotazioni'
import { onMounted, ref } from 'vue'

const prenotazioni = ref([])
const loading = ref(true)

const carica = async () => {
  try {
    const res = await prenotazioniApi.mie()
    prenotazioni.value = res.data.data ?? res.data
  } finally { loading.value = false }
}

const formatDateTime = (d) => d ? new Date(d).toLocaleString('it-IT', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' }) : '–'
const statoClass = (s) => s?.toLowerCase().replace(/_/g, '-') ?? ''

onMounted(carica)
</script>

<style scoped>
h1 { margin-bottom: 1.5rem; }
.loading, .empty { padding: 2rem; text-align: center; color: #aaa; }
.link { color: #3498db; text-decoration: none; margin-left: .4rem; }
.mono { font-family: monospace; font-size: .85rem; }
.actions { display: flex; gap: .4rem; }
.badge { padding: .22rem .55rem; border-radius: 10px; font-size: .75rem; font-weight: 600; text-transform: uppercase; }
.badge-confermata       { background: #d5f5e3; color: #1a7a45; }
.badge-da-confirmare    { background: #fef9e7; color: #7d6608; }
.badge-riservata        { background: #d6eaf8; color: #1a5276; }
.badge-annullata-utente,
.badge-annullata-admin  { background: #fadbd8; color: #a93226; }
.badge-scaduta          { background: #eee; color: #555; }
.btn-sm { padding: .3rem .65rem; font-size: .82rem; }
</style>
