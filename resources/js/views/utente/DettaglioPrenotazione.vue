<template>
  <div class="dettaglio-page">
    <div v-if="loading" class="loading">Caricamento…</div>
    <div v-else-if="errore" class="alert-error">{{ errore }}</div>
    <template v-else-if="prenotazione">
      <div class="page-header">
        <div>
          <router-link to="/prenotazioni/mie" class="back-link">← Le mie prenotazioni</router-link>
          <h1>Prenotazione <span class="codice">{{ prenotazione.codice }}</span></h1>
        </div>
        <span :class="['badge', `badge-${statoClass(prenotazione.stato)}`]">{{ prenotazione.stato }}</span>
      </div>

      <div class="grid-2">
        <div class="card">
          <h2>🗓 Evento</h2>
          <p><strong>{{ prenotazione.sessione?.evento?.titolo }}</strong></p>
          <p class="muted">{{ formatDateTime(prenotazione.sessione?.inizio_at) }}</p>
        </div>

        <div class="card">
          <h2>👤 Titolare</h2>
          <p>{{ prenotazione.cognome }} {{ prenotazione.nome }}</p>
          <p class="muted">{{ prenotazione.email }}</p>
          <p v-if="prenotazione.telefono" class="muted">{{ prenotazione.telefono }}</p>
        </div>
      </div>

      <!-- Posti -->
      <div class="card" v-if="prenotazione.prenotazione_posti?.length">
        <h2>🪑 Posti prenotati</h2>
        <table class="table">
          <thead><tr><th>Tipologia</th><th>Quantità</th><th>Costo unitario</th><th>Totale</th></tr></thead>
          <tbody>
            <tr v-for="p in prenotazione.prenotazione_posti" :key="p.id">
              <td>{{ p.tipologia_posto?.nome ?? '–' }}</td>
              <td>{{ p.quantita }}</td>
              <td>€ {{ Number(p.costo_unitario).toFixed(2) }}</td>
              <td>€ {{ Number(p.costo_totale).toFixed(2) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3"><strong>Totale</strong></td>
              <td><strong>€ {{ Number(prenotazione.importo_totale).toFixed(2) }}</strong></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Risposte form -->
      <div class="card" v-if="prenotazione.risposte_form?.length">
        <h2>📋 Dati aggiuntivi</h2>
        <dl class="risposte">
          <template v-for="r in prenotazione.risposte_form" :key="r.id">
            <dt>{{ r.campo_form?.etichetta }}</dt>
            <dd>{{ r.risposta }}</dd>
          </template>
        </dl>
      </div>

      <!-- Note -->
      <div class="card" v-if="prenotazione.note">
        <h2>📝 Note</h2>
        <p>{{ prenotazione.note }}</p>
      </div>

      <!-- Azioni -->
      <div class="card" v-if="!['ANNULLATA_UTENTE','ANNULLATA_ADMIN','SCADUTA'].includes(prenotazione.stato)">
        <button @click="annulla" :disabled="annullando" class="btn btn-danger">
          {{ annullando ? 'Annullamento…' : '❌ Annulla prenotazione' }}
        </button>
        <p class="muted" style="margin-top: .5rem; font-size: .85rem;">
          L'annullamento è irreversibile.
        </p>
      </div>
    </template>
  </div>
</template>

<script setup>
import { prenotazioniApi } from '@/api/prenotazioni'
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const codice = route.params.codice
const tokenGuest = route.query.token ?? null

const prenotazione = ref(null)
const loading   = ref(true)
const annullando = ref(false)
const errore    = ref('')

const carica = async () => {
  loading.value = true
  errore.value  = ''
  try {
    const res = await prenotazioniApi.show(codice, tokenGuest)
    prenotazione.value = res.data
  } catch (e) {
    errore.value = e.response?.status === 403
      ? 'Accesso non autorizzato a questa prenotazione.'
      : 'Prenotazione non trovata.'
  } finally { loading.value = false }
}

const annulla = async () => {
  if (!confirm('Sei sicuro di voler annullare questa prenotazione?')) return
  annullando.value = true
  try {
    await prenotazioniApi.annullaUtente(codice, tokenGuest)
    prenotazione.value.stato = 'ANNULLATA_UTENTE'
  } catch (e) {
    errore.value = e.response?.data?.message ?? 'Impossibile annullare la prenotazione.'
  } finally { annullando.value = false }
}

const formatDateTime = (d) => d ? new Date(d).toLocaleString('it-IT', { weekday: 'short', day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '–'
const statoClass = (s) => s?.toLowerCase().replace(/_/g, '-') ?? ''

onMounted(carica)
</script>

<style scoped>
.dettaglio-page { max-width: 820px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; }
.back-link { display: block; color: #3498db; text-decoration: none; font-size: .85rem; margin-bottom: .25rem; }
h1 { margin: 0; }
.codice { font-family: monospace; font-size: 1rem; color: #1a5276; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
.card { margin-bottom: 1rem; }
.card h2 { margin-bottom: .75rem; font-size: 1rem; }
.muted { color: #888; font-size: .88rem; margin: .2rem 0; }
.badge { padding: .3rem .75rem; border-radius: 12px; font-size: .82rem; font-weight: 600; text-transform: uppercase; }
.badge-confermata       { background: #d5f5e3; color: #1a7a45; }
.badge-da-confermare    { background: #fef9e7; color: #7d6608; }
.badge-riservata        { background: #d6eaf8; color: #1a5276; }
.badge-annullata-utente,
.badge-annullata-admin  { background: #fadbd8; color: #a93226; }
.badge-scaduta          { background: #eee; color: #555; }
.risposte { display: grid; grid-template-columns: auto 1fr; gap: .3rem 1.5rem; align-items: baseline; }
.risposte dt { font-weight: 600; font-size: .9rem; }
.risposte dd { margin: 0; color: #444; }
.loading { padding: 3rem; text-align: center; color: #aaa; }
.alert-error { background: #fadbd8; color: #922b21; border-radius: 6px; padding: .75rem 1rem; margin-bottom: 1rem; }
tfoot td { padding: .75rem; font-size: 1rem; }
@media (max-width: 600px) { .grid-2 { grid-template-columns: 1fr; } }
</style>
