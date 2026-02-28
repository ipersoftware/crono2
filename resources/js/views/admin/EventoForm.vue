<template>
  <div>
    <div class="page-header">
      <h1>{{ isNuovo ? '+ Nuovo evento' : '✏️ Modifica evento' }}</h1>
      <router-link :to="`/admin/${enteId}/eventi`" class="btn btn-secondary">← Torna agli eventi</router-link>
    </div>

    <!-- Tab navigation -->
    <div class="tabs">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        :class="['tab-btn', { active: tabAttivo === tab.key }]"
        @click="tabAttivo = tab.key"
        :disabled="isNuovo && tab.key !== 'dettagli'"
      >{{ tab.label }}</button>
    </div>

    <!-- TAB: Dettagli base -->
    <div v-if="tabAttivo === 'dettagli'" class="card">
      <form @submit.prevent="salva">
        <div class="grid-2">
          <div class="form-group">
            <label>Titolo *</label>
            <input v-model="form.titolo" required class="input" placeholder="Titolo evento" />
          </div>
          <div class="form-group">
            <label>Stato</label>
            <select v-model="form.stato" class="input">
              <option value="BOZZA">Bozza</option>
              <option value="PUBBLICATO">Pubblicato</option>
              <option value="SOSPESO">Sospeso</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label>Descrizione breve</label>
          <textarea v-model="form.descrizione_breve" rows="2" class="input" placeholder="Breve descrizione (max 300 caratteri)"></textarea>
        </div>

        <div class="form-group">
          <label>Descrizione completa</label>
          <textarea v-model="form.descrizione" rows="5" class="input" placeholder="Descrizione completa HTML/testo"></textarea>
        </div>

        <div class="grid-3">
          <div class="form-group">
            <label>Serie</label>
            <select v-model="form.serie_id" class="input">
              <option :value="null">— nessuna —</option>
              <option v-for="s in serie" :key="s.id" :value="s.id">{{ s.titolo }}</option>
            </select>
          </div>
          <div class="form-group">
            <label>Max prenotazioni per utente</label>
            <input v-model.number="form.max_prenotazioni_utente" type="number" min="1" class="input" />
          </div>
          <div class="form-group">
            <label>Cancellazione consentita (ore prima)</label>
            <input v-model.number="form.cancellazione_consentita_ore" type="number" min="0" class="input" placeholder="0 = non consentita" />
          </div>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="checkbox-label">
              <input type="checkbox" v-model="form.richiede_approvazione" />
              Richiede approvazione operatore
            </label>
          </div>
          <div class="form-group">
            <label class="checkbox-label">
              <input type="checkbox" v-model="form.lista_attesa_abilitata" />
              Lista d'attesa abilitata
            </label>
          </div>
        </div>

        <!-- Tag -->
        <div class="form-group">
          <label>Tag</label>
          <div class="tags-flex">
            <label v-for="tag in tags" :key="tag.id" class="tag-check">
              <input type="checkbox" :value="tag.id" v-model="form.tag_ids" />
              <span :style="{ background: tag.colore || '#3498db' }" class="tag-badge">{{ tag.nome }}</span>
            </label>
          </div>
        </div>

        <div v-if="errore" class="alert-error">{{ errore }}</div>

        <div class="form-actions">
          <button type="submit" :disabled="saving" class="btn btn-primary">
            {{ saving ? 'Salvataggio…' : (isNuovo ? 'Crea evento' : 'Salva modifiche') }}
          </button>
        </div>
      </form>
    </div>

    <!-- TAB: Tipologie posto -->
    <div v-if="tabAttivo === 'tipologie'" class="card">
      <div class="section-header">
        <h2>Tipologie di posto</h2>
        <button @click="nuovaTipologia" class="btn btn-primary btn-sm">+ Aggiungi</button>
      </div>

      <div v-if="tipologie.length === 0" class="empty">Nessuna tipologia. Aggiungine una per gestire i posti.</div>

      <div v-for="(t, i) in tipologie" :key="t.id ?? i" class="tipologia-row">
        <div class="grid-3">
          <div class="form-group">
            <label>Nome</label>
            <input v-model="t.nome" class="input" @blur="salvaTipologia(t)" />
          </div>
          <div class="form-group">
            <label>Costo (€)</label>
            <input v-model.number="t.costo" type="number" step="0.01" min="0" class="input" @blur="salvaTipologia(t)" />
          </div>
          <div class="form-group">
            <label class="checkbox-label" style="margin-top:1.5rem">
              <input type="checkbox" v-model="t.gratuita" @change="salvaTipologia(t)" />
              Gratuita
            </label>
          </div>
        </div>
        <button @click="eliminaTipologia(t, i)" class="btn btn-danger btn-sm" style="margin-top:.5rem">Elimina</button>
      </div>
    </div>

    <!-- TAB: Campi form -->
    <div v-if="tabAttivo === 'form'" class="card">
      <div class="section-header">
        <h2>Campi del modulo di prenotazione</h2>
        <button @click="nuovoCampo" class="btn btn-primary btn-sm">+ Aggiungi campo</button>
      </div>

      <div v-if="campi.length === 0" class="empty">Nessun campo. Il modulo utilizzerà solo i dati base (nome, email).</div>

      <div v-for="(c, i) in campi" :key="c.id ?? i" class="campo-row">
        <div class="grid-3">
          <div class="form-group">
            <label>Tipo</label>
            <select v-model="c.tipo" class="input" @change="salvaCampo(c)">
              <option v-for="tipo in tipiCampo" :key="tipo" :value="tipo">{{ tipo }}</option>
            </select>
          </div>
          <div class="form-group">
            <label>Etichetta</label>
            <input v-model="c.etichetta" class="input" @blur="salvaCampo(c)" />
          </div>
          <div class="form-group">
            <label class="checkbox-label" style="margin-top:1.5rem">
              <input type="checkbox" v-model="c.obbligatorio" @change="salvaCampo(c)" />
              Obbligatorio
            </label>
          </div>
        </div>
        <button @click="eliminaCampo(c, i)" class="btn btn-danger btn-sm">Elimina</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { serieApi, tagsApi } from '@/api/admin'
