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
            <div class="helper">Usa <code v-pre>{{nome_utente}}</code>, <code v-pre>{{codice_prenotazione}}</code>, <code v-pre>{{titolo_evento}}</code>, <code v-pre>{{link_conferma}}</code>, ecc.</div>
            <Editor
              v-model="form.corpo"
              api-key="jzd2a0zvkf6gmknn3yaxfk66pb6c1zzjnap5x6uzxo717ufn"
              :init="tinyInit"
            />
          </div>

          <div v-if="errore" class="alert-error">{{ errore }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { mailTemplatesApi } from '@/api/admin'
import Editor from '@tinymce/tinymce-vue'
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'

const route  = useRoute()
const enteId = route.params.enteId

const tinyInit = {
  height: 480,
  menubar: false,
  plugins: [
    'advlist', 'autolink', 'lists', 'link', 'charmap',
    'searchreplace', 'visualblocks', 'code', 'fullscreen',
    'table', 'wordcount',
  ],
  toolbar:
    'undo redo | blocks | bold italic underline | forecolor backcolor | ' +
    'alignleft aligncenter alignright alignjustify | ' +
    'bullist numlist outdent indent | link | code | removeformat',
  content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; }',
  entity_encoding: 'raw',
  verify_html: false,
  valid_elements: '*[*]',
  extended_valid_elements: '*[*]',
  valid_children: '+body[style]',
}

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
  'BENVENUTO_OPERATORE',
]

const templates            = ref([])
const templatePersonalizzati = ref(new Set())
const tipoAttivo           = ref(null)
const saving               = ref(false)
const errore               = ref('')
const form = reactive({ oggetto: '', corpo: '' })

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
  BENVENUTO_OPERATORE:           '🔐 Benvenuto operatore',
}[tipo] ?? tipo)

onMounted(carica)
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: .75rem; }
.layout { display: grid; grid-template-columns: 240px 1fr; gap: 1rem; align-items: start; }
.sidebar { padding: .5rem; }
.tipo-item { padding: .6rem .85rem; border-radius: 6px; cursor: pointer; margin-bottom: .2rem; display: flex; justify-content: space-between; align-items: center; gap: .5rem; }
.tipo-item:hover { background: #f0f4f8; }
.tipo-item.active { background: #ebf5fb; color: #1a5276; font-weight: 600; }
.tipo-nome { font-size: .88rem; }
.badge-custom { background: #d6eaf8; color: #1a5276; border-radius: 8px; padding: .1rem .45rem; font-size: .7rem; white-space: nowrap; flex-shrink: 0; }
.empty { padding: 3rem; text-align: center; color: #aaa; }
.editor-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: .6rem; }
.editor-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
.form-group { margin-bottom: .9rem; }
.form-group label { display: block; margin-bottom: .3rem; font-weight: 500; font-size: .9rem; }
.input { width: 100%; padding: .45rem .75rem; border: 1px solid #ddd; border-radius: 6px; font-size: .9rem; box-sizing: border-box; }

.helper { font-size: .8rem; color: #888; margin-bottom: .35rem; }
.alert-error { background: #fadbd8; color: #922b21; border-radius: 6px; padding: .75rem 1rem; }
.btn-sm { padding: .3rem .65rem; font-size: .82rem; }
.btn-secondary { background: #ecf0f1; color: #2c3e50; border: none; border-radius: 6px; padding: .45rem 1rem; cursor: pointer; }

@media (max-width: 768px) {
  .layout { grid-template-columns: 1fr; }
  .sidebar { padding: .25rem; }
  .tipo-item { padding: .55rem .75rem; }
  .editor-header { flex-direction: column; align-items: flex-start; }
  .editor-actions { width: 100%; }
  .editor-actions .btn { flex: 1; text-align: center; }
}
</style>
