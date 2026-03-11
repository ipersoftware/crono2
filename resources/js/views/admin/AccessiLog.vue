<template>
  <div>
    <div class="page-header">
      <h1>🔐 Log accessi</h1>
    </div>

    <!-- Filtri -->
    <div class="card filtri">
      <div class="filtri-row">
        <input v-model="filtri.dal" type="date" class="input" title="Dal" />
        <input v-model="filtri.al"  type="date" class="input" title="Al" />
        <select v-model="filtri.esito" class="input">
          <option value="">Tutti gli esiti</option>
          <option value="ok">✅ OK</option>
          <option value="account_disabilitato">🚫 Account disabilitato</option>
        </select>
        <button @click="carica(1)" class="btn btn-primary btn-sm">Filtra</button>
        <button @click="resetFiltri" class="btn btn-secondary btn-sm">Reset</button>
      </div>
    </div>

    <div class="card">
      <div v-if="loading" class="loading">Caricamento…</div>
      <div v-else-if="!log.length" class="empty">Nessun accesso registrato con i filtri selezionati.</div>
      <template v-else>
        <table class="table">
          <thead>
            <tr>
              <th>Data / Ora</th>
              <th>Utente</th>
              <th>Ruolo</th>
              <th>IP</th>
              <th>Browser / App</th>
              <th>Esito</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in log" :key="r.id" :class="{ 'row-error': r.esito !== 'ok' }">
              <td data-label="Data / Ora" class="nowrap">{{ formatDataOra(r.created_at) }}</td>
              <td data-label="Utente">
                <div class="user-name">{{ r.user?.cognome }} {{ r.user?.nome }}</div>
                <div class="muted">{{ r.user?.email }}</div>
              </td>
              <td data-label="Ruolo"><span class="role-badge">{{ r.role }}</span></td>
              <td data-label="IP" class="mono">{{ r.ip ?? '—' }}</td>
              <td data-label="Browser" class="ua">{{ parseUA(r.user_agent) }}</td>
              <td data-label="Esito">
                <span :class="['esito-badge', r.esito === 'ok' ? 'ok' : 'errore']">
                  {{ r.esito === 'ok' ? '✅ OK' : '🚫 Disabilitato' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Paginazione -->
        <div v-if="meta.last_page > 1" class="paginazione">
          <button
            v-for="p in meta.last_page" :key="p"
            :class="['btn btn-sm', p === meta.current_page ? 'btn-primary' : 'btn-secondary']"
            @click="carica(p)"
          >{{ p }}</button>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import api from '@/api'
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'

const route  = useRoute()
const enteId = route.params.enteId

const log     = ref([])
const loading = ref(false)
const meta    = ref({ current_page: 1, last_page: 1 })

const oggi = new Date().toISOString().slice(0, 10)
const treggiFa = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10)

const filtri = reactive({
  dal:   treggiFa,
  al:    oggi,
  esito: '',
})

const carica = async (page = 1) => {
  loading.value = true
  try {
    const params = { per_page: 50, page, ...filtri }
    if (!params.dal)   delete params.dal
    if (!params.al)    delete params.al
    if (!params.esito) delete params.esito

    const { data } = await api.get(`/enti/${enteId}/accessi-log`, { params })
    log.value  = data.data
    meta.value = { current_page: data.current_page, last_page: data.last_page }
  } catch (e) {
    console.error('Errore caricamento log accessi', e)
  } finally {
    loading.value = false
  }
}

const resetFiltri = () => {
  filtri.dal   = treggiFa
  filtri.al    = oggi
  filtri.esito = ''
  carica(1)
}

const formatDataOra = (d) => d
  ? new Date(d).toLocaleString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' })
  : '—'

const parseUA = (ua) => {
  if (!ua) return '—'
  // Estrae browser e OS in modo semplice
  const browser =
    ua.includes('Chrome')  ? 'Chrome' :
    ua.includes('Firefox') ? 'Firefox' :
    ua.includes('Safari')  ? 'Safari' :
    ua.includes('Edge')    ? 'Edge' :
    ua.includes('curl')    ? 'curl' : 'Altro'
  const os =
    ua.includes('Windows') ? 'Windows' :
    ua.includes('Mac')     ? 'macOS' :
    ua.includes('Linux')   ? 'Linux' :
    ua.includes('Android') ? 'Android' :
    ua.includes('iPhone') || ua.includes('iPad') ? 'iOS' : ''
  return os ? `${browser} / ${os}` : browser
}

onMounted(() => carica())
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.filtri { margin-bottom: 1rem; }
.filtri-row { display: flex; gap: .75rem; flex-wrap: wrap; align-items: center; }
.input { padding: .45rem .75rem; border: 1px solid #ddd; border-radius: 6px; font-size: .9rem; }
.loading, .empty { padding: 2rem; text-align: center; color: #888; }

.table { width: 100%; border-collapse: collapse; font-size: .875rem; }
.table th { text-align: left; border-bottom: 2px solid #e0e0e0; padding: .5rem .7rem; color: #555; font-weight: 600; }
.table td { padding: .5rem .7rem; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
.table tr:last-child td { border-bottom: none; }
.row-error td { background: #fff5f5; }

.user-name { font-weight: 500; }
.muted   { font-size: .78rem; color: #999; }
.mono    { font-family: monospace; font-size: .82rem; }
.nowrap  { white-space: nowrap; }
.ua      { max-width: 200px; font-size: .82rem; color: #666; }

.role-badge {
  background: #eef2ff;
  color: #3730a3;
  border-radius: 4px;
  padding: .15rem .5rem;
  font-size: .78rem;
  font-weight: 600;
}

.esito-badge {
  border-radius: 12px;
  padding: .2rem .6rem;
  font-size: .8rem;
  font-weight: 600;
}
.esito-badge.ok     { background: #d5f5e3; color: #1a7a45; }
.esito-badge.errore { background: #fadbd8; color: #a93226; }

.paginazione { display: flex; gap: .4rem; flex-wrap: wrap; margin-top: 1rem; justify-content: center; }
</style>