import { campiFormApi, eventiApi, tipologiePostoApi } from '@/api/eventi'
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route  = useRoute()
const router = useRouter()
const enteId  = route.params.enteId
const eventoId = route.params.eventoId
const isNuovo = !eventoId

const tabAttivo = ref('dettagli')
const tabs = [
  { key: 'dettagli',  label: '📝 Dettagli'    },
  { key: 'tipologie', label: '🪑 Tipologie posto' },
  { key: 'form',      label: '📋 Campi form'  },
]

const form = reactive({
  titolo: '', descrizione_breve: '', descrizione: '',
  stato: 'BOZZA', serie_id: null,
  max_prenotazioni_utente: 1, cancellazione_consentita_ore: 0,
  richiede_approvazione: false, lista_attesa_abilitata: false,
  tag_ids: [],
})
const serie = ref([])
const tags  = ref([])
const tipologie = ref([])
const campi = ref([])
const saving = ref(false)
const errore = ref('')
const tipiCampo = ['TEXT','TEXTAREA','SELECT','CHECKBOX','RADIO','DATE','EMAIL','PHONE','NUMBER']

const caricaDati = async () => {
  const [tagsRes, serieRes] = await Promise.all([
    tagsApi.index(enteId),
    serieApi.index(enteId),
  ])
  tags.value  = tagsRes.data.data ?? tagsRes.data
  serie.value = serieRes.data.data ?? serieRes.data

  if (!isNuovo) {
    const evRes = await eventiApi.show(enteId, eventoId)
    const ev = evRes.data
    Object.assign(form, {
      titolo: ev.titolo, descrizione_breve: ev.descrizione_breve,
      descrizione: ev.descrizione, stato: ev.stato,
      serie_id: ev.serie_id, max_prenotazioni_utente: ev.max_prenotazioni_utente,
      cancellazione_consentita_ore: ev.cancellazione_consentita_ore,
      richiede_approvazione: !!ev.richiede_approvazione,
      lista_attesa_abilitata: !!ev.lista_attesa_abilitata,
      tag_ids: ev.tags?.map(t => t.id) ?? [],
    })

    const [tipRes, campiRes] = await Promise.all([
      tipologiePostoApi.index(enteId, eventoId),
      campiFormApi.index(enteId, eventoId),
    ])
    tipologie.value = tipRes.data
    campi.value     = campiRes.data
  }
}

