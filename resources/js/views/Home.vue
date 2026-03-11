<template>
  <div>
    <!-- ── Intestazione ─────────────────────────────────────── -->
    <div class="card mb-3">
      <h1>👋 Benvenuto, {{ user?.nome }}</h1>
      <p v-if="user?.ente" class="ente-label">🏢 {{ user.ente.nome }}</p>
    </div>

    <!-- ── Statistiche (solo staff ente) ────────────────────── -->
    <template v-if="enteId">
      <div v-if="loadingStats" class="card text-center py-4 text-muted">
        Caricamento statistiche…
      </div>

      <template v-else-if="stats">
        <!-- Numeri chiave -->
        <div class="stats-grid mb-3">
          <div class="stat-card blue">
            <div class="stat-value">{{ stats.stats.prenotazioni_oggi }}</div>
            <div class="stat-label">Prenotazioni oggi</div>
            <router-link :to="`/admin/${enteId}/prenotazioni`" class="stat-link">Vedi tutte →</router-link>
          </div>
          <div class="stat-card green">
            <div class="stat-value">{{ stats.stats.prenotazioni_settimana }}</div>
            <div class="stat-label">Prenotazioni questa settimana</div>
          </div>
          <div class="stat-card orange">
            <div class="stat-value">{{ stats.stats.annullate_settimana }}</div>
            <div class="stat-label">Cancellazioni questa settimana</div>
          </div>
          <div :class="['stat-card', stats.totale_richieste_non_lette > 0 ? 'red' : 'teal']">
            <div class="stat-value">{{ stats.totale_richieste_non_lette }}</div>
            <div class="stat-label">Richieste contatto non lette</div>
            <router-link v-if="stats.totale_richieste_non_lette > 0" :to="`/admin/${enteId}/richieste-contatto`" class="stat-link">
              Rispondi →
            </router-link>
          </div>
        </div>

        <!-- Ultime prenotazioni e richieste contatto affiancate -->
        <div class="dashboard-row mb-3">

          <!-- Ultime prenotazioni di oggi -->
          <div class="card flex-1">
            <h2>🎟 Ultime prenotazioni di oggi</h2>
            <template v-if="stats.ultime_prenotazioni_oggi.length">
              <table class="dash-table">
                <thead>
                  <tr>
                    <th>Codice</th>
                    <th>Utente</th>
                    <th>Evento</th>
                    <th>Posti</th>
                    <th>Ora</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="p in stats.ultime_prenotazioni_oggi" :key="p.codice">
                    <td><code>{{ p.codice }}</code></td>
                    <td>{{ p.nome }}</td>
                    <td class="text-ellipsis">{{ p.evento }}</td>
                    <td class="text-center">{{ p.posti }}</td>
                    <td class="text-muted">{{ p.created_at }}</td>
                  </tr>
                </tbody>
              </table>
            </template>
            <p v-else class="text-muted italic">Nessuna prenotazione ricevuta oggi.</p>
            <div class="card-footer">
              <router-link :to="`/admin/${enteId}/prenotazioni`">Tutte le prenotazioni →</router-link>
            </div>
          </div>

          <!-- Messaggi non letti -->
          <div class="card flex-1" v-if="stats.totale_richieste_non_lette > 0">
            <h2>✉️ Messaggi non letti</h2>
            <ul class="message-list">
              <li v-for="r in stats.richieste_non_lette" :key="r.id">
                <div class="msg-from">{{ r.nome }} <span class="text-muted">— {{ r.email }}</span></div>
                <div class="msg-text">{{ r.messaggio }}</div>
                <div class="msg-time text-muted">{{ r.created_at }}</div>
              </li>
            </ul>
            <div class="card-footer">
              <router-link :to="`/admin/${enteId}/richieste-contatto`">Leggi tutti i messaggi →</router-link>
            </div>
          </div>
          <div class="card flex-1" v-else>
            <h2>✉️ Messaggi</h2>
            <p class="text-muted italic">Nessun messaggio non letto.</p>
            <div class="card-footer">
              <router-link :to="`/admin/${enteId}/richieste-contatto`">Archivio messaggi →</router-link>
            </div>
          </div>
        </div>

        <!-- Eventi pubblicati quest'anno -->
        <div class="card mb-3">
          <h2>📋 Eventi pubblicati nel {{ stats.anno }}
            <span class="badge">{{ stats.totale_eventi_anno }}</span>
          </h2>
          <template v-if="stats.eventi_pubblicati_anno.length">
            <table class="dash-table">
              <thead>
                <tr>
                  <th>Titolo</th>
                  <th class="text-center">Sessioni</th>
                  <th class="text-center">Prenotazioni</th>
                  <th>Luogo</th>
                  <th>Tag</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="e in stats.eventi_pubblicati_anno" :key="e.id">
                  <td>
                    <router-link :to="`/admin/${enteId}/eventi/${e.id}`">{{ e.titolo }}</router-link>
                  </td>
                  <td class="text-center">{{ e.sessioni_count }}</td>
                  <td class="text-center">{{ e.prenotazioni_count }}</td>
                  <td class="text-muted">{{ e.luogo ?? '—' }}</td>
                  <td>
                    <span v-for="tag in e.tags" :key="tag" class="tag-chip">{{ tag }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-if="stats.totale_eventi_anno > stats.eventi_pubblicati_anno.length" class="text-muted mt-2 small">
              Mostrati {{ stats.eventi_pubblicati_anno.length }} su {{ stats.totale_eventi_anno }}.
              <router-link :to="`/admin/${enteId}/eventi`">Vedi tutti →</router-link>
            </p>
          </template>
          <p v-else class="text-muted italic">Nessun evento pubblicato per il {{ stats.anno }}.</p>
        </div>
      </template>
    </template>

    <!-- ── Menu rapido ───────────────────────────────────────── -->
    <div class="card">
      <h2>Menu rapido</h2>
      <div class="links-grid">

        <!-- Staff ente -->
        <template v-if="enteId">
          <router-link :to="`/admin/${enteId}/eventi`" class="link-card blue">
            <h3>📋 Eventi</h3>
            <p>Gestisci gli eventi del tuo ente</p>
          </router-link>
          <router-link :to="`/admin/${enteId}/prenotazioni`" class="link-card green">
            <h3>🎟 Prenotazioni</h3>
            <p>Visualizza e gestisci le prenotazioni</p>
          </router-link>
          <router-link :to="`/admin/${enteId}/luoghi`" class="link-card orange">
            <h3>📍 Luoghi</h3>
            <p>Gestisci le sedi degli eventi</p>
          </router-link>
          <router-link :to="`/admin/${enteId}/tags`" class="link-card purple">
            <h3>🏷 Tag</h3>
            <p>Categorie e filtri degli eventi</p>
          </router-link>
          <router-link :to="`/admin/${enteId}/richieste-contatto`" class="link-card teal">
            <h3>✉️ Richieste contatto</h3>
            <p>Messaggi ricevuti dalla vetrina</p>
          </router-link>
        </template>

        <!-- Super admin -->
        <template v-if="isAdmin">
          <router-link to="/users" class="link-card gray">
            <h3>👥 Utenti</h3>
            <p>Gestisci gli utenti del sistema</p>
          </router-link>
          <router-link to="/enti" class="link-card gray">
            <h3>🏢 Enti</h3>
            <p>Gestisci gli enti</p>
          </router-link>
        </template>

        <!-- Utente finale -->
        <router-link v-if="isUtente" to="/prenotazioni/mie" class="link-card teal">
          <h3>🗓 Le mie prenotazioni</h3>
          <p>Visualizza le tue prenotazioni</p>
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import api from '@/api'
import { useAuthStore } from '@/stores/auth'
import { computed, onMounted, ref } from 'vue'

const authStore = useAuthStore()
const user = ref(null)
const stats = ref(null)
const loadingStats = ref(false)

const isAdmin  = computed(() => authStore.user?.role === 'admin')
const isUtente = computed(() => authStore.user?.role === 'utente')
const enteId   = computed(() => {
  if (authStore.isImpersonating) return authStore.impersonatingEnte?.id ?? null
  return authStore.user?.ente_id ?? null
})

onMounted(async () => {
  if (!authStore.user) await authStore.fetchUser()
  user.value = authStore.user

  if (enteId.value) {
    loadingStats.value = true
    try {
      const { data } = await api.get(`/enti/${enteId.value}/dashboard`)
      stats.value = data
    } catch (e) {
      console.error('Errore caricamento statistiche dashboard', e)
    } finally {
      loadingStats.value = false
    }
  }
})
</script>

<style scoped>
h1 { margin-bottom: .25rem; }
h2 { margin: 0 0 1rem 0; }

.ente-label { color: #666; margin: 0; }
.mb-3 { margin-bottom: 1rem; }
.mt-2 { margin-top: .5rem; }
.text-muted  { color: #888; }
.text-center { text-align: center; }
.italic { font-style: italic; }
.small  { font-size: .875rem; }

/* Stat cards */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 1rem;
}
.stat-card {
  border-radius: 10px;
  padding: 1.2rem 1.4rem;
  color: white;
}
.stat-value { font-size: 2.2rem; font-weight: 700; line-height: 1; }
.stat-label { font-size: .85rem; opacity: .9; margin: .3rem 0 .5rem; }
.stat-link  { font-size: .8rem; color: rgba(255,255,255,.85); text-decoration: underline; }

/* Dashboard two-column row */
.dashboard-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}
@media (max-width: 768px) { .dashboard-row { grid-template-columns: 1fr; } }
.flex-1 { min-width: 0; }

/* Tables */
.dash-table {
  width: 100%;
  border-collapse: collapse;
  font-size: .9rem;
}
.dash-table th {
  text-align: left;
  border-bottom: 2px solid #e0e0e0;
  padding: .5rem .6rem;
  color: #555;
  font-weight: 600;
}
.dash-table td {
  padding: .45rem .6rem;
  border-bottom: 1px solid #f0f0f0;
  vertical-align: middle;
}
.dash-table tr:last-child td { border-bottom: none; }
.text-ellipsis {
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Messages */
.message-list { list-style: none; padding: 0; margin: 0; }
.message-list li { border-bottom: 1px solid #f0f0f0; padding: .6rem 0; }
.message-list li:last-child { border-bottom: none; }
.msg-from  { font-weight: 600; font-size: .9rem; }
.msg-text  { font-size: .875rem; color: #555; margin: .2rem 0; }
.msg-time  { font-size: .8rem; }

/* Card footer */
.card-footer {
  margin-top: .75rem;
  padding-top: .75rem;
  border-top: 1px solid #eee;
  font-size: .875rem;
}

/* Tag chips */
.tag-chip {
  display: inline-block;
  background: #eee;
  border-radius: 4px;
  padding: .15rem .45rem;
  font-size: .75rem;
  margin-right: .2rem;
  color: #555;
}

/* Badge */
.badge {
  background: #3498db;
  color: white;
  border-radius: 999px;
  padding: .15rem .6rem;
  font-size: .8rem;
  margin-left: .5rem;
  vertical-align: middle;
}

/* Quick-links grid */
.links-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 1rem;
}
.link-card {
  padding: 1.4rem;
  border-radius: 10px;
  text-decoration: none;
  color: white;
  transition: transform .15s, box-shadow .15s;
  display: block;
}
.link-card:hover { transform: translateY(-3px); box-shadow: 0 6px 14px rgba(0,0,0,.15); }
.link-card h3 { margin: 0 0 .4rem 0; }
.link-card p  { margin: 0; opacity: .9; font-size: .875rem; }
.blue   { background: #3498db; }
.green  { background: #27ae60; }
.orange { background: #e67e22; }
.purple { background: #8e44ad; }
.teal   { background: #16a085; }
.gray   { background: #7f8c8d; }
.red    { background: #e74c3c; }
</style>
