<template>
  <div class="lp">

    <!-- ──────────────────────────── NAVBAR ──────────────────────────── -->
    <header class="lp-nav" :class="{ 'lp-nav--scrolled': scrolled }">
      <div class="lp-nav-inner">
        <a href="#hero" class="lp-nav-brand" @click.prevent="scrollTo('hero')">
          <span class="lp-nav-brand-icon">🗓</span> Crono
        </a>
        <nav class="lp-nav-links">
          <a href="#funzionalita" @click.prevent="scrollTo('funzionalita')">Funzionalità</a>
          <a href="#prezzi"       @click.prevent="scrollTo('prezzi')">Prezzi</a>
          <a href="#contatti"     @click.prevent="scrollTo('contatti')">Contatti</a>
        </nav>
        <div class="lp-nav-actions">
          <template v-if="isAuthenticated">
            <router-link to="/dashboard" class="lp-btn lp-btn--primary">Vai alla dashboard →</router-link>
          </template>
          <template v-else>
            <router-link to="/login" class="lp-btn lp-btn--ghost">Accedi</router-link>
            <router-link to="/register" class="lp-btn lp-btn--primary">Registrati</router-link>
          </template>
        </div>
      </div>
    </header>

    <!-- ──────────────────────────── HERO ──────────────────────────── -->
    <section id="hero" class="lp-hero">
      <div class="lp-hero-bg"></div>
      <div class="lp-container lp-hero-content">
        <div class="lp-badge">Piattaforma eventi &amp; prenotazioni</div>
        <h1 class="lp-hero-title">Gestisci eventi e<br>prenotazioni online<br><span class="lp-hero-accent">in modo semplice</span></h1>
        <p class="lp-hero-sub">Crono è la piattaforma completa per associazioni, enti e organizzatori. Crea eventi, gestisci sessioni, accetta prenotazioni e comunica automaticamente con il tuo pubblico.</p>
        <div class="lp-hero-cta">
          <router-link to="/register" class="lp-btn lp-btn--primary lp-btn--lg">Inizia gratis →</router-link>
          <a href="#funzionalita" class="lp-btn lp-btn--outline lp-btn--lg" @click.prevent="scrollTo('funzionalita')">Scopri le funzionalità</a>
        </div>
        <div class="lp-hero-stats">
          <div class="lp-stat"><span class="lp-stat-num">∞</span><span class="lp-stat-label">Prenotazioni</span></div>
          <div class="lp-stat-sep"></div>
          <div class="lp-stat"><span class="lp-stat-num">Multi</span><span class="lp-stat-label">Ente</span></div>
          <div class="lp-stat-sep"></div>
          <div class="lp-stat"><span class="lp-stat-num">100%</span><span class="lp-stat-label">Web</span></div>
        </div>
      </div>
    </section>

    <!-- ──────────────────────────── FUNZIONALITÀ ──────────────────────────── -->
    <section id="funzionalita" class="lp-section lp-section--light">
      <div class="lp-container">
        <div class="lp-section-header">
          <h2 class="lp-section-title">Tutto quello che ti serve</h2>
          <p class="lp-section-sub">Una piattaforma completa per ogni fase del processo: dalla creazione dell'evento alla conferma della prenotazione.</p>
        </div>
        <div class="lp-features-grid">
          <div class="lp-feature-card" v-for="f in features" :key="f.title">
            <div class="lp-feature-icon">{{ f.icon }}</div>
            <h3 class="lp-feature-title">{{ f.title }}</h3>
            <p class="lp-feature-desc">{{ f.desc }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ──────────────────────────── PREZZI ──────────────────────────── -->
    <section id="prezzi" class="lp-section">
      <div class="lp-container">
        <div class="lp-section-header">
          <h2 class="lp-section-title">Piani semplici e trasparenti</h2>
          <p class="lp-section-sub">Scegli il piano più adatto alla tua realtà. Senza costi nascosti.</p>
        </div>
        <div class="lp-pricing-grid">
          <div
            class="lp-price-card"
            :class="{ 'lp-price-card--featured': plan.featured }"
            v-for="plan in plans"
            :key="plan.name"
          >
            <div v-if="plan.featured" class="lp-price-badge">Più scelto</div>
            <div class="lp-price-name">{{ plan.name }}</div>
            <div class="lp-price-amount">
              <span class="lp-price-currency">€</span>
              <span class="lp-price-num">{{ plan.price }}</span>
              <span class="lp-price-period">/mese</span>
            </div>
            <p class="lp-price-desc">{{ plan.desc }}</p>
            <ul class="lp-price-features">
              <li v-for="feat in plan.features" :key="feat">
                <span class="lp-check">✓</span> {{ feat }}
              </li>
            </ul>
            <router-link to="/register" class="lp-btn lp-btn--lg" :class="plan.featured ? 'lp-btn--primary' : 'lp-btn--outline'">
              Inizia ora
            </router-link>
          </div>
        </div>
        <p class="lp-pricing-note">Tutti i piani includono 30 giorni di prova gratuita. Nessuna carta di credito richiesta.<br>Per esigenze particolari <a href="#contatti" @click.prevent="scrollTo('contatti')">contattaci</a>.</p>
      </div>
    </section>

    <!-- ──────────────────────────── CONTATTI ──────────────────────────── -->
    <section id="contatti" class="lp-section lp-section--light">
      <div class="lp-container lp-contact-wrap">
        <div class="lp-contact-info">
          <h2 class="lp-section-title" style="text-align:left">Parliamoci</h2>
          <p class="lp-section-sub" style="text-align:left;max-width:none">Hai domande sul piano più adatto, vuoi una demo o hai bisogno di un'offerta personalizzata? Scrivici, ti rispondiamo in poche ore.</p>
          <div class="lp-contact-items">
            <div class="lp-contact-item">
              <span class="lp-contact-icon">📧</span>
              <span>info@ipersoftware.it</span>
            </div>
            <div class="lp-contact-item">
              <span class="lp-contact-icon">🌐</span>
              <span>crono.ipersoftware.it</span>
            </div>
          </div>
        </div>
        <form class="lp-contact-form" @submit.prevent="inviaContatto">
          <div class="lp-form-row">
            <div class="lp-form-group">
              <label>Nome *</label>
              <input v-model="form.nome" type="text" class="lp-input" required placeholder="Mario Rossi" />
            </div>
            <div class="lp-form-group">
              <label>Email *</label>
              <input v-model="form.email" type="email" class="lp-input" required placeholder="mario@esempio.it" />
            </div>
          </div>
          <div class="lp-form-group">
            <label>Telefono</label>
            <input v-model="form.telefono" type="tel" class="lp-input" placeholder="+39 333 000 0000" />
          </div>
          <div class="lp-form-group">
            <label>Messaggio *</label>
            <textarea v-model="form.messaggio" class="lp-input lp-textarea" rows="5" required placeholder="Descrivi il tuo utilizzo o fai le tue domande…"></textarea>
          </div>
          <div v-if="formError" class="lp-alert lp-alert--error">{{ formError }}</div>
          <div v-if="formSuccess" class="lp-alert lp-alert--success">{{ formSuccess }}</div>
          <button type="submit" class="lp-btn lp-btn--primary lp-btn--lg lp-btn--full" :disabled="sending">
            {{ sending ? 'Invio in corso…' : 'Invia messaggio →' }}
          </button>
        </form>
      </div>
    </section>

    <!-- ──────────────────────────── FOOTER ──────────────────────────── -->
    <footer class="lp-footer">
      <div class="lp-container lp-footer-inner">
        <span class="lp-footer-brand">🗓 Crono</span>
        <span class="lp-footer-copy">© {{ new Date().getFullYear() }} Crono — Piattaforma eventi e prenotazioni</span>
        <div class="lp-footer-links">
          <router-link to="/login">Accedi</router-link>
          <router-link to="/register">Registrati</router-link>
        </div>
      </div>
    </footer>

  </div>
</template>

<script setup>
import { useAuthStore } from '@/stores/auth'
import axios from 'axios'
import { computed, onMounted, onUnmounted, ref } from 'vue'

const authStore = useAuthStore()
const isAuthenticated = computed(() => authStore.isAuthenticated)

const scrolled = ref(false)
const sending  = ref(false)
const formError   = ref('')
const formSuccess = ref('')

const form = ref({ nome: '', email: '', telefono: '', messaggio: '' })

const features = [
  { icon: '📋', title: 'Gestione eventi',        desc: 'Crea eventi con titolo, descrizione, categoria e personalizzazione grafica. Duplica eventi ricorrenti con le serie.' },
  { icon: '📅', title: 'Sessioni multiple',       desc: 'Ogni evento può avere più sessioni con orari, sedi e capacità distinte. Gestisci i posti per tipologia.' },
  { icon: '🎟', title: 'Prenotazioni online',     desc: 'Il pubblico prenota in pochi click dalla vetrina pubblica. Nessun account necessario per prenotare.' },
  { icon: '🛍', title: 'Vetrina personalizzabile', desc: 'Ogni ente ha la propria pagina pubblica con logo, colori, immagini e descrizione. Condivisibile via link.' },
  { icon: '📧', title: 'Notifiche automatiche',   desc: 'Email di conferma, promemoria e notifiche allo staff inviate automaticamente ad ogni evento.' },
  { icon: '⏳', title: 'Lista d\'attesa',          desc: 'Quando i posti sono esauriti, gli utenti possono iscriversi alla lista d\'attesa e ricevere notifica automatica.' },
  { icon: '📊', title: 'Monitoraggio live',       desc: 'Visualizza in tempo reale i posti disponibili, le prenotazioni attive e i lock temporanei per sessione.' },
  { icon: '🔗', title: 'Integrazione Keycloak',   desc: 'Autenticazione enterprise con Keycloak. Login SSO, gestione ruoli avanzata e sincronizzazione utenti.' },
  { icon: '🏢', title: 'Multi-ente',              desc: 'Ogni organizzazione ha il proprio spazio isolato. Un\'unica installazione, più enti indipendenti.' },
]

const plans = [
  {
    name: 'Base',
    price: '0',
    featured: false,
    desc: 'Per chi vuole iniziare senza impegno.',
    features: ['1 ente', 'Fino a 1 evento attivo', 'Prenotazioni illimitate', 'Vetrina pubblica', 'Notifiche email', 'Supporto community'],
  },
  {
    name: 'Professional',
    price: '29',
    featured: true,
    desc: 'Per associazioni e organizzatori che crescono.',
    features: ['1 ente', 'Fino a 3 eventi attivi', 'Tipologie di posto', 'Lista d\'attesa', 'Serie ricorrenti', 'Monitoraggio live', 'Integrazione Keycloak', 'Supporto prioritario'],
  },
  {
    name: 'Enterprise',
    price: '—',
    featured: false,
    desc: 'Per reti di enti e realtà complesse.',
    features: ['Multi-ente con eventi illimitati', 'Tutto di Professional', 'SSO avanzato', 'SLA garantito', 'Onboarding dedicato', 'Prezzi personalizzati'],
  },
]

const scrollTo = (id) => {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' })
}

const onScroll = () => { scrolled.value = window.scrollY > 40 }

onMounted(() => window.addEventListener('scroll', onScroll))
onUnmounted(() => window.removeEventListener('scroll', onScroll))

const inviaContatto = async () => {
  sending.value  = true
  formError.value   = ''
  formSuccess.value = ''
  try {
    await axios.post('/api/contatto-piattaforma', form.value)
    formSuccess.value = 'Messaggio inviato! Ti risponderemo al più presto.'
    form.value = { nome: '', email: '', telefono: '', messaggio: '' }
  } catch (err) {
    const errors = err.response?.data?.errors
    if (errors) {
      formError.value = Object.values(errors).flat().join('. ')
    } else {
      formError.value = err.response?.data?.message ?? 'Errore durante l\'invio. Riprova più tardi.'
    }
  } finally {
    sending.value = false
  }
}
</script>

<style scoped>
/* ── Base ──────────────────────────────────────────────────────────────── */
.lp { font-family: 'Inter', system-ui, sans-serif; color: #1a1a2e; background: #fff; }
.lp-container { max-width: 1140px; margin: 0 auto; padding: 0 1.5rem; }

/* ── Navbar ────────────────────────────────────────────────────────────── */
.lp-nav { position: fixed; top: 0; left: 0; right: 0; z-index: 100; padding: .9rem 0; transition: background .25s, box-shadow .25s; }
.lp-nav--scrolled { background: rgba(255,255,255,.97); box-shadow: 0 2px 12px rgba(0,0,0,.08); }
.lp-nav-inner { max-width: 1140px; margin: 0 auto; padding: 0 1.5rem; display: flex; align-items: center; gap: 2rem; }
.lp-nav-brand { display: flex; align-items: center; gap: .4rem; font-size: 1.25rem; font-weight: 800; color: #fff; text-decoration: none; }
.lp-nav--scrolled .lp-nav-brand { color: #4a1fa8; }
.lp-nav-brand-icon { font-size: 1.4rem; }
.lp-nav-links { display: flex; gap: 1.75rem; margin-left: auto; }
.lp-nav-links a { text-decoration: none; font-size: .92rem; font-weight: 500; color: rgba(255,255,255,.85); cursor: pointer; transition: color .2s; }
.lp-nav-links a:hover { color: #fff; }
.lp-nav--scrolled .lp-nav-links a { color: #555; }
.lp-nav--scrolled .lp-nav-links a:hover { color: #4a1fa8; }
.lp-nav-actions { display: flex; gap: .75rem; margin-left: 1.5rem; }

/* ── Buttons ───────────────────────────────────────────────────────────── */
.lp-btn { display: inline-flex; align-items: center; justify-content: center; padding: .5rem 1.2rem; border-radius: 8px; font-size: .9rem; font-weight: 600; text-decoration: none; cursor: pointer; border: none; transition: all .2s; white-space: nowrap; }
.lp-btn--lg { padding: .75rem 1.75rem; font-size: 1rem; border-radius: 10px; }
.lp-btn--full { width: 100%; }
.lp-btn--primary { background: #4a1fa8; color: #fff; }
.lp-btn--primary:hover:not(:disabled) { background: #3a189a; transform: translateY(-1px); box-shadow: 0 4px 16px rgba(74,31,168,.35); }
.lp-btn--primary:disabled { opacity: .6; cursor: not-allowed; }
.lp-btn--ghost { background: rgba(255,255,255,.15); color: #fff; border: 1.5px solid rgba(255,255,255,.4); }
.lp-btn--ghost:hover { background: rgba(255,255,255,.25); }
.lp-nav--scrolled .lp-btn--ghost { background: transparent; color: #4a1fa8; border-color: #4a1fa8; }
.lp-nav--scrolled .lp-btn--ghost:hover { background: #f0eaf9; }
.lp-btn--outline { background: transparent; color: #4a1fa8; border: 2px solid #4a1fa8; }
.lp-btn--outline:hover { background: #4a1fa8; color: #fff; }

/* ── Hero ──────────────────────────────────────────────────────────────── */
.lp-hero { min-height: 100vh; display: flex; align-items: center; position: relative; overflow: hidden; }
.lp-hero-bg { position: absolute; inset: 0; background: linear-gradient(135deg, #2d0e6e 0%, #4a1fa8 45%, #3a8ef6 100%); }
.lp-hero-bg::after { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse at 70% 50%, rgba(255,255,255,.06) 0%, transparent 70%); }
.lp-hero-content { position: relative; z-index: 1; padding: 8rem 1.5rem 5rem; max-width: 700px; }
.lp-badge { display: inline-block; background: rgba(255,255,255,.15); color: rgba(255,255,255,.9); padding: .3rem .9rem; border-radius: 20px; font-size: .8rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; margin-bottom: 1.5rem; }
.lp-hero-title { font-size: clamp(2.2rem, 5vw, 3.5rem); font-weight: 800; color: #fff; line-height: 1.15; margin: 0 0 1.25rem; }
.lp-hero-accent { color: #a78bfa; }
.lp-hero-sub { font-size: 1.1rem; color: rgba(255,255,255,.82); line-height: 1.7; margin-bottom: 2rem; max-width: 560px; }
.lp-hero-cta { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 3rem; }
.lp-hero-cta .lp-btn--outline { color: #fff; border-color: rgba(255,255,255,.5); }
.lp-hero-cta .lp-btn--outline:hover { background: rgba(255,255,255,.15); color: #fff; }
.lp-hero-stats { display: flex; align-items: center; gap: 1.5rem; }
.lp-stat { display: flex; flex-direction: column; align-items: center; }
.lp-stat-num { font-size: 1.5rem; font-weight: 800; color: #fff; }
.lp-stat-label { font-size: .75rem; color: rgba(255,255,255,.65); text-transform: uppercase; letter-spacing: .06em; }
.lp-stat-sep { width: 1px; height: 36px; background: rgba(255,255,255,.2); }

/* ── Sections ──────────────────────────────────────────────────────────── */
.lp-section { padding: 5rem 0; }
.lp-section--light { background: #f8f7ff; }
.lp-section-header { text-align: center; margin-bottom: 3rem; }
.lp-section-title { font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 800; margin: 0 0 .75rem; color: #1a1a2e; }
.lp-section-sub { font-size: 1rem; color: #666; max-width: 560px; margin: 0 auto; line-height: 1.7; }

/* ── Features ──────────────────────────────────────────────────────────── */
.lp-features-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }
.lp-feature-card { background: #fff; border-radius: 14px; padding: 1.75rem; box-shadow: 0 2px 12px rgba(0,0,0,.06); transition: transform .2s, box-shadow .2s; border: 1px solid #f0edf9; }
.lp-feature-card:hover { transform: translateY(-3px); box-shadow: 0 8px 28px rgba(74,31,168,.1); }
.lp-feature-icon { font-size: 2rem; margin-bottom: .75rem; }
.lp-feature-title { font-size: 1rem; font-weight: 700; margin: 0 0 .5rem; color: #1a1a2e; }
.lp-feature-desc { font-size: .88rem; color: #666; line-height: 1.65; margin: 0; }

/* ── Pricing ───────────────────────────────────────────────────────────── */
.lp-pricing-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; align-items: start; margin-bottom: 2rem; }
.lp-price-card { background: #fff; border-radius: 16px; padding: 2rem; border: 2px solid #ede8f9; position: relative; }
.lp-price-card--featured { border-color: #4a1fa8; box-shadow: 0 8px 40px rgba(74,31,168,.15); }
.lp-price-badge { position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: #4a1fa8; color: #fff; font-size: .75rem; font-weight: 700; padding: .25rem .85rem; border-radius: 20px; white-space: nowrap; }
.lp-price-name { font-size: 1.1rem; font-weight: 700; color: #1a1a2e; margin-bottom: .75rem; }
.lp-price-amount { display: flex; align-items: baseline; gap: .2rem; margin-bottom: .75rem; }
.lp-price-currency { font-size: 1.2rem; font-weight: 700; color: #4a1fa8; }
.lp-price-num { font-size: 3rem; font-weight: 800; color: #1a1a2e; line-height: 1; }
.lp-price-period { font-size: .85rem; color: #888; margin-left: .1rem; }
.lp-price-desc { font-size: .88rem; color: #666; margin: 0 0 1.25rem; line-height: 1.6; }
.lp-price-features { list-style: none; padding: 0; margin: 0 0 1.75rem; display: flex; flex-direction: column; gap: .5rem; }
.lp-price-features li { font-size: .88rem; color: #444; display: flex; align-items: flex-start; gap: .5rem; }
.lp-check { color: #4a1fa8; font-weight: 700; flex-shrink: 0; }
.lp-pricing-note { text-align: center; font-size: .85rem; color: #888; line-height: 1.7; }
.lp-pricing-note a { color: #4a1fa8; }

/* ── Contact ───────────────────────────────────────────────────────────── */
.lp-contact-wrap { display: grid; grid-template-columns: 1fr 1.4fr; gap: 4rem; align-items: start; }
.lp-contact-items { display: flex; flex-direction: column; gap: .9rem; margin-top: 1.75rem; }
.lp-contact-item { display: flex; align-items: center; gap: .75rem; font-size: .95rem; color: #444; }
.lp-contact-icon { font-size: 1.2rem; }
.lp-contact-form { background: #fff; border-radius: 16px; padding: 2rem; box-shadow: 0 4px 24px rgba(74,31,168,.08); border: 1px solid #ede8f9; }
.lp-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.lp-form-group { display: flex; flex-direction: column; gap: .4rem; margin-bottom: 1rem; }
.lp-form-group label { font-size: .85rem; font-weight: 600; color: #333; }
.lp-input { padding: .6rem .85rem; border: 1.5px solid #dde0e6; border-radius: 8px; font-size: .9rem; color: #1a1a2e; background: #fff; outline: none; font-family: inherit; transition: border-color .2s; width: 100%; box-sizing: border-box; }
.lp-input:focus { border-color: #4a1fa8; box-shadow: 0 0 0 3px rgba(74,31,168,.1); }
.lp-textarea { resize: vertical; min-height: 120px; }
.lp-alert { padding: .65rem 1rem; border-radius: 8px; font-size: .88rem; margin-bottom: 1rem; }
.lp-alert--error   { background: #fdf2f2; color: #c0392b; border: 1px solid #f5c6c6; }
.lp-alert--success { background: #f0fdf4; color: #27ae60; border: 1px solid #b7e8c7; }

/* ── Footer ────────────────────────────────────────────────────────────── */
.lp-footer { background: #1a1a2e; padding: 1.5rem 0; }
.lp-footer-inner { display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; }
.lp-footer-brand { font-size: 1.1rem; font-weight: 800; color: #fff; }
.lp-footer-copy { font-size: .82rem; color: rgba(255,255,255,.5); margin-left: auto; }
.lp-footer-links { display: flex; gap: 1.25rem; }
.lp-footer-links a { font-size: .85rem; color: rgba(255,255,255,.6); text-decoration: none; }
.lp-footer-links a:hover { color: #fff; }

/* ── Responsive ────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
  .lp-nav-links { display: none; }
  .lp-contact-wrap { grid-template-columns: 1fr; gap: 2rem; }
  .lp-form-row { grid-template-columns: 1fr; }
  .lp-hero-cta { flex-direction: column; }
  .lp-footer-copy { margin-left: 0; }
}
</style>