const salva = async () => {
  saving.value = true
  errore.value = ''
  try {
    if (isNuovo) {
      const res = await eventiApi.store(enteId, form)
      router.push(`/admin/${enteId}/eventi/${res.data.id}`)
    } else {
      await eventiApi.update(enteId, eventoId, form)
    }
  } catch (e) {
    errore.value = e.response?.data?.message ?? 'Errore durante il salvataggio.'
  } finally {
    saving.value = false
  }
}

// --- Tipologie ---
const nuovaTipologia = () => tipologie.value.push({ nome: '', costo: 0, gratuita: false })
const salvaTipologia = async (t) => {
  if (!t.nome) return
  if (t.id) {
    await tipologiePostoApi.update(enteId, eventoId, t.id, t)
  } else {
    const res = await tipologiePostoApi.store(enteId, eventoId, t)
    t.id = res.data.id
  }
}
const eliminaTipologia = async (t, i) => {
  if (t.id) await tipologiePostoApi.destroy(enteId, eventoId, t.id)
  tipologie.value.splice(i, 1)
}

// --- Campi form ---
const nuovoCampo = () => campi.value.push({ tipo: 'TEXT', etichetta: '', obbligatorio: false })
const salvaCampo = async (c) => {
  if (!c.etichetta) return
  if (c.id) {
    await campiFormApi.update(enteId, eventoId, c.id, c)
  } else {
    const res = await campiFormApi.store(enteId, eventoId, c)
    c.id = res.data.id
  }
}
const eliminaCampo = async (c, i) => {
  if (c.id) await campiFormApi.destroy(enteId, eventoId, c.id)
  campi.value.splice(i, 1)
}

onMounted(caricaDati)
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.tabs { display: flex; gap: .5rem; margin-bottom: 1rem; }
.tab-btn { padding: .5rem 1.2rem; border: 2px solid #ddd; border-radius: 6px; background: white; cursor: pointer; font-size: .9rem; }
.tab-btn.active { border-color: #3498db; color: #3498db; font-weight: 600; }
.tab-btn:disabled { opacity: .4; cursor: not-allowed; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
.form-group { margin-bottom: .75rem; }
.form-group label { display: block; margin-bottom: .3rem; font-weight: 500; font-size: .9rem; }
.input { width: 100%; padding: .45rem .75rem; border: 1px solid #ddd; border-radius: 6px; font-size: .9rem; box-sizing: border-box; }
.checkbox-label { display: flex; align-items: center; gap: .5rem; cursor: pointer; }
.form-actions { margin-top: 1.5rem; }
.alert-error { background: #fadbd8; color: #922b21; border-radius: 6px; padding: .75rem 1rem; margin-bottom: 1rem; }
.tags-flex { display: flex; flex-wrap: wrap; gap: .5rem; }
.tag-check { display: flex; align-items: center; gap: .3rem; cursor: pointer; }
.tag-badge { padding: .2rem .7rem; border-radius: 12px; color: white; font-size: .8rem; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.tipologia-row, .campo-row { border: 1px solid #eee; border-radius: 8px; padding: .75rem; margin-bottom: .75rem; }
.empty { padding: 2rem; text-align: center; color: #aaa; }
.btn-sm { padding: .3rem .65rem; font-size: .82rem; }
.btn-secondary { background: #ecf0f1; color: #2c3e50; border: none; border-radius: 6px; padding: .45rem 1rem; cursor: pointer; text-decoration: none; }
</style>
