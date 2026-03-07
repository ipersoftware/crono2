<template>
  <div>
    <div class="page-header">
      <h1>{{ isNuovo ? '+ Nuovo evento' : (form.titolo ? `✏️ ${form.titolo}` : '✏️ Modifica evento') }}</h1>
      <div style="display:flex;gap:.6rem;align-items:center">
        <a v-if="urlVetrina" :href="urlVetrina" target="_blank" class="btn btn-outline">👁 Vedi in vetrina</a>
        <router-link :to="`/admin/${enteId}/eventi`" class="btn btn-secondary">← Torna agli eventi</router-link>
      </div>
    </div>

    <!-- Tab navigation -->
    <div class="tabs">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        :class="['tab-btn', { active: tabAttivo === tab.key }]"
        @click="cambiaTab(tab.key)"
        :disabled="isNuovo && tab.key !== 'dettagli'"
      >{{ tab.label }}</button>
    </div>

    <!-- TAB: Dettagli base -->
    <div v-if="tabAttivo === 'dettagli'" class="card">
      <div v-if="loading" style="padding:2rem;text-align:center;color:#aaa">Caricamento…</div>
      <form @submit.prevent="salva" :style="loading ? 'opacity:0;pointer-events:none' : ''">
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

        <div class="grid-3">
          <div class="form-group">
            <label>Serie</label>
            <select v-model="form.serie_id" class="input">
              <option :value="null">— nessuna —</option>
              <option v-for="s in serie" :key="s.id" :value="s.id">{{ s.titolo }}</option>
            </select>
          </div>
          <div class="form-group">
            <label>Max posti per prenotazione</label>
            <input v-model.number="form.posti_max_per_prenotazione" type="number" min="1" class="input" />
          </div>
          <div class="form-group">
            <label>Cancellazione consentita (ore prima)</label>
            <input v-model.number="form.cancellazione_consentita_ore" type="number" min="0" class="input" placeholder="0 = non consentita" />
          </div>
        </div>

        <!-- Date visibilità e prenotabilità -->
        <div class="grid-2">
          <div class="form-group">
            <label>Visibile dal</label>
            <input v-model="form.visibile_dal" type="datetime-local" class="input" />
          </div>
          <div class="form-group">
            <label>Visibile al</label>
            <input v-model="form.visibile_al" type="datetime-local" class="input" />
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label>Prenotabile dal</label>
            <input v-model="form.prenotabile_dal" type="datetime-local" class="input" />
          </div>
          <div class="form-group">
            <label>Prenotabile al</label>
            <input v-model="form.prenotabile_al" type="datetime-local" class="input" />
          </div>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="toggle-label">
              <span class="toggle-text">Richiede approvazione operatore</span>
              <span class="toggle-state">{{ form.richiede_approvazione ? 'Sì' : 'No' }}</span>
              <span class="toggle-wrap">
                <input type="checkbox" v-model="form.richiede_approvazione" class="toggle-input" />
                <span class="toggle-slider"></span>
              </span>
            </label>
          </div>
          <div class="form-group">
            <label class="toggle-label">
              <span class="toggle-text">Consenti prenotazioni guest (senza account)</span>
              <span class="toggle-state">{{ form.consenti_prenotazione_guest ? 'Sì' : 'No' }}</span>
              <span class="toggle-wrap">
                <input type="checkbox" v-model="form.consenti_prenotazione_guest" class="toggle-input" />
                <span class="toggle-slider"></span>
              </span>
            </label>
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="toggle-label">
              <span class="toggle-text">Consenti prenotazione di più sessioni</span>
              <span class="toggle-state">{{ form.consenti_multi_sessione ? 'Sì' : 'No' }}</span>
              <span class="toggle-wrap">
                <input type="checkbox" v-model="form.consenti_multi_sessione" class="toggle-input" />
                <span class="toggle-slider"></span>
              </span>
            </label>
          </div>
          <div class="form-group">
            <label class="toggle-label">
              <span class="toggle-text">Mostra disponibilità in vetrina</span>
              <span class="toggle-state">{{ form.mostra_disponibilita ? 'Sì' : 'No' }}</span>
              <span class="toggle-wrap">
                <input type="checkbox" v-model="form.mostra_disponibilita" class="toggle-input" />
                <span class="toggle-slider"></span>
              </span>
            </label>
          </div>
        </div>

        <!-- Tag -->
        <div class="form-group">
          <label>Tag</label>
          <div class="tag-combobox" @focusout="chiudiDropdown" tabindex="-1">
            <div class="tag-selected">
              <span
                v-for="tag in tagsSelezionati" :key="tag.id"
                class="tag-badge-sel"
                :style="{ background: tag.colore || '#3498db' }"
              >
                {{ tag.nome }}
                <button type="button" @click="rimuoviTag(tag)" class="tag-remove">×</button>
              </span>
              <input
                v-model="tagSearch"
                @input="tagDropdownAperto = true"
                @focus="tagDropdownAperto = true"
                @keydown.enter.prevent="selezionaPrimoTag"
                @keydown.backspace="backspaceTag"
                @keydown.escape="tagDropdownAperto = false"
                class="tag-input"
                placeholder="Cerca o crea tag…"
                autocomplete="off"
              />
            </div>
            <ul v-if="tagDropdownAperto && tagsFiltrati.length" class="tag-dropdown">
              <li
                v-for="t in tagsFiltrati" :key="t.id ?? t.nome"
                @mousedown.prevent="aggiungiTag(t)"
                :class="['tag-option', { 'tag-option-new': t._nuovo }]"
              >
                <span v-if="!t._nuovo" class="tag-dot" :style="{ background: t.colore || '#3498db' }"></span>
                <span v-else class="tag-option-icon">＋</span>
                {{ t._nuovo ? `Crea "${t.nome}"` : t.nome }}
              </li>
            </ul>
          </div>
        </div>

        <!-- Link Pubblico -->
        <div v-if="linkPubblico" class="form-group link-pubblico-group">
          <label>Link Pubblico</label>
          <div class="link-pubblico-row">
            <input readonly :value="linkPubblico" class="input link-pubblico-input" @click="$event.target.select()" />
            <button type="button" @click="copiaLink" class="btn btn-sm btn-copy">📋 Copia</button>
          </div>
          <a :href="linkPubblico" target="_blank" class="link-apri">Apri link</a>
        </div>

        <div v-if="errore" class="alert-error">{{ errore }}</div>
        <div v-if="successo" class="alert-success">{{ successo }}</div>

        <div class="form-actions">
          <button type="submit" :disabled="saving" class="btn btn-primary">
            {{ saving ? 'Salvataggio…' : (isNuovo ? 'Crea evento' : 'Salva modifiche') }}
          </button>
        </div>
      </form>
    </div>

    <!-- TAB: Layout (descrizione completa + copertina + colori) -->
    <div v-if="tabAttivo === 'layout'" class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
        <h2 style="margin:0">🖋 Layout evento</h2>
        <div style="display:flex;gap:.6rem">
          <a v-if="urlVetrina" :href="urlVetrina" target="_blank" class="btn btn-outline btn-sm">👁 Anteprima vetrina</a>
          <button @click.prevent="salva" :disabled="saving" class="btn btn-primary btn-sm">
            {{ saving ? 'Salvataggio…' : 'Salva' }}
          </button>
        </div>
      </div>

      <!-- Copertina -->
      <div class="layout-section">
        <h3 class="layout-section-title">🖼 Immagine di copertina</h3>
        <div class="copertina-wrap">
          <div class="copertina-preview" :style="copertinaBg">
            <span v-if="!form.immagine" class="copertina-placeholder">Nessuna immagine</span>
            <button v-if="form.immagine" type="button" @click="eliminaImmagine" :disabled="uploadingImg" class="copertina-rm" title="Rimuovi immagine">✕</button>
          </div>
          <div class="copertina-actions">
            <label class="btn btn-secondary btn-sm" style="cursor:pointer">
              {{ uploadingImg ? 'Caricamento…' : '📁 Scegli immagine' }}
              <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none" :disabled="uploadingImg" @change="onImmagineFile" />
            </label>
            <span style="color:#aaa;font-size:.8rem">JPG, PNG, WEBP, GIF — max 3 MB</span>
            <div v-if="uploadErrImg" class="alert-error" style="margin-top:.4rem">{{ uploadErrImg }}</div>
          </div>
        </div>
      </div>

      <!-- Gradienti -->
      <div class="layout-section">
        <h3 class="layout-section-title">🎨 Colori (gradiente hero)</h3>
        <p style="color:#888;font-size:.82rem;margin:-.25rem 0 .85rem">Usati come sfondo hero quando non c'è immagine di copertina.</p>
        <div class="gradient-row">
          <div class="color-picker-group">
            <label>Colore primario</label>
            <div class="color-picker-wrap">
              <input type="color" v-model="form.colore_primario" class="color-input" />
              <input type="text" v-model="form.colore_primario" class="input color-text" placeholder="#4a1fa8" maxlength="7" />
              <button type="button" class="btn-reset-color" @click="form.colore_primario = ''" title="Ripristina default">✕</button>
            </div>
          </div>
          <div class="color-picker-group">
            <label>Colore secondario</label>
            <div class="color-picker-wrap">
              <input type="color" v-model="form.colore_secondario" class="color-input" />
              <input type="text" v-model="form.colore_secondario" class="input color-text" placeholder="#3a8ef6" maxlength="7" />
              <button type="button" class="btn-reset-color" @click="form.colore_secondario = ''" title="Ripristina default">✕</button>
            </div>
          </div>
          <div class="color-picker-group">
            <label>Anteprima</label>
            <div class="gradient-preview" :style="{ background: gradientPreview }"></div>
          </div>
        </div>
      </div>

      <!-- Descrizione completa -->
      <div class="layout-section">
        <h3 class="layout-section-title">📝 Descrizione completa</h3>
        <p style="color:#888;font-size:.82rem;margin:-.25rem 0 .85rem">Testo mostrato sulla pagina pubblica dell'evento. Puoi usare titoli, elenchi, immagini e link.</p>
        <Editor
          tinymce-script-src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js"
          v-model="form.descrizione"
          :init="tinyInitLayout"
        />
      </div>

      <div v-if="errore" class="alert-error" style="margin-top:1rem">{{ errore }}</div>
      <div v-if="successo" class="alert-success" style="margin-top:1rem">{{ successo }}</div>
    </div>

    <!-- TAB: Tipologie posto -->
    <div v-if="tabAttivo === 'tipologie'" class="card">
      <div class="section-header">
        <h2>Tipologie di posto</h2>
        <button @click="apriDialogTipologia()" class="btn btn-primary btn-sm">+ Aggiungi</button>
      </div>

      <div v-if="tipologie.length === 0" class="empty">Nessuna tipologia. Aggiungine una per gestire i posti.</div>

      <table v-else class="table">
        <thead>
          <tr>
            <th>Nome</th>
            <th>Costo (€)</th>
            <th>Gratuita</th>
            <th>Min posti</th>
            <th>Max posti</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(t, i) in tipologie" :key="t.id ?? i">
            <td data-label="Nome">{{ t.nome }}</td>
            <td data-label="Costo (€)">{{ t.gratuita ? '—' : `€ ${Number(t.costo).toFixed(2)}` }}</td>
            <td data-label="Gratuita">{{ t.gratuita ? '✓' : '' }}</td>
            <td data-label="Min posti">{{ t.min_prenotabili ?? '—' }}</td>
            <td data-label="Max posti">{{ t.max_prenotabili ?? '—' }}</td>
            <td data-label="Azioni" class="actions-cell">
              <button @click="apriDialogTipologia(t, i)" class="btn btn-sm btn-secondary">Modifica</button>
              <button @click="eliminaTipologia(t, i)" class="btn btn-sm btn-danger">Elimina</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- TAB: Campi form -->
    <div v-if="tabAttivo === 'form'" class="card">
      <div class="section-header">
        <h2>Campi del modulo di prenotazione</h2>
        <button @click="apriDialogCampo()" class="btn btn-primary btn-sm">+ Aggiungi campo</button>
      </div>

      <div v-if="campi.length === 0" class="empty">Nessun campo. Il modulo utilizzerà solo i dati base (nome, email).</div>

      <table v-else class="table">
        <thead>
          <tr>
            <th style="width:60px"></th>
            <th>Tipo</th>
            <th>Etichetta</th>
            <th>Obbligatorio</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(c, i) in campi" :key="c.id ?? i">
            <td data-label="Ordine" class="ordine-cell">
              <button @click="spostaCampo(i, -1)" :disabled="i === 0" class="btn-ordine" title="Sposta su">↑</button>
              <button @click="spostaCampo(i, 1)" :disabled="i === campi.length - 1" class="btn-ordine" title="Sposta giù">↓</button>
            </td>
            <td data-label="Tipo"><span class="tipo-badge">{{ c.tipo }}</span></td>
            <td data-label="Etichetta">{{ c.etichetta }}</td>
            <td data-label="Obbligatorio">{{ c.obbligatorio ? '✓' : '' }}</td>
            <td data-label="Azioni" class="actions-cell">
              <button @click="apriDialogCampo(c, i)" class="btn btn-sm btn-secondary">Modifica</button>
              <button @click="eliminaCampo(c, i)" class="btn btn-sm btn-danger">Elimina</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- TAB: Log attività -->
    <div v-if="tabAttivo === 'log'" class="card">
      <h2 style="margin-bottom:1rem">🕑 Log attività</h2>
      <div v-if="logLoading" class="empty">Caricamento log…</div>
      <div v-else-if="logEntries.length === 0" class="empty">Nessuna attività registrata per questo evento.</div>
      <table v-else class="table log-table">
        <thead>
          <tr>
            <th style="width:160px">Data/ora</th>
            <th style="width:140px">Operatore</th>
            <th>Descrizione</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="entry in logEntries" :key="entry.id">
            <td data-label="Data/ora" class="log-ts">{{ formatLogDate(entry.created_at) }}</td>
            <td data-label="Operatore" class="log-user">{{ entry.user ? entry.user.nome + ' ' + entry.user.cognome : 'Sistema' }}</td>
            <td data-label="Descrizione">
              <span :class="['log-badge', logBadgeClass(entry.azione)]">{{ logAzioneLabel(entry.azione) }}</span>
              {{ entry.descrizione }}
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="logMeta.last_page > 1" class="log-pagination">
        <button :disabled="logMeta.current_page <= 1" @click="caricaLog(logMeta.current_page - 1)" class="btn btn-sm">‹ Prec</button>
        <span>Pagina {{ logMeta.current_page }} / {{ logMeta.last_page }}</span>
        <button :disabled="logMeta.current_page >= logMeta.last_page" @click="caricaLog(logMeta.current_page + 1)" class="btn btn-sm">Succ ›</button>
      </div>
    </div>

    <!-- Dialog Tipologia -->
    <div v-if="dialogTipologia.aperto" class="modal-overlay" @click.self="dialogTipologia.aperto = false">
      <div class="modal-dialog">
        <h3>{{ dialogTipologia.indice === null ? 'Nuova tipologia' : 'Modifica tipologia' }}</h3>
        <div class="form-group">
          <label>Nome *</label>
          <input v-model="dialogTipologia.form.nome" class="input" placeholder="es. Ordinario, Ridotto…" />
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label>Costo (€)</label>
            <input v-model.number="dialogTipologia.form.costo" type="number" step="0.01" min="0" class="input" :disabled="dialogTipologia.form.gratuita" />
          </div>
          <div class="form-group" style="display:flex;align-items:center;gap:.5rem;margin-top:1.5rem">
            <input type="checkbox" id="chk-gratuita" v-model="dialogTipologia.form.gratuita" @change="dialogTipologia.form.gratuita && (dialogTipologia.form.costo = 0)" />
            <label for="chk-gratuita" style="margin:0;cursor:pointer">Gratuita</label>
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label>Min prenotabili</label>
            <input v-model.number="dialogTipologia.form.min_prenotabili" type="number" min="1" class="input" placeholder="lascia vuoto = nessun limite" />
          </div>
          <div class="form-group">
            <label>Max prenotabili</label>
            <input v-model.number="dialogTipologia.form.max_prenotabili" type="number" min="1" class="input" placeholder="lascia vuoto = nessun limite" />
          </div>
        </div>
        <div class="form-group">
          <label class="toggle-label">
            <span class="toggle-text">Mostra posti disponibili in prenotazione</span>
            <span class="toggle-state">{{ dialogTipologia.form.visualizza_disponibili ? 'Sì' : 'No' }}</span>
            <span class="toggle-wrap">
              <input type="checkbox" v-model="dialogTipologia.form.visualizza_disponibili" class="toggle-input" />
              <span class="toggle-slider"></span>
            </span>
          </label>
        </div>
        <div v-if="dialogTipologia.errore" class="alert-error">{{ dialogTipologia.errore }}</div>
        <div class="dialog-actions">
          <button @click="dialogTipologia.aperto = false" class="btn">Annulla</button>
          <button @click="salvaTipologia" :disabled="dialogTipologia.saving" class="btn btn-primary">
            {{ dialogTipologia.saving ? 'Salvataggio…' : 'Salva' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Dialog Campo form -->
    <div v-if="dialogCampo.aperto" class="modal-overlay" @click.self="dialogCampo.aperto = false">
      <div class="modal-dialog">
        <h3>{{ dialogCampo.indice === null ? 'Nuovo campo' : 'Modifica campo' }}</h3>
        <div class="form-group">
          <label>Tipo</label>
          <select v-model="dialogCampo.form.tipo" class="input">
            <option v-for="tipo in tipiCampo" :key="tipo" :value="tipo">{{ tipo }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>Etichetta *</label>
          <input v-model="dialogCampo.form.etichetta" class="input" placeholder="es. Data di nascita" />
        </div>
        <div class="form-group" style="display:flex;align-items:center;gap:.5rem">
          <input type="checkbox" id="chk-obbligatorio" v-model="dialogCampo.form.obbligatorio" />
          <label for="chk-obbligatorio" style="margin:0;cursor:pointer">Obbligatorio</label>
        </div>
        <div v-if="dialogCampo.errore" class="alert-error">{{ dialogCampo.errore }}</div>
        <div class="dialog-actions">
          <button @click="dialogCampo.aperto = false" class="btn">Annulla</button>
          <button @click="salvaCampo" :disabled="dialogCampo.saving" class="btn btn-primary">
            {{ dialogCampo.saving ? 'Salvataggio…' : 'Salva' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { serieApi, tagsApi } from '@/api/admin'
import { campiFormApi, eventiApi, tipologiePostoApi } from '@/api/eventi'
import { useEnteStore } from '@/stores/ente'
import Editor from '@tinymce/tinymce-vue'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route  = useRoute()
const router = useRouter()
const enteId  = computed(() => route.params.enteId)
const eventoId = computed(() => route.params.eventoId)
const isNuovo = computed(() => !route.params.eventoId)

const tabs = [
  { key: 'dettagli',  label: '📝 Dettagli'       },
  { key: 'layout',    label: '🖋 Layout'          },
  { key: 'sessioni',  label: '🗓 Sessioni'        },
  { key: 'tipologie', label: '🪑 Tipologie posto' },
  { key: 'form',      label: '📋 Campi form'      },
  { key: 'log',       label: '🕑 Log attività'    },
]

const tabAttivo = ref(route.query.tab || 'dettagli')

const cambiaTab = (key) => {
  if (key === 'sessioni') {
    router.push(`/admin/${enteId.value}/eventi/${eventoId.value}/sessioni`)
    return
  }
  if (key === 'log' && eventoId.value) {
    caricaLog()
  }
  tabAttivo.value = key
  router.replace({ query: { ...route.query, tab: key } })
}

const form = reactive({
  titolo: '', descrizione_breve: '', descrizione: '',
  stato: 'BOZZA', serie_id: null,
  posti_max_per_prenotazione: 1, cancellazione_consentita_ore: 0,
  richiede_approvazione: false, consenti_prenotazione_guest: true,
  consenti_multi_sessione: false, mostra_disponibilita: true,
  visibile_dal: '', visibile_al: '', prenotabile_dal: '', prenotabile_al: '',
  tag_ids: [],
  immagine: '',
  colore_primario: '',
  colore_secondario: '',
})
const serie = ref([])
const tags  = ref([])
const tipologie = ref([])
const campi = ref([])
const saving = ref(false)
const loading = ref(false)
const errore  = ref('')
const successo = ref('')
const eventoSlug = ref('')
const enteShopUrl = ref('')
const uploadingImg = ref(false)
const uploadErrImg = ref('')

const enteStore  = useEnteStore()
const urlVetrina = computed(() => {
  const shop = enteShopUrl.value || enteStore.ente?.shop_url || enteId.value
  const slug = eventoSlug.value
  if (!shop || !slug) return null
  return `/vetrina/${shop}/eventi/${slug}`
})

const linkPubblico = computed(() => {
  if (!urlVetrina.value) return null
  return window.location.origin + urlVetrina.value
})

const copiaLink = () => {
  if (linkPubblico.value) navigator.clipboard.writeText(linkPubblico.value)
}

// --- Layout: copertina + colori ---
const DEFAULT_GRADIENT = 'linear-gradient(135deg,#4a1fa8 0%,#6c63ff 55%,#3a8ef6 100%)'

const gradientPreview = computed(() => {
  const p = form.colore_primario
  const s = form.colore_secondario
  if (p && s) return `linear-gradient(135deg, ${p} 0%, ${s} 100%)`
  if (p)      return `linear-gradient(135deg, ${p} 0%, #3a8ef6 100%)`
  if (s)      return `linear-gradient(135deg, #4a1fa8 0%, ${s} 100%)`
  return DEFAULT_GRADIENT
})

const copertinaBg = computed(() => {
  if (form.immagine) return { backgroundImage: `url(${form.immagine})`, backgroundSize: 'cover', backgroundPosition: 'center' }
  return { background: gradientPreview.value }
})

const onImmagineFile = async (e) => {
  const file = e.target.files?.[0]
  if (!file) return
  uploadingImg.value = true
  uploadErrImg.value = ''
  try {
    const res = await eventiApi.uploadImmagine(enteId.value, eventoId.value, file)
    form.immagine = res.data.immagine
  } catch (err) {
    uploadErrImg.value = err.response?.data?.message ?? 'Errore durante il caricamento.'
  } finally {
    uploadingImg.value = false
    e.target.value = ''
  }
}

const eliminaImmagine = async () => {
  if (!confirm('Rimuovere l\'immagine di copertina?')) return
  uploadingImg.value = true
  uploadErrImg.value = ''
  try {
    await eventiApi.eliminaImmagine(enteId.value, eventoId.value)
    form.immagine = ''
  } catch (err) {
    uploadErrImg.value = err.response?.data?.message ?? 'Errore durante la rimozione.'
  } finally {
    uploadingImg.value = false
  }
}
// --- fine Layout ---

const tinyInit = {
  height: 280,
  menubar: false,
  language: 'it',
  language_url: 'https://cdn.jsdelivr.net/npm/tinymce-i18n@23.7.24/langs7/it.js',
  plugins: 'lists link image code',
  toolbar: 'bold italic underline | bullist numlist | link | removeformat | code',
  branding: false,
  promotion: false,
  statusbar: false,
  content_style: 'body { font-family: system-ui, sans-serif; font-size: 14px; }',
}

const tinyInitLayout = {
  height: 620,
  menubar: true,
  language: 'it',
  language_url: 'https://cdn.jsdelivr.net/npm/tinymce-i18n@23.7.24/langs7/it.js',
  plugins: [
    'lists', 'link', 'image', 'table', 'code', 'fullscreen',
    'searchreplace', 'autolink', 'media', 'charmap', 'anchor',
    'visualblocks', 'wordcount',
  ],
  toolbar: [
    'undo redo | styles | bold italic underline strikethrough',
    'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent',
    'link image media table | blockquote hr charmap | removeformat code fullscreen',
  ].join(' | '),
  toolbar_mode: 'sliding',
  image_advtab: true,
  branding: false,
  promotion: false,
  resize: true,
  statusbar: true,
  content_style: 'body { font-family: system-ui, sans-serif; font-size: 15px; line-height: 1.6; max-width: 860px; margin: 1rem auto; padding: 0 1rem; }',
}

const tipiCampo = ['TEXT','TEXTAREA','SELECT','CHECKBOX','RADIO','DATE','EMAIL','PHONE','NUMBER']

// ── Tag combobox ──────────────────────────────────────────────────────────────
const tagSearch        = ref('')
const tagDropdownAperto = ref(false)

const tagsSelezionati = computed(() =>
  form.tag_ids.map(id => tags.value.find(t => t.id === id)).filter(Boolean)
)

const tagsFiltrati = computed(() => {
  const q = tagSearch.value.trim().toLowerCase()
  const disponibili = tags.value.filter(
    t => !form.tag_ids.includes(t.id) && (!q || t.nome.toLowerCase().includes(q))
  )
  const esattoEsiste = tags.value.some(t => t.nome.toLowerCase() === q)
  if (q && !esattoEsiste) {
    return [...disponibili, { _nuovo: true, nome: tagSearch.value.trim() }]
  }
  return disponibili
})

const aggiungiTag = async (t) => {
  if (t._nuovo) {
    try {
      const res = await tagsApi.store(enteId.value, { nome: t.nome })
      const nuovo = res.data
      tags.value.push(nuovo)
      form.tag_ids.push(nuovo.id)
    } catch (e) {
      console.error('Errore creazione tag:', e)
    }
  } else {
    if (!form.tag_ids.includes(t.id)) form.tag_ids.push(t.id)
  }
  tagSearch.value = ''
  tagDropdownAperto.value = false
}

const rimuoviTag = (tag) => {
  form.tag_ids = form.tag_ids.filter(id => id !== tag.id)
}

const backspaceTag = () => {
  if (tagSearch.value === '' && form.tag_ids.length) {
    form.tag_ids.pop()
  }
}

const selezionaPrimoTag = () => {
  if (tagsFiltrati.value.length) aggiungiTag(tagsFiltrati.value[0])
}

const chiudiDropdown = (e) => {
  // chiude solo se il focus esce dal componente
  if (!e.currentTarget.contains(e.relatedTarget)) tagDropdownAperto.value = false
}

// Auto-suggerimento: quando viene caricato l'evento (nuovo), propone tag il cui
// nome compare nel titolo
watch(() => [form.titolo, tags.value], ([titolo, tagList]) => {
  if (!isNuovo.value || !titolo || !tagList.length) return
  const tLow = titolo.toLowerCase()
  tagList.forEach(t => {
    if (tLow.includes(t.nome.toLowerCase()) && !form.tag_ids.includes(t.id)) {
      form.tag_ids.push(t.id)
    }
  })
}, { immediate: false })

const caricaDati = async () => {
  errore.value = ''
  loading.value = true
  try {
    // Carica ente (per shop_url), tags e serie in parallelo
    try {
      const [tagsRes, serieRes] = await Promise.all([
        tagsApi.index(enteId.value),
        serieApi.index(enteId.value),
        enteStore.ente?.id !== Number(enteId.value) ? enteStore.fetchEnte(enteId.value) : Promise.resolve(),
      ])
      tags.value  = tagsRes.data.data ?? tagsRes.data
      serie.value = serieRes.data.data ?? serieRes.data
    } catch (e) {
      console.warn('Errore caricamento tags/serie:', e)
    }

    if (!isNuovo.value) {
      const evRes = await eventiApi.show(enteId.value, eventoId.value)
      const ev = evRes.data
      form.titolo                    = ev.titolo ?? ''
      form.descrizione_breve         = ev.descrizione_breve ?? ''
      form.descrizione               = ev.descrizione ?? ''
      form.stato                     = ev.stato ?? 'BOZZA'
      form.serie_id                  = ev.serie_id ?? null
      form.posti_max_per_prenotazione = ev.posti_max_per_prenotazione ?? 1
      form.cancellazione_consentita_ore = ev.cancellazione_consentita_ore ?? 0
      form.richiede_approvazione     = !!ev.richiede_approvazione
      form.consenti_prenotazione_guest = ev.consenti_prenotazione_guest ?? true
      form.consenti_multi_sessione   = !!ev.consenti_multi_sessione
      form.mostra_disponibilita      = ev.mostra_disponibilita ?? true
      form.visibile_dal              = ev.visibile_dal ? ev.visibile_dal.slice(0, 16) : ''
      form.visibile_al               = ev.visibile_al ? ev.visibile_al.slice(0, 16) : ''
      form.prenotabile_dal           = ev.prenotabile_dal ? ev.prenotabile_dal.slice(0, 16) : ''
      form.prenotabile_al            = ev.prenotabile_al ? ev.prenotabile_al.slice(0, 16) : ''
      form.tag_ids                   = ev.tags?.map(t => t.id) ?? []
      eventoSlug.value               = ev.slug ?? ''
      enteShopUrl.value              = ev.ente?.shop_url ?? ev.ente?.slug ?? ''
      form.immagine                  = ev.immagine ?? ''
      form.colore_primario           = ev.colore_primario ?? ''
      form.colore_secondario         = ev.colore_secondario ?? ''

      try {
        const [tipRes, campiRes] = await Promise.all([
          tipologiePostoApi.index(enteId.value, eventoId.value),
          campiFormApi.index(enteId.value, eventoId.value),
        ])
        tipologie.value = tipRes.data
        campi.value     = campiRes.data
      } catch (e) {
        console.warn('Errore caricamento tipologie/campi:', e)
      }
    }
  } catch (e) {
    errore.value = e.response?.data?.message ?? `Errore caricamento evento: ${e.message}`
  } finally {
    loading.value = false
  }
}

const salva = async () => {
  saving.value = true
  errore.value  = ''
  successo.value = ''
  try {
    if (isNuovo.value) {
      await eventiApi.store(enteId.value, form)
      router.push(`/admin/${enteId.value}/eventi`)
    } else {
      await eventiApi.update(enteId.value, eventoId.value, form)
      successo.value = '✓ Modifiche salvate con successo.'
      setTimeout(() => { successo.value = '' }, 3500)
    }
  } catch (e) {
    errore.value = e.response?.data?.message ?? 'Errore durante il salvataggio.'
  } finally {
    saving.value = false
  }
}

// --- Tipologie — dialog ---
const dialogTipologia = reactive({
  aperto: false,
  indice: null,       // null = nuovo, number = modifica
  saving: false,
  errore: '',
  form: { nome: '', costo: 0, gratuita: false, min_prenotabili: null, max_prenotabili: null },
})

const apriDialogTipologia = (t = null, i = null) => {
  dialogTipologia.indice = i
  dialogTipologia.errore = ''
  dialogTipologia.form = t
    ? { id: t.id, nome: t.nome, costo: t.costo ?? 0, gratuita: !!t.gratuita, min_prenotabili: t.min_prenotabili ?? null, max_prenotabili: t.max_prenotabili ?? null, visualizza_disponibili: !!t.visualizza_disponibili }
    : { nome: '', costo: 0, gratuita: false, min_prenotabili: null, max_prenotabili: null, visualizza_disponibili: false }
  dialogTipologia.aperto = true
}

const salvaTipologia = async () => {
  if (!dialogTipologia.form.nome) { dialogTipologia.errore = 'Il nome è obbligatorio.'; return }
  dialogTipologia.saving = true
  dialogTipologia.errore = ''
  try {
    const payload = { ...dialogTipologia.form }
    if (payload.id) {
      const res = await tipologiePostoApi.update(enteId.value, eventoId.value, payload.id, payload)
      tipologie.value[dialogTipologia.indice] = res.data
    } else {
      const res = await tipologiePostoApi.store(enteId.value, eventoId.value, payload)
      tipologie.value.push(res.data)
    }
    dialogTipologia.aperto = false
  } catch (e) {
    dialogTipologia.errore = e.response?.data?.message ?? 'Errore durante il salvataggio.'
  } finally {
    dialogTipologia.saving = false
  }
}

const eliminaTipologia = async (t, i) => {
  if (!confirm(`Eliminare la tipologia "${t.nome}"?`)) return
  if (t.id) await tipologiePostoApi.destroy(enteId.value, eventoId.value, t.id)
  tipologie.value.splice(i, 1)
}

// --- Campi form — dialog ---
const dialogCampo = reactive({
  aperto: false,
  indice: null,
  saving: false,
  errore: '',
  form: { tipo: 'TEXT', etichetta: '', obbligatorio: false },
})

const apriDialogCampo = (c = null, i = null) => {
  dialogCampo.indice = i
  dialogCampo.errore = ''
  dialogCampo.form = c
    ? { id: c.id, tipo: c.tipo, etichetta: c.etichetta, obbligatorio: !!c.obbligatorio }
    : { tipo: 'TEXT', etichetta: '', obbligatorio: false }
  dialogCampo.aperto = true
}

const salvaCampo = async () => {
  if (!dialogCampo.form.etichetta) { dialogCampo.errore = 'L\'etichetta è obbligatoria.'; return }
  dialogCampo.saving = true
  dialogCampo.errore = ''
  try {
    const payload = { ...dialogCampo.form }
    if (payload.id) {
      const res = await campiFormApi.update(enteId.value, eventoId.value, payload.id, payload)
      campi.value[dialogCampo.indice] = res.data
    } else {
      const res = await campiFormApi.store(enteId.value, eventoId.value, payload)
      campi.value.push(res.data)
    }
    dialogCampo.aperto = false
  } catch (e) {
    dialogCampo.errore = e.response?.data?.message ?? 'Errore durante il salvataggio.'
  } finally {
    dialogCampo.saving = false
  }
}

const eliminaCampo = async (c, i) => {
  if (!confirm(`Eliminare il campo "${c.etichetta}"?`)) return
  if (c.id) await campiFormApi.destroy(enteId.value, eventoId.value, c.id)
  campi.value.splice(i, 1)
}

const spostaCampo = async (i, direzione) => {
  const j = i + direzione
  if (j < 0 || j >= campi.value.length) return
  const arr = [...campi.value]
  ;[arr[i], arr[j]] = [arr[j], arr[i]]
  campi.value = arr
  const ordine = arr.map(c => c.id).filter(Boolean)
  if (ordine.length === arr.length) {
    await campiFormApi.riordina(enteId.value, eventoId.value, ordine)
  }
}

onMounted(async () => {
  await caricaDati()
  if (tabAttivo.value === 'log' && eventoId.value) {
    caricaLog()
  }
})

// ─── Log attività ──────────────────────────────────────────────────────────
const logEntries  = ref([])
const logLoading  = ref(false)
const logMeta     = ref({ current_page: 1, last_page: 1 })

const caricaLog = async (page = 1) => {
  if (!eventoId.value) return
  logLoading.value = true
  try {
    const res = await eventiApi.log(enteId.value, eventoId.value, { page, per_page: 50 })
    logEntries.value = res.data.data
    logMeta.value    = { current_page: res.data.current_page, last_page: res.data.last_page }
  } catch (e) {
    console.error('Errore caricamento log:', e)
  } finally {
    logLoading.value = false
  }
}

const formatLogDate = (d) =>
  d ? new Date(d).toLocaleString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : ''

const AZIONE_LABELS = {
  'evento.creato':           'Creazione',
  'evento.modificato':       'Modifica',
  'evento.pubblicato':       'Pubblicazione',
  'evento.sospeso':          'Sospensione',
  'evento.annullato':        'Annullamento',
  'sessione.creata':         'Sessione aggiunta',
  'sessione.modificata':     'Sessione modificata',
  'sessione.eliminata':      'Sessione eliminata',
  'prenotazione.approvata':  'Pren. approvata',
  'prenotazione.annullata':  'Pren. annullata',
  'form.campo_aggiunto':     'Campo aggiunto',
  'form.campo_modificato':   'Campo modificato',
  'form.campo_rimosso':      'Campo rimosso',
  'form.riordinato':         'Campi riordinati',
  'tipologia.creata':        'Tipologia aggiunta',
  'tipologia.modificata':    'Tipologia modificata',
  'tipologia.eliminata':     'Tipologia rimossa',
}
const logAzioneLabel = (azione) => AZIONE_LABELS[azione] ?? azione

const logBadgeClass = (azione) => {
  if (azione.includes('annullat') || azione.includes('eliminat') || azione.includes('sospeso') || azione === 'form.campo_rimosso' || azione === 'tipologia.eliminata') return 'log-badge-danger'
  if (azione.includes('pubblicat') || azione.includes('approvata')) return 'log-badge-success'
  if (azione.includes('creata') || azione.includes('creato') || azione === 'form.campo_aggiunto' || azione === 'tipologia.creata') return 'log-badge-info'
  return 'log-badge-default'
}

// Ricarica dati se l'eventoId cambia senza smontare il componente
watch(() => route.params.eventoId, (newId) => {
  if (newId) caricaDati()
})
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: .75rem; }
.tabs { display: flex; gap: .5rem; margin-bottom: 1rem; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; padding-bottom: 2px; }
.tabs::-webkit-scrollbar { display: none; }
.tab-btn { padding: .5rem 1.2rem; border: 2px solid #ddd; border-radius: 6px; background: white; cursor: pointer; font-size: .9rem; white-space: nowrap; flex-shrink: 0; }
.tab-btn.active { border-color: #3498db; color: #3498db; font-weight: 600; }
.tab-btn:disabled { opacity: .4; cursor: not-allowed; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
.form-group { margin-bottom: .75rem; }
.form-group label { display: block; margin-bottom: .3rem; font-weight: 500; font-size: .9rem; }
.form-group .checkbox-label { display: flex; align-items: center; gap: .5rem; cursor: pointer; font-weight: 500; margin-bottom: 0; }
.checkbox-label { display: flex; align-items: center; gap: .5rem; cursor: pointer; }
/* Toggle switch */
.toggle-label { display: flex; align-items: center; justify-content: space-between; gap: 1rem; cursor: pointer; padding: .6rem .85rem; border: 2px solid #e8e8e8; border-radius: 8px; background: #fafafa; transition: all .2s; }
.toggle-label:hover { background: #f0f4f8; }
.toggle-label:has(.toggle-input:checked) { background: #eafaf1; border-color: #27ae60; }
.toggle-text { font-size: .9rem; font-weight: 500; color: #2c3e50; flex: 1; }
.toggle-state { font-size: .78rem; font-weight: 700; color: #aaa; min-width: 22px; text-align: right; margin-left: .75rem; margin-right: .5rem; }
.toggle-label:has(.toggle-input:checked) .toggle-state { color: #27ae60; }
.toggle-wrap { position: relative; flex-shrink: 0; width: 48px; height: 24px; }
.toggle-input { opacity: 0; width: 0; height: 0; position: absolute; }
.toggle-slider { position: absolute; inset: 0; background: #ccc; border-radius: 24px; transition: background .2s; cursor: pointer; border: 1px solid #bbb; }
.toggle-slider::before { content: ''; position: absolute; width: 18px; height: 18px; left: 2px; top: 2px; background: white; border-radius: 50%; transition: transform .2s; box-shadow: 0 1px 4px rgba(0,0,0,.3); }
.toggle-input:checked + .toggle-slider { background: #27ae60; border-color: #27ae60; }
.toggle-input:checked + .toggle-slider::before { transform: translateX(24px); }
.input { width: 100%; padding: .45rem .75rem; border: 1px solid #ddd; border-radius: 6px; font-size: .9rem; box-sizing: border-box; }
.form-actions { margin-top: 1.5rem; }
.alert-error   { background: #fadbd8; color: #922b21; border-radius: 6px; padding: .75rem 1rem; margin-bottom: 1rem; }
.alert-success { background: #eafaf1; color: #1e8449;  border-radius: 6px; padding: .75rem 1rem; margin-bottom: 1rem; border: 1px solid #a9dfbf; font-weight: 500; }
/* ── Tab Layout: copertina + colori ─────────────────────────────────────── */
.layout-section { margin-bottom: 1.75rem; padding-bottom: 1.5rem; border-bottom: 1px solid #f0f0f0; }
.layout-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
.layout-section-title { font-size: 1rem; font-weight: 700; margin: 0 0 .85rem; color: #2c3e50; }
.copertina-wrap { display: flex; gap: 1.25rem; align-items: flex-start; flex-wrap: wrap; }
.copertina-preview { width: 200px; height: 120px; border-radius: 10px; overflow: hidden; position: relative; border: 2px solid #e8eaed; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.copertina-placeholder { color: #bbb; font-size: .82rem; }
.copertina-rm { position: absolute; top: 6px; right: 6px; border: none; background: rgba(0,0,0,.55); color: white; border-radius: 50%; width: 22px; height: 22px; font-size: .8rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.copertina-rm:hover { background: #c0392b; }
.copertina-actions { display: flex; flex-direction: column; gap: .55rem; justify-content: center; }
.gradient-row { display: flex; gap: 1.5rem; flex-wrap: wrap; align-items: flex-end; }
.color-picker-group { display: flex; flex-direction: column; gap: .35rem; }
.color-picker-group label { font-size: .85rem; font-weight: 500; }
.color-picker-wrap { display: flex; align-items: center; gap: .45rem; }
.color-input { width: 38px; height: 38px; padding: 2px; border: 1px solid #ddd; border-radius: 6px; cursor: pointer; background: white; }
.color-text { width: 100px; font-family: monospace; font-size: .88rem; }
.btn-reset-color { border: none; background: #eee; border-radius: 4px; padding: .2rem .45rem; cursor: pointer; color: #888; font-size: .82rem; }
.btn-reset-color:hover { background: #fadbd8; color: #c0392b; }
.gradient-preview { width: 140px; height: 38px; border-radius: 8px; border: 1px solid #e8eaed; }
/* ── Tag combobox ─────────────────────────────────────────────────────────── */
.tag-combobox { position: relative; border: 1px solid #ddd; border-radius: 6px; background: white; outline: none; }
.tag-combobox:focus-within { border-color: #3498db; box-shadow: 0 0 0 2px rgba(52,152,219,.18); }
.tag-selected { display: flex; flex-wrap: wrap; gap: .35rem; padding: .4rem .55rem; min-height: 38px; align-items: center; }
.tag-badge-sel { display: inline-flex; align-items: center; gap: .25rem; padding: .18rem .55rem .18rem .65rem; border-radius: 12px; color: white; font-size: .8rem; font-weight: 500; }
.tag-remove { background: none; border: none; color: rgba(255,255,255,.85); font-size: 1rem; line-height: 1; cursor: pointer; padding: 0 0 .05rem; }
.tag-remove:hover { color: white; }
.tag-input { border: none; outline: none; font-size: .88rem; min-width: 140px; flex: 1; padding: .1rem .2rem; background: transparent; }
.tag-dropdown { position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: white; border: 1px solid #ddd; border-radius: 6px; box-shadow: 0 4px 16px rgba(0,0,0,.12); z-index: 500; list-style: none; margin: 0; padding: .3rem 0; max-height: 220px; overflow-y: auto; }
.tag-option { display: flex; align-items: center; gap: .5rem; padding: .45rem .85rem; font-size: .88rem; cursor: pointer; }
.tag-option:hover { background: #f4f8fb; }
.tag-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.tag-option-new { color: #2980b9; font-style: italic; }
.tag-option-icon { font-style: normal; font-weight: 700; color: #2980b9; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.empty { padding: 2rem; text-align: center; color: #aaa; }
.btn-sm { padding: .3rem .65rem; font-size: .82rem; }
.btn-secondary { background: #ecf0f1; color: #2c3e50; border: none; border-radius: 6px; padding: .45rem 1rem; cursor: pointer; text-decoration: none; }
.btn-outline { background: white; color: #3498db; border: 1.5px solid #3498db; border-radius: 6px; padding: .4rem 1rem; cursor: pointer; text-decoration: none; font-size: .9rem; }
.btn-outline:hover { background: #eaf4fd; }
.table { width: 100%; border-collapse: collapse; }
.table th, .table td { padding: .6rem .75rem; text-align: left; border-bottom: 1px solid #eee; font-size: .9rem; }
.table th { background: #f8f9fa; font-weight: 600; }
.actions-cell { display: flex; gap: .4rem; flex-wrap: wrap; }

@media (max-width: 768px) {
  .table thead { display: none; }
  .table, .table tbody, .table tr, .table td { display: block; width: 100%; box-sizing: border-box; }
  .table tr { border: 1px solid #ddd; border-radius: 8px; margin-bottom: .9rem; padding: .3rem 0; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.07); }
  .table td { display: flex; justify-content: space-between; align-items: center; padding: .45rem .75rem; border-bottom: 1px solid #f0f0f0; font-size: .88rem; gap: .5rem; }
  .table td:last-child { border-bottom: none; }
  .table td::before { content: attr(data-label); font-weight: 600; color: #555; font-size: .78rem; text-transform: uppercase; letter-spacing: .03em; min-width: 100px; flex-shrink: 0; }
  .actions-cell { justify-content: flex-end; }
  .ordine-cell { justify-content: space-between; }
}
.tipo-badge { background: #e8f4fd; color: #2980b9; padding: .15rem .5rem; border-radius: 4px; font-size: .8rem; font-weight: 600; }
.ordine-cell { white-space: nowrap; }
.btn-ordine { background: none; border: 1px solid #ddd; border-radius: 4px; width: 24px; height: 24px; cursor: pointer; font-size: .85rem; line-height: 1; padding: 0; color: #555; }
.btn-ordine:hover:not(:disabled) { background: #f0f0f0; border-color: #bbb; }
.btn-ordine:disabled { opacity: .3; cursor: default; }
.link-pubblico-group { margin-top: .25rem; }
.link-pubblico-row { display: flex; gap: .5rem; align-items: center; }
.link-pubblico-input { flex: 1; font-size: .82rem; color: #555; cursor: text; }
.btn-copy { background: #ecf0f1; color: #2c3e50; border: 1px solid #ccc; border-radius: 6px; cursor: pointer; white-space: nowrap; flex-shrink: 0; }
.btn-copy:hover { background: #dfe6e9; }
.link-apri { font-size: .82rem; color: #3498db; text-decoration: underline; display: inline-block; margin-top: .3rem; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); display: flex; align-items: center; justify-content: center; z-index: 2000; }
.modal-dialog { background: white; border-radius: 10px; padding: 1.75rem; width: 90%; max-width: 480px; box-shadow: 0 8px 30px rgba(0,0,0,.2); }
.modal-dialog h3 { margin: 0 0 1.25rem; font-size: 1.1rem; }
.dialog-actions { display: flex; justify-content: flex-end; gap: .75rem; margin-top: 1.25rem; }
/* ── Log attività ────────────────────────────────────────────── */
.log-table .log-ts   { white-space: nowrap; color: #777; font-size: .82rem; }
.log-table .log-user { font-size: .85rem; color: #444; }
.log-badge { display: inline-block; padding: .15rem .5rem; border-radius: 4px; font-size: .75rem; font-weight: 700; margin-right: .4rem; text-transform: uppercase; vertical-align: middle; }
.log-badge-danger  { background: #fadbd8; color: #a93226; }
.log-badge-success { background: #d5f5e3; color: #1a7a45; }
.log-badge-info    { background: #d6eaf8; color: #1a5276; }
.log-badge-default { background: #f0f0f0; color: #555; }
.log-pagination { display: flex; align-items: center; gap: 1rem; justify-content: center; margin-top: 1rem; font-size: .9rem; }

@media (max-width: 768px) {
  .log-table thead { display: none; }
  .log-table, .log-table tbody, .log-table tr, .log-table td { display: block; width: 100%; box-sizing: border-box; }
  .log-table tr { border: 1px solid #e8eaed; border-radius: 8px; margin-bottom: .75rem; padding: .25rem 0; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
  .log-table td { display: flex; justify-content: space-between; align-items: flex-start; gap: .5rem; padding: .5rem .75rem; border-bottom: 1px solid #f0f0f0; font-size: .88rem; }
  .log-table td:last-child { border-bottom: none; }
  .log-table td::before { content: attr(data-label); font-weight: 600; color: #777; font-size: .75rem; text-transform: uppercase; white-space: nowrap; min-width: 80px; flex-shrink: 0; padding-top: .1rem; }
  .log-table .log-ts { white-space: normal; }
}
</style>
