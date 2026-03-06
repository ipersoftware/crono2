<template>
  <div>
    <div class="page-header">
      <h1>✉ Template email</h1>
    </div>

    <div class="layout">
      <!-- Lista tipi -->
      <div class="sidebar card">
        <div
          v-for="tipo in tipiDisponibili"
          :key="tipo"
          :class="['tipo-item', { active: tipoAttivo === tipo }]"
          @click="seleziona(tipo)"
        >
          <div class="tipo-nome">{{ tipoLabel(tipo) }}</div>
          <span v-if="templatePersonalizzati.has(tipo)" class="badge-custom">personalizzato</span>
        </div>
      </div>

      <!-- Editor -->
      <div class="editor-area">
        <div v-if="!tipoAttivo" class="empty">Seleziona un tipo di template dalla lista.</div>
        <div v-else class="card">
          <div class="editor-header">
            <h2>{{ tipoLabel(tipoAttivo) }}</h2>
            <div class="editor-actions">
              <button
                v-if="templatePersonalizzati.has(tipoAttivo)"
                @click="ripristina"
                class="btn btn-secondary btn-sm"
              >Ripristina predefinito</button>
              <button @click="salva" :disabled="saving" class="btn btn-primary btn-sm">
                {{ saving ? 'Salvataggio…' : 'Salva' }}
              </button>
            </div>
          </div>

          <div class="form-group">
            <label>Oggetto email *</label>
            <input v-model="form.oggetto" class="input" required />
          </div>

          <div class="form-group">
            <label>Corpo HTML</label>
            <div class="helper">Usa <code v-pre>{{nome}}</code>, <code v-pre>{{codice}}</code>, <code v-pre>{{evento}}</code>, <code v-pre>{{link}}</code>, ecc.</div>
            <div class="corpo-tabs">
              <button type="button" :class="['tab-btn', { active: corpoTab === 'modifica' }]" @click="corpoTab = 'modifica'">✏️ Modifica</button>
              <button type="button" :class="['tab-btn', { active: corpoTab === 'anteprima' }]" @click="corpoTab = 'anteprima'">👁 Anteprima</button>
            </div>
            <textarea v-if="corpoTab === 'modifica'" v-model="form.corpo" rows="16" class="input mono-area"></textarea>
            <div v-else class="anteprima-corpo" v-html="form.corpo"></div>
          </div>

          <div v-if="errore" class="alert-error">{{ errore }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { mailTemplatesApi } from '@/api/admin'
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'

const route  = useRoute()
const enteId = route.params.enteId

const tipiDisponibili = [
  'PRENOTAZIONE_CONFERMATA',
  'PRENOTAZIONE_DA_CONFERMARE',
  'PRENOTAZIONE_APPROVATA',
  'PRENOTAZIONE_ANNULLATA_UTENTE',
  'PRENOTAZIONE_ANNULLATA_OPERATORE',
  'PRENOTAZIONE_NOTIFICA_STAFF',
  'EVENTO_ANNULLATO',
  'LISTA_ATTESA_ISCRIZIONE',
  'LISTA_ATTESA_POSTO_DISPONIBILE',
  'LISTA_ATTESA_SCADENZA',
  'REMINDER_EVENTO',
  'REGISTRAZIONE_CONFERMATA',
  'RESET_PASSWORD',
]

const templates            = ref([])
const templatePersonalizzati = ref(new Set())
const tipoAttivo           = ref(null)
const saving               = ref(false)
const errore               = ref('')
const form = reactive({ oggetto: '', corpo: '' })
const corpoTab = ref('modifica')

const carica = async () => {
  const res = await mailTemplatesApi.index(enteId)
  templates.value = res.data
  templatePersonalizzati.value = new Set(templates.value.map(t => t.tipo))
}

const seleziona = async (tipo) => {
  tipoAttivo.value = tipo
  errore.value     = ''
  try {
    const res = await mailTemplatesApi.show(enteId, tipo)
    Object.assign(form, { oggetto: res.data.oggetto, corpo: res.data.corpo })
    corpoTab.value = 'modifica'
  } catch {
    Object.assign(form, { oggetto: '', corpo: '' })
  }
}

const salva = async () => {
  saving.value = true
  errore.value = ''
  try {
    await mailTemplatesApi.store(enteId, { tipo: tipoAttivo.value, ...form })
    templatePersonalizzati.value.add(tipoAttivo.value)
  } catch (e) {
    errore.value = e.response?.data?.message ?? 'Errore.'
  } finally { saving.value = false }
}

const ripristina = async () => {
  if (!confirm('Eliminare il template personalizzato e tornare al predefinito?')) return
  const t = templates.value.find(x => x.tipo === tipoAttivo.value)
  if (t) {
    await mailTemplatesApi.destroy(enteId, t.id)
    templatePersonalizzati.value.delete(tipoAttivo.value)
    await seleziona(tipoAttivo.value)
  }
}

const tipoLabel = (tipo) => ({
  PRENOTAZIONE_CONFERMATA:       '✅ Conferma prenotazione',
  PRENOTAZIONE_DA_CONFERMARE:    '📩 Prenotazione da confermare',
  PRENOTAZIONE_APPROVATA:        '👍 Prenotazione approvata',
  PRENOTAZIONE_ANNULLATA_UTENTE: '❌ Annullamento (utente)',
  PRENOTAZIONE_ANNULLATA_OPERATORE: '❌ Annullamento (operatore)',
  PRENOTAZIONE_NOTIFICA_STAFF:   '👤 Notifica staff',
  EVENTO_ANNULLATO:              '🚫 Evento annullato',
  LISTA_ATTESA_ISCRIZIONE:       '🔔 Lista attesa — iscrizione',
  LISTA_ATTESA_POSTO_DISPONIBILE:'✔ Lista attesa — posto disponibile',
  LISTA_ATTESA_SCADENZA:         '⏰ Lista attesa — scadenza',
  REMINDER_EVENTO:               '⏰ Promemoria evento',
  REGISTRAZIONE_CONFERMATA:      '👋 Registrazione confermata',
  RESET_PASSWORD:                '🔑 Reset password',
}[tipo] ?? tipo)

onMounted(carica)
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.layout { display: grid; grid-template-columns: 240px 1fr; gap: 1rem; align-items: start; }
.sidebar { padding: .5rem; }
.tipo-item { padding: .6rem .85rem; border-radius: 6px; cursor: pointer; margin-bottom: .2rem; display: flex; justify-content: space-between; align-items: center; }
.tipo-item:hover { background: #f0f4f8; }
.tipo-item.active { background: #ebf5fb; color: #1a5276; font-weight: 600; }
.tipo-nome { font-size: .88rem; }
.badge-custom { background: #d6eaf8; color: #1a5276; border-radius: 8px; padding: .1rem .45rem; font-size: .7rem; }
.empty { padding: 3rem; text-align: center; color: #aaa; }
.editor-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; }
.editor-actions { display: flex; gap: .5rem; }
.form-group { margin-bottom: .9rem; }
.form-group label { display: block; margin-bottom: .3rem; font-weight: 500; font-size: .9rem; }
.input { width: 100%; padding: .45rem .75rem; border: 1px solid #ddd; border-radius: 6px; font-size: .9rem; box-sizing: border-box; }
.mono-area { font-family: 'Courier New', monospace; font-size: .85rem; }
.corpo-tabs { display: flex; gap: .35rem; margin-bottom: .4rem; }
.tab-btn { padding: .25rem .75rem; border: 1px solid #ddd; border-radius: 6px 6px 0 0; background: #f8f9fa; cursor: pointer; font-size: .82rem; }
.tab-btn.active { background: #fff; border-bottom-color: #fff; font-weight: 600; color: #1a5276; }
.anteprima-corpo { border: 1px solid #ddd; border-radius: 0 6px 6px 6px; padding: 1rem 1.25rem; min-height: 260px; background: #fff; font-size: .9rem; line-height: 1.6; overflow-y: auto; }
.helper { font-size: .8rem; color: #888; margin-bottom: .35rem; }
.alert-error { background: #fadbd8; color: #922b21; border-radius: 6px; padding: .75rem 1rem; }
.btn-sm { padding: .3rem .65rem; font-size: .82rem; }
.btn-secondary { background: #ecf0f1; color: #2c3e50; border: none; border-radius: 6px; padding: .45rem 1rem; cursor: pointer; }
</style>
