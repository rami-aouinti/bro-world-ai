<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { api, ApiError } from './api'
import type { Assignee, BriefResponse, Channel, ContentIdea, HandoffResponse, WeeklyPlan } from './types'

type Page = 'overview' | 'ideas' | 'planning' | 'review'

const page = ref<Page>('overview')
const ideas = ref<ContentIdea[]>([])
const currentPlan = ref<WeeklyPlan | null>(null)
const loading = ref(false)
const saving = ref(false)
const apiOnline = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const searchTerm = ref('')
const statusFilter = ref('all')
const showIdeaForm = ref(false)
const selectedIdea = ref<ContentIdea | null>(null)
const selectedBrief = ref<BriefResponse | null>(null)
const handoffResult = ref<HandoffResponse | null>(null)
const detailLoading = ref(false)

const channels: Channel[] = ['instagram', 'facebook', 'tiktok', 'linkedin']
const channelLabels: Record<Channel, string> = {
  instagram: 'Instagram',
  facebook: 'Facebook',
  tiktok: 'TikTok',
  linkedin: 'LinkedIn',
}

const newIdea = reactive({
  title: '',
  topic: '',
  targetAudience: 'Adults aged 25 to 40',
  tone: 'Friendly and informative',
  channels: ['instagram', 'facebook'] as Channel[],
})

const planWeekStart = ref(mondayOf(new Date()))
const capacityA = ref(90)
const capacityB = ref(150)
const selectedPlanIds = ref<string[]>([])
const slotSettings = reactive<Record<string, { assignee: Assignee; minutes: number }>>({})

const briefForm = reactive({
  mediaReference: 'media://sample/hero-product-image',
  scheduledAt: localDateTime(new Date(Date.now() + 86400000)),
})

const editForm = reactive({
  title: '',
  topic: '',
  targetAudience: '',
  tone: '',
  channels: [] as Channel[],
})

const filteredIdeas = computed(() => {
  const term = searchTerm.value.trim().toLowerCase()
  return ideas.value.filter((idea) => {
    const matchesTerm = !term || [idea.title, idea.topic, idea.targetAudience].some((value) => value.toLowerCase().includes(term))
    const matchesStatus = statusFilter.value === 'all' || idea.status === statusFilter.value
    return matchesTerm && matchesStatus
  })
})

const selectedPlanIdeas = computed(() => selectedPlanIds.value
  .map((id) => ideas.value.find((idea) => idea.id === id))
  .filter((idea): idea is ContentIdea => Boolean(idea)))

const plannedMinutesA = computed(() => selectedPlanIdeas.value.reduce((sum, idea) => {
  const slot = slotSettings[idea.id]
  return sum + (slot?.assignee === 'A' ? Number(slot.minutes || 0) : 0)
}, 0))

const plannedMinutesB = computed(() => selectedPlanIdeas.value.reduce((sum, idea) => {
  const slot = slotSettings[idea.id]
  return sum + (slot?.assignee === 'B' ? Number(slot.minutes || 0) : 0)
}, 0))

const reviewQueue = computed(() => ideas.value.filter((idea) => ['review', 'approved'].includes(idea.status)))
const priorityCount = computed(() => currentPlan.value?.slots.filter((slot) => slot.type === 'priority').length ?? 0)
const reserveCount = computed(() => currentPlan.value?.slots.filter((slot) => slot.type === 'reserve').length ?? 0)
const draftCount = computed(() => ideas.value.filter((idea) => ['draft', 'ready_for_planning'].includes(idea.status)).length)
const handedOffCount = computed(() => ideas.value.filter((idea) => idea.status === 'handed_off').length)

function mondayOf(date: Date): string {
  const value = new Date(date)
  const day = value.getDay() || 7
  value.setDate(value.getDate() - day + 1)
  return localDate(value)
}

function localDate(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return year + '-' + month + '-' + day
}

function localDateTime(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hour = String(date.getHours()).padStart(2, '0')
  const minute = String(date.getMinutes()).padStart(2, '0')
  return year + '-' + month + '-' + day + 'T' + hour + ':' + minute
}

function formatDate(value?: string): string {
  if (!value) return '—'
  return new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value))
}

function formatDateTime(value?: string): string {
  if (!value) return '—'
  return new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

function statusLabel(status: string): string {
  const labels: Record<string, string> = {
    draft: 'Draft',
    ready_for_planning: 'Planned',
    review: 'In review',
    approved: 'Approved',
    handed_off: 'Handed off',
  }
  return labels[status] || status
}

function statusClass(status: string): string {
  return 'status-' + status
}

function channelLabel(channel: string): string {
  return channelLabels[channel as Channel] || channel
}

function openSlotIdea(id: string): void {
  const idea = ideas.value.find((item) => item.id === id)
  if (idea) void openIdea(idea)
}

function isIdeaAssigned(id: string): boolean {
  return currentPlan.value?.slots.some((slot) => slot.ideaId === id) ?? false
}

function clearMessages(): void {
  errorMessage.value = ''
  successMessage.value = ''
}

function showError(error: unknown): void {
  if (error instanceof ApiError) {
    const warning = error.body.capacityWarnings
    if (warning) {
      const details = Object.entries(warning).map(([person, value]) =>
        'Person ' + person + ': ' + value.plannedMinutes + '/' + value.capacityMinutes + ' min (' + value.overByMinutes + ' min over)',
      ).join(' · ')
      errorMessage.value = (error.message || 'Capacity exceeded') + ' ' + details
      return
    }
    const violations = error.body.violations?.map((item) => item.field + ': ' + item.message).join(' · ')
    errorMessage.value = error.message + (violations ? ' ' + violations : '')
    return
  }
  errorMessage.value = error instanceof Error ? error.message : 'An unexpected error occurred.'
}

async function loadIdeas(): Promise<void> {
  const result = await api.listIdeas()
  ideas.value = result
}

async function loadCurrentPlan(): Promise<void> {
  const id = window.localStorage.getItem('bro-world-current-plan')
  if (!id) {
    currentPlan.value = null
    return
  }
  try {
    currentPlan.value = await api.getWeeklyPlan(id)
    planWeekStart.value = currentPlan.value.weekStart
  } catch {
    currentPlan.value = null
    window.localStorage.removeItem('bro-world-current-plan')
  }
}

async function refresh(): Promise<void> {
  loading.value = true
  clearMessages()
  try {
    await Promise.all([loadIdeas(), loadCurrentPlan()])
    apiOnline.value = true
  } catch (error) {
    apiOnline.value = false
    showError(error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void refresh()
})

function navigate(next: Page): void {
  page.value = next
  clearMessages()
}

function toggleNewChannel(channel: Channel): void {
  newIdea.channels = newIdea.channels.includes(channel)
    ? newIdea.channels.filter((item) => item !== channel)
    : [...newIdea.channels, channel]
}

async function createIdea(): Promise<void> {
  clearMessages()
  if (!newIdea.channels.length) {
    errorMessage.value = 'Choose at least one social channel.'
    return
  }
  saving.value = true
  try {
    const created = await api.createIdea({
      title: newIdea.title,
      topic: newIdea.topic,
      targetAudience: newIdea.targetAudience,
      tone: newIdea.tone,
      channels: [...newIdea.channels],
    })
    ideas.value.unshift(created)
    newIdea.title = ''
    newIdea.topic = ''
    showIdeaForm.value = false
    successMessage.value = 'Content idea created successfully.'
  } catch (error) {
    showError(error)
  } finally {
    saving.value = false
  }
}

function togglePlanIdea(id: string): void {
  const exists = selectedPlanIds.value.includes(id)
  if (exists) {
    selectedPlanIds.value = selectedPlanIds.value.filter((item) => item !== id)
    return
  }
  if (selectedPlanIds.value.length >= 5) {
    errorMessage.value = 'A weekly plan contains exactly five ideas.'
    return
  }
  selectedPlanIds.value.push(id)
  if (!slotSettings[id]) slotSettings[id] = { assignee: selectedPlanIds.value.length % 2 === 1 ? 'A' : 'B', minutes: 30 }
}

async function createPlan(): Promise<void> {
  clearMessages()
  if (selectedPlanIds.value.length !== 5) {
    errorMessage.value = 'Select exactly five ideas: three priorities and two reserves.'
    return
  }
  if (plannedMinutesA.value > capacityA.value || plannedMinutesB.value > capacityB.value) {
    errorMessage.value = 'Capacity exceeded. Reassign work or adjust the estimates before saving.'
    return
  }
  saving.value = true
  try {
    const result = await api.createWeeklyPlan({
      weekStart: planWeekStart.value,
      capacityPersonA: Number(capacityA.value),
      capacityPersonB: Number(capacityB.value),
      slots: selectedPlanIdeas.value.map((idea, index) => ({
        ideaId: idea.id,
        type: index < 3 ? 'priority' : 'reserve',
        assignee: slotSettings[idea.id]?.assignee || 'A',
        estimatedMinutes: Number(slotSettings[idea.id]?.minutes || 30),
      })),
    })
    currentPlan.value = result
    window.localStorage.setItem('bro-world-current-plan', result.id)
    selectedPlanIds.value = []
    await loadIdeas()
    successMessage.value = 'Weekly plan saved. Three priorities and two reserve ideas are now assigned.'
  } catch (error) {
    showError(error)
  } finally {
    saving.value = false
  }
}

async function openIdea(idea: ContentIdea): Promise<void> {
  selectedIdea.value = idea
  selectedBrief.value = null
  handoffResult.value = null
  editForm.title = idea.title
  editForm.topic = idea.topic
  editForm.targetAudience = idea.targetAudience
  editForm.tone = idea.tone
  editForm.channels = [...idea.channels]
  briefForm.mediaReference = 'media://sample/' + idea.id
  detailLoading.value = true
  try {
    selectedBrief.value = await api.getBrief(idea.id)
    if (selectedBrief.value.brief.scheduledAt) {
      const scheduled = new Date(selectedBrief.value.brief.scheduledAt)
      briefForm.scheduledAt = localDateTime(scheduled)
    }
  } catch (error) {
    if (!(error instanceof ApiError) || error.status !== 404) showError(error)
  } finally {
    detailLoading.value = false
  }
}

function closeIdea(): void {
  selectedIdea.value = null
  selectedBrief.value = null
  handoffResult.value = null
}

function toggleEditChannel(channel: Channel): void {
  editForm.channels = editForm.channels.includes(channel)
    ? editForm.channels.filter((item) => item !== channel)
    : [...editForm.channels, channel]
}

async function saveIdeaChanges(): Promise<void> {
  if (!selectedIdea.value) return
  clearMessages()
  saving.value = true
  try {
    await api.editIdea(selectedIdea.value.id, {
      title: editForm.title,
      topic: editForm.topic,
      targetAudience: editForm.targetAudience,
      tone: editForm.tone,
      channels: editForm.channels,
    })
    await loadIdeas()
    selectedIdea.value = ideas.value.find((idea) => idea.id === selectedIdea.value?.id) || null
    selectedBrief.value = null
    successMessage.value = 'Changes saved. Previous brief and approval have been invalidated; generate a new brief.'
  } catch (error) {
    showError(error)
  } finally {
    saving.value = false
  }
}

async function generateBrief(): Promise<void> {
  if (!selectedIdea.value) return
  clearMessages()
  saving.value = true
  try {
    selectedBrief.value = await api.generateBrief(selectedIdea.value.id, {
      mediaReference: briefForm.mediaReference,
      scheduledAt: new Date(briefForm.scheduledAt).toISOString(),
    })
    await loadIdeas()
    selectedIdea.value = ideas.value.find((idea) => idea.id === selectedIdea.value?.id) || selectedIdea.value
    successMessage.value = 'Brief generated. Review the copy and creative direction before submitting it.'
  } catch (error) {
    showError(error)
  } finally {
    saving.value = false
  }
}

async function submitForReview(): Promise<void> {
  if (!selectedIdea.value) return
  clearMessages()
  saving.value = true
  try {
    await api.review(selectedIdea.value.id)
    await loadIdeas()
    selectedIdea.value = ideas.value.find((idea) => idea.id === selectedIdea.value?.id) || selectedIdea.value
    successMessage.value = 'Content submitted for human review.'
  } catch (error) {
    showError(error)
  } finally {
    saving.value = false
  }
}

async function approveIdea(): Promise<void> {
  if (!selectedIdea.value) return
  clearMessages()
  saving.value = true
  try {
    await api.approve(selectedIdea.value.id)
    await loadIdeas()
    selectedIdea.value = ideas.value.find((idea) => idea.id === selectedIdea.value?.id) || selectedIdea.value
    successMessage.value = 'Exact brief version approved. Handoff is now available.'
  } catch (error) {
    showError(error)
  } finally {
    saving.value = false
  }
}

async function handoffIdea(): Promise<void> {
  if (!selectedIdea.value) return
  clearMessages()
  saving.value = true
  try {
    const key = 'bro-world-' + selectedIdea.value.id + '-' + (selectedBrief.value?.versionHash || 'v1')
    handoffResult.value = await api.handoff(selectedIdea.value.id, key)
    await loadIdeas()
    selectedIdea.value = ideas.value.find((idea) => idea.id === selectedIdea.value?.id) || selectedIdea.value
    successMessage.value = handoffResult.value.idempotentReplay
      ? 'Existing simulated handoff returned. No duplicate was created.'
      : 'Simulated handoff persisted successfully. No social network was contacted.'
  } catch (error) {
    showError(error)
  } finally {
    saving.value = false
  }
}

function personProgress(person: 'A' | 'B'): number {
  if (!currentPlan.value) return 0
  const capacity = currentPlan.value.capacity[person] || 1
  const allocated = currentPlan.value.allocatedMinutes[person] || 0
  return Math.min(100, Math.round((allocated / capacity) * 100))
}

function goToPlan(): void {
  navigate('planning')
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
</script>

<template>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-mark">bw</div>
        <div>
          <strong>bro world</strong>
          <span>AI CONTENT STUDIO</span>
        </div>
      </div>

      <div class="workspace-label">WORKSPACE</div>
      <nav class="main-nav" aria-label="Main navigation">
        <button :class="{ active: page === 'overview' }" @click="navigate('overview')"><span class="nav-icon">⌂</span> Overview</button>
        <button :class="{ active: page === 'ideas' }" @click="navigate('ideas')"><span class="nav-icon">✳</span> Content ideas <span class="nav-count">{{ ideas.length }}</span></button>
        <button :class="{ active: page === 'planning' }" @click="navigate('planning')"><span class="nav-icon">▦</span> Weekly plan</button>
        <button :class="{ active: page === 'review' }" @click="navigate('review')"><span class="nav-icon">✓</span> Review queue <span v-if="reviewQueue.length" class="nav-count">{{ reviewQueue.length }}</span></button>
      </nav>

      <div class="sidebar-bottom">
        <div class="connection-card">
          <span class="connection-dot" :class="{ online: apiOnline }"></span>
          <div>
            <strong>{{ apiOnline ? 'API connected' : 'API disconnected' }}</strong>
            <small>Symfony workspace</small>
          </div>
        </div>
        <div class="profile">
          <div class="avatar">BW</div>
          <div><strong>Optical Studio</strong><small>Demo workspace</small></div>
          <span class="profile-menu">···</span>
        </div>
      </div>
    </aside>

    <main class="main-area">
      <header class="topbar">
        <div class="breadcrumb">Workspace <span>/</span> <strong>{{ page === 'overview' ? 'Overview' : page === 'ideas' ? 'Content ideas' : page === 'planning' ? 'Weekly plan' : 'Review queue' }}</strong></div>
        <div class="topbar-actions">
          <span class="today-label">{{ new Intl.DateTimeFormat('en-GB', { weekday: 'short', day: '2-digit', month: 'short' }).format(new Date()) }}</span>
          <button class="icon-button" title="Refresh data" :disabled="loading" @click="refresh">↻</button>
          <button class="primary-button small" @click="showIdeaForm = true">＋ New idea</button>
        </div>
      </header>

      <div class="page-content">
        <div v-if="errorMessage" class="alert alert-error"><span>!</span><div>{{ errorMessage }}</div><button @click="errorMessage = ''">×</button></div>
        <div v-if="successMessage" class="alert alert-success"><span>✓</span><div>{{ successMessage }}</div><button @click="successMessage = ''">×</button></div>

        <section v-if="page === 'overview'" class="page-section">
          <div class="page-heading">
            <div><div class="eyebrow">YOUR CONTENT WORKSPACE</div><h1>Good work starts<br /><em>with a clear plan.</em></h1><p>Plan, create and approve your social content — all in one place.</p></div>
            <button class="primary-button" @click="goToPlan">Open weekly plan <span>↗</span></button>
          </div>

          <div class="metric-grid">
            <article class="metric-card"><div class="metric-top"><span>Content ideas</span><span class="metric-symbol mint">✳</span></div><strong>{{ ideas.length.toString().padStart(2, '0') }}</strong><small>{{ draftCount }} waiting to be planned</small></article>
            <article class="metric-card"><div class="metric-top"><span>Priority this week</span><span class="metric-symbol lavender">↗</span></div><strong>{{ currentPlan ? priorityCount : '—' }}<small class="metric-total">/ 3</small></strong><small>{{ currentPlan ? reserveCount + ' reserve ideas ready' : 'No weekly plan yet' }}</small></article>
            <article class="metric-card"><div class="metric-top"><span>Waiting for review</span><span class="metric-symbol peach">◷</span></div><strong>{{ ideas.filter((idea) => idea.status === 'review').length.toString().padStart(2, '0') }}</strong><small>{{ ideas.filter((idea) => idea.status === 'approved').length }} approved and ready</small></article>
            <article class="metric-card"><div class="metric-top"><span>Handed off</span><span class="metric-symbol blue">↗</span></div><strong>{{ handedOffCount.toString().padStart(2, '0') }}</strong><small>Simulated handoffs only</small></article>
          </div>

          <div class="dashboard-grid">
            <section class="panel plan-panel">
              <div class="panel-heading"><div><div class="eyebrow">WEEKLY OVERVIEW</div><h2>{{ currentPlan ? 'Your content plan' : 'Start your weekly plan' }}</h2></div><span v-if="currentPlan" class="week-chip">Week of {{ formatDate(currentPlan.weekStart) }}</span></div>
              <template v-if="currentPlan">
                <div class="plan-summary"><div><strong>{{ currentPlan.slots.length }}</strong><span>planned ideas</span></div><div><strong>{{ priorityCount }}</strong><span>priorities</span></div><div><strong>{{ reserveCount }}</strong><span>reserves</span></div></div>
                <div class="capacity-block">
                  <div class="capacity-heading"><strong>Team capacity</strong><span>Planned time / available time</span></div>
                  <div class="capacity-person"><div class="person-line"><span><b class="person-dot person-a"></b> Person A</span><strong>{{ currentPlan.allocatedMinutes.A }} / {{ currentPlan.capacity.A }} min</strong></div><div class="progress-track"><span class="progress-fill fill-a" :style="{ width: personProgress('A') + '%' }"></span></div></div>
                  <div class="capacity-person"><div class="person-line"><span><b class="person-dot person-b"></b> Person B</span><strong>{{ currentPlan.allocatedMinutes.B }} / {{ currentPlan.capacity.B }} min</strong></div><div class="progress-track"><span class="progress-fill fill-b" :style="{ width: personProgress('B') + '%' }"></span></div></div>
                </div>
                <div class="mini-slot-list"><div v-for="(slot, index) in currentPlan.slots" :key="slot.ideaId" class="mini-slot"><span class="slot-index">{{ String(index + 1).padStart(2, '0') }}</span><div class="slot-name"><strong>{{ slot.title || ideas.find((idea) => idea.id === slot.ideaId)?.title || 'Content idea' }}</strong><small>{{ slot.type }} · Person {{ slot.assignee }}</small></div><span class="slot-time">{{ slot.estimatedMinutes }} min</span></div></div>
                <button class="text-button" @click="navigate('planning')">View full weekly plan <span>→</span></button>
              </template>
              <div v-else class="empty-state plan-empty"><div class="empty-illustration">▦</div><h3>Your week is a blank canvas.</h3><p>Select five ideas, assign owners and balance the team's capacity.</p><button class="primary-button" @click="goToPlan">Create weekly plan <span>→</span></button></div>
            </section>

            <section class="panel activity-panel">
              <div class="panel-heading"><div><div class="eyebrow">NEXT STEPS</div><h2>Keep things moving</h2></div><span class="sparkle">✳</span></div>
              <div class="action-list">
                <button class="action-item" @click="navigate('ideas')"><span class="action-icon mint">✳</span><span><strong>Build your idea bank</strong><small>{{ draftCount }} ideas ready for planning</small></span><b>→</b></button>
                <button class="action-item" @click="navigate('planning')"><span class="action-icon lavender">▦</span><span><strong>Balance team capacity</strong><small>3 priorities · 2 reserve slots</small></span><b>→</b></button>
                <button class="action-item" @click="navigate('review')"><span class="action-icon peach">✓</span><span><strong>Review content</strong><small>{{ reviewQueue.length }} items in the approval flow</small></span><b>→</b></button>
              </div>
              <div class="human-note"><span>✦</span><div><strong>Human approval, always.</strong><p>AI can suggest content. Your team stays in control of every approval and handoff.</p></div></div>
            </section>
          </div>

          <section class="panel recent-panel">
            <div class="panel-heading"><div><div class="eyebrow">CONTENT LIBRARY</div><h2>Recently added ideas</h2></div><button class="text-button" @click="navigate('ideas')">View all <span>→</span></button></div>
            <div v-if="ideas.length" class="table-wrap"><table><thead><tr><th>CONTENT IDEA</th><th>CHANNELS</th><th>STATUS</th><th>CREATED</th><th></th></tr></thead><tbody><tr v-for="idea in ideas.slice(0, 5)" :key="idea.id"><td><button class="table-title" @click="openIdea(idea)">{{ idea.title }}</button><small>{{ idea.topic }}</small></td><td><div class="channel-pills"><span v-for="channel in idea.channels" :key="channel">{{ channelLabels[channel] }}</span></div></td><td><span class="status-pill" :class="statusClass(idea.status)">{{ statusLabel(idea.status) }}</span></td><td>{{ formatDate(idea.createdAt) }}</td><td><button class="row-action" @click="openIdea(idea)">↗</button></td></tr></tbody></table></div>
            <div v-else class="empty-inline">No content ideas yet. Create your first idea to get started.</div>
          </section>
        </section>

        <section v-else-if="page === 'ideas'" class="page-section">
          <div class="page-heading compact-heading"><div><div class="eyebrow">CONTENT LIBRARY</div><h1>Your ideas, <em>your voice.</em></h1><p>Collect ideas before turning them into a weekly plan.</p></div><button class="primary-button" @click="showIdeaForm = true">＋ Create idea</button></div>
          <div class="filter-bar"><label class="search-box"><span>⌕</span><input v-model="searchTerm" placeholder="Search ideas, topics or audience..." /></label><select v-model="statusFilter"><option value="all">All statuses</option><option value="draft">Draft</option><option value="ready_for_planning">Planned</option><option value="review">In review</option><option value="approved">Approved</option><option value="handed_off">Handed off</option></select><span class="result-count">{{ filteredIdeas.length }} ideas</span></div>
          <div v-if="filteredIdeas.length" class="idea-grid"><article v-for="idea in filteredIdeas" :key="idea.id" class="idea-card"><div class="idea-card-top"><span class="idea-category">CONTENT IDEA</span><span class="status-pill" :class="statusClass(idea.status)">{{ statusLabel(idea.status) }}</span></div><h3>{{ idea.title }}</h3><p>{{ idea.topic }}</p><div class="idea-meta"><span>Audience</span><strong>{{ idea.targetAudience }}</strong></div><div class="channel-pills"><span v-for="channel in idea.channels" :key="channel">{{ channelLabels[channel] }}</span></div><div class="idea-card-footer"><small>Added {{ formatDate(idea.createdAt) }}</small><button class="outline-button" @click="openIdea(idea)">Open idea <span>↗</span></button></div></article></div>
          <div v-else class="empty-state large-empty"><div class="empty-illustration">✳</div><h3>{{ ideas.length ? 'No ideas match your filters.' : 'Your idea bank is waiting.' }}</h3><p>{{ ideas.length ? 'Try another search or status.' : 'Create your first content idea and start building your weekly plan.' }}</p><button v-if="!ideas.length" class="primary-button" @click="showIdeaForm = true">Create first idea</button></div>
        </section>

        <section v-else-if="page === 'planning'" class="page-section">
          <div class="page-heading compact-heading"><div><div class="eyebrow">CAPACITY & SCHEDULING</div><h1>Make room for <em>great work.</em></h1><p>Five ideas. Two people. One realistic weekly plan.</p></div><span class="week-chip">{{ currentPlan ? 'Week of ' + formatDate(currentPlan.weekStart) : 'Planning workspace' }}</span></div>
          <template v-if="currentPlan">
            <div class="capacity-cards">
              <div class="capacity-card"><div class="capacity-card-head"><span class="person-dot person-a"></span><strong>Person A</strong><span>{{ currentPlan.capacity.A }} min capacity</span></div><div class="capacity-number">{{ currentPlan.allocatedMinutes.A }}<small> / {{ currentPlan.capacity.A }} min</small></div><div class="progress-track"><span class="progress-fill fill-a" :style="{ width: personProgress('A') + '%' }"></span></div><small class="remaining">{{ currentPlan.remainingMinutes?.A ?? currentPlan.capacity.A - currentPlan.allocatedMinutes.A }} minutes remaining</small></div>
              <div class="capacity-card"><div class="capacity-card-head"><span class="person-dot person-b"></span><strong>Person B</strong><span>{{ currentPlan.capacity.B }} min capacity</span></div><div class="capacity-number">{{ currentPlan.allocatedMinutes.B }}<small> / {{ currentPlan.capacity.B }} min</small></div><div class="progress-track"><span class="progress-fill fill-b" :style="{ width: personProgress('B') + '%' }"></span></div><small class="remaining">{{ currentPlan.remainingMinutes?.B ?? currentPlan.capacity.B - currentPlan.allocatedMinutes.B }} minutes remaining</small></div>
            </div>
            <section class="panel plan-detail-panel"><div class="panel-heading"><div><div class="eyebrow">WEEK OF {{ formatDate(currentPlan.weekStart).toUpperCase() }}</div><h2>Editorial schedule</h2></div><span class="status-pill status-ready_for_planning">{{ currentPlan.slots.length }} slots assigned</span></div><div class="schedule-list"><div v-for="(slot, index) in currentPlan.slots" :key="slot.ideaId" class="schedule-row"><div class="schedule-number">{{ String(index + 1).padStart(2, '0') }}</div><div class="schedule-main"><span class="schedule-type" :class="slot.type">{{ slot.type }}</span><strong>{{ slot.title || ideas.find((idea) => idea.id === slot.ideaId)?.title || 'Content idea' }}</strong><small>{{ ideas.find((idea) => idea.id === slot.ideaId)?.topic || 'Content topic' }}</small></div><div class="schedule-owner"><span class="person-dot" :class="slot.assignee === 'A' ? 'person-a' : 'person-b'"></span> Person {{ slot.assignee }}</div><div class="schedule-minutes">{{ slot.estimatedMinutes }} <small>min</small></div><button class="row-action" @click="openSlotIdea(slot.ideaId)" :disabled="!ideas.find((idea) => idea.id === slot.ideaId)">↗</button></div></div><div class="plan-footnote"><span>✦</span> Capacity is based on assigned slot estimates. Brief effort estimates are illustrative and do not automatically change these allocations.</div></section>
          </template>
          <template v-else>
            <div class="plan-builder-grid">
              <section class="panel builder-panel"><div class="panel-heading"><div><div class="eyebrow">STEP 01 · SELECT CONTENT</div><h2>Choose five ideas</h2></div><span class="selection-count">{{ selectedPlanIds.length }} / 5 selected</span></div><p class="section-description">Your first three selections become priorities. The final two are reserve ideas.</p><div v-if="ideas.filter((idea) => !['handed_off'].includes(idea.status)).length" class="select-idea-list"><label v-for="idea in ideas.filter((item) => item.status !== 'handed_off')" :key="idea.id" class="select-idea-row" :class="{ chosen: selectedPlanIds.includes(idea.id) }"><input type="checkbox" :checked="selectedPlanIds.includes(idea.id)" @change="togglePlanIdea(idea.id)" :disabled="!selectedPlanIds.includes(idea.id) && selectedPlanIds.length >= 5" /><span class="custom-check">✓</span><span class="select-idea-copy"><strong>{{ idea.title }}</strong><small>{{ idea.topic }}</small></span><span class="status-pill" :class="statusClass(idea.status)">{{ statusLabel(idea.status) }}</span></label></div><div v-else class="empty-inline">Create at least five ideas before planning your week.</div></section>
              <section class="panel builder-panel"><div class="panel-heading"><div><div class="eyebrow">STEP 02 · TEAM CAPACITY</div><h2>Set your limits</h2></div></div><div class="field-grid"><label class="field"><span>Week starts (Monday)</span><input v-model="planWeekStart" type="date" /></label><label class="field"><span>Person A capacity · minutes</span><input v-model.number="capacityA" type="number" min="0" max="600" /></label><label class="field"><span>Person B capacity · minutes</span><input v-model.number="capacityB" type="number" min="0" max="600" /></label></div><div class="capacity-preview"><div><span>Person A</span><strong :class="{ over: plannedMinutesA > capacityA }">{{ plannedMinutesA }} / {{ capacityA }} min</strong></div><div class="progress-track"><span class="progress-fill fill-a" :style="{ width: Math.min(100, capacityA ? plannedMinutesA / capacityA * 100 : 0) + '%' }"></span></div><div><span>Person B</span><strong :class="{ over: plannedMinutesB > capacityB }">{{ plannedMinutesB }} / {{ capacityB }} min</strong></div><div class="progress-track"><span class="progress-fill fill-b" :style="{ width: Math.min(100, capacityB ? plannedMinutesB / capacityB * 100 : 0) + '%' }"></span></div></div></section>
            </div>
            <section v-if="selectedPlanIdeas.length" class="panel allocation-panel"><div class="panel-heading"><div><div class="eyebrow">STEP 03 · ASSIGN WORK</div><h2>Who does what?</h2></div><span class="selection-count">Priority × 3 · Reserve × 2</span></div><div class="allocation-list"><div v-for="(idea, index) in selectedPlanIdeas" :key="idea.id" class="allocation-row"><span class="allocation-index">{{ String(index + 1).padStart(2, '0') }}</span><div class="allocation-name"><strong>{{ idea.title }}</strong><small>{{ index < 3 ? 'Priority' : 'Reserve' }}</small></div><select v-model="slotSettings[idea.id].assignee"><option value="A">Person A</option><option value="B">Person B</option></select><label class="minutes-input"><input v-model.number="slotSettings[idea.id].minutes" type="number" min="1" max="600" /><span>min</span></label></div></div><div v-if="plannedMinutesA > capacityA || plannedMinutesB > capacityB" class="capacity-warning">⚠ Capacity exceeded. Reassign work or reduce estimates before saving.</div><div class="builder-footer"><span>Nothing is saved until you create the plan.</span><button class="primary-button" :disabled="saving || selectedPlanIds.length !== 5 || plannedMinutesA > capacityA || plannedMinutesB > capacityB" @click="createPlan">{{ saving ? 'Saving…' : 'Create weekly plan' }} <span>→</span></button></div></section>
          </template>
        </section>

        <section v-else class="page-section">
          <div class="page-heading compact-heading"><div><div class="eyebrow">HUMAN-IN-THE-LOOP</div><h1>Good content deserves <em>a second look.</em></h1><p>Review the exact version before it can be handed off.</p></div><span class="review-count">{{ reviewQueue.length }} in workflow</span></div>
          <div v-if="reviewQueue.length" class="review-grid"><article v-for="idea in reviewQueue" :key="idea.id" class="review-card"><div class="review-card-top"><span class="review-avatar">✳</span><span class="status-pill" :class="statusClass(idea.status)">{{ statusLabel(idea.status) }}</span></div><h3>{{ idea.title }}</h3><p>{{ idea.topic }}</p><div class="channel-pills"><span v-for="channel in idea.channels" :key="channel">{{ channelLabels[channel] }}</span></div><div class="review-card-bottom"><span>{{ idea.status === 'review' ? 'Needs human review' : 'Approved version' }}</span><button class="outline-button" @click="openIdea(idea)">Open review <span>↗</span></button></div></article></div>
          <div v-else class="empty-state large-empty"><div class="empty-illustration">✓</div><h3>All clear for now.</h3><p>Content appears here after a brief has been generated and submitted for review.</p><button class="secondary-button" @click="navigate('ideas')">Browse content ideas</button></div>
          <div class="human-note wide-note"><span>✦</span><div><strong>Approval is tied to a specific version.</strong><p>If copy, channels, media or schedule changes, the previous approval is invalidated. A new review is required before handoff.</p></div></div>
        </section>

        <footer class="page-footer"><span>© 2026 Bro World AI · Demo workspace</span><span>AI-assisted, human-approved <b>✦</b></span></footer>
      </div>
    </main>

    <div v-if="showIdeaForm" class="modal-backdrop" @click.self="showIdeaForm = false">
      <section class="modal-card form-modal">
        <div class="modal-heading"><div><div class="eyebrow">IDEA BANK</div><h2>Create a content idea</h2><p>Start with the topic. You can build the brief later.</p></div><button class="close-button" @click="showIdeaForm = false">×</button></div>
        <form class="modal-form" @submit.prevent="createIdea">
          <label class="field"><span>Idea title</span><input v-model="newIdea.title" required maxlength="160" placeholder="e.g. Find your everyday frame" /></label>
          <label class="field"><span>Topic / key message</span><textarea v-model="newIdea.topic" required maxlength="255" rows="2" placeholder="What should this content be about?" /></label>
          <label class="field"><span>Target audience</span><input v-model="newIdea.targetAudience" required maxlength="255" /></label>
          <label class="field"><span>Tone of voice</span><input v-model="newIdea.tone" required maxlength="80" /></label>
          <div class="field"><span>Social channels</span><div class="channel-options"><button v-for="channel in channels" :key="channel" type="button" :class="{ selected: newIdea.channels.includes(channel) }" @click="toggleNewChannel(channel)">{{ channelLabels[channel] }} <b>{{ newIdea.channels.includes(channel) ? '✓' : '+' }}</b></button></div></div>
          <div class="modal-actions"><button type="button" class="secondary-button" @click="showIdeaForm = false">Cancel</button><button class="primary-button" type="submit" :disabled="saving">{{ saving ? 'Creating…' : 'Create idea' }} <span>→</span></button></div>
        </form>
      </section>
    </div>

    <div v-if="selectedIdea" class="modal-backdrop detail-backdrop" @click.self="closeIdea">
      <section class="detail-drawer">
        <header class="drawer-header"><div><div class="eyebrow">CONTENT WORKFLOW</div><div class="drawer-title-row"><h2>{{ selectedIdea.title }}</h2><span class="status-pill" :class="statusClass(selectedIdea.status)">{{ statusLabel(selectedIdea.status) }}</span></div><p>{{ selectedIdea.topic }}</p></div><button class="close-button" @click="closeIdea">×</button></header>
        <div v-if="detailLoading" class="drawer-loading">Loading content and brief…</div>
        <div class="drawer-body">
          <section class="drawer-section"><div class="drawer-section-heading"><div><div class="eyebrow">01 · IDEA DETAILS</div><h3>Content direction</h3></div></div>
            <div class="field-grid"><label class="field"><span>Title</span><input v-model="editForm.title" :disabled="selectedIdea.status === 'handed_off'" /></label><label class="field"><span>Topic</span><input v-model="editForm.topic" :disabled="selectedIdea.status === 'handed_off'" /></label><label class="field"><span>Target audience</span><input v-model="editForm.targetAudience" :disabled="selectedIdea.status === 'handed_off'" /></label><label class="field"><span>Tone</span><input v-model="editForm.tone" :disabled="selectedIdea.status === 'handed_off'" /></label></div>
            <div class="field"><span>Channels</span><div class="channel-options"><button v-for="channel in channels" :key="channel" type="button" :disabled="selectedIdea.status === 'handed_off'" :class="{ selected: editForm.channels.includes(channel) }" @click="toggleEditChannel(channel)">{{ channelLabels[channel] }} <b>{{ editForm.channels.includes(channel) ? '✓' : '+' }}</b></button></div></div>
            <div v-if="selectedIdea.status !== 'handed_off'" class="section-actions"><button class="secondary-button" :disabled="saving" @click="saveIdeaChanges">Save changes & reset approval</button></div>
            <p v-else class="immutable-note">This content has been handed off and is immutable. Create a new idea for another version.</p>
          </section>

          <section class="drawer-section"><div class="drawer-section-heading"><div><div class="eyebrow">02 · CREATIVE BRIEF</div><h3>{{ selectedBrief ? 'Generated brief' : 'Generate your brief' }}</h3></div><span v-if="selectedBrief" class="hash-label">v{{ selectedBrief.versionHash.slice(0, 8) }}</span></div>
            <template v-if="!selectedBrief">
              <p class="section-description">A media reference and publishing schedule are required. Use a synthetic reference for this demo.</p>
              <label class="field"><span>Media reference</span><input v-model="briefForm.mediaReference" :disabled="['approved', 'handed_off'].includes(selectedIdea.status)" placeholder="media://sample/image" /></label>
              <label class="field"><span>Scheduled at</span><input v-model="briefForm.scheduledAt" type="datetime-local" :disabled="['approved', 'handed_off'].includes(selectedIdea.status)" /></label>
              <button v-if="!['approved', 'handed_off'].includes(selectedIdea.status)" class="primary-button full-button" :disabled="saving || !briefForm.mediaReference || !briefForm.scheduledAt" @click="generateBrief">{{ saving ? 'Generating…' : 'Generate content brief' }} <span>✦</span></button>
            </template>
            <template v-else>
              <div class="brief-preview">
                <div class="brief-hook">{{ selectedBrief.brief.hook }}</div>
                <div class="brief-block"><span>MASTER COPY</span><p>{{ selectedBrief.brief.masterCopy }}</p></div>
                <div class="brief-block"><span>VISUAL DIRECTION</span><p>{{ selectedBrief.brief.visual.overlay }} · {{ selectedBrief.brief.visual.titleImage }}</p><small>Media: {{ selectedBrief.brief.visual.mediaReference }}</small></div>
                <div class="brief-block"><span>PRODUCTION PLAN</span><p>{{ selectedBrief.brief.productionPlan.format }}</p><ol><li v-for="step in selectedBrief.brief.productionPlan.steps" :key="step">{{ step }}</li></ol></div>
                <div class="brief-block"><span>CHANNEL VARIANTS</span><div v-for="(variant, channel) in selectedBrief.brief.channelVariants" :key="channel" class="variant-card"><strong>{{ channelLabel(String(channel)) }}</strong><p>{{ variant.copy }}</p><small>{{ variant.format }} · CTA: {{ variant.cta }}</small></div></div>
                <div class="brief-block"><span>EFFORT ESTIMATE</span><div class="effort-grid"><div><strong>{{ selectedBrief.brief.effortMinutes.research }}</strong><small>Research</small></div><div><strong>{{ selectedBrief.brief.effortMinutes.copyAndVariants }}</strong><small>Copy</small></div><div><strong>{{ selectedBrief.brief.effortMinutes.production }}</strong><small>Production</small></div><div><strong>{{ selectedBrief.brief.effortMinutes.coordinationAndApproval }}</strong><small>Approval</small></div><div class="effort-total"><strong>{{ selectedBrief.brief.effortMinutes.total }} min</strong><small>Total</small></div></div></div>
                <div class="brief-schedule"><span>Scheduled</span><strong>{{ formatDateTime(selectedBrief.brief.scheduledAt) }}</strong></div>
                <div class="brief-schedule"><span>Booking URL (placeholder)</span><strong>{{ selectedBrief.brief.bookingLink }}</strong></div>
                <p class="synthetic-note">Synthetic sample content. Human review required. The media reference and booking URL are placeholders.</p>
              </div>
              <button v-if="!['approved', 'handed_off'].includes(selectedIdea.status)" class="secondary-button full-button" :disabled="saving" @click="selectedBrief = null">Regenerate brief / change media & schedule</button>
            </template>
          </section>

          <section class="drawer-section"><div class="drawer-section-heading"><div><div class="eyebrow">03 · HUMAN APPROVAL</div><h3>Review & handoff</h3></div></div>
            <div class="workflow-steps"><div :class="{ complete: ['review', 'approved', 'handed_off'].includes(selectedIdea.status) }"><span>1</span><small>Draft</small></div><i></i><div :class="{ complete: ['review', 'approved', 'handed_off'].includes(selectedIdea.status) }"><span>2</span><small>Review</small></div><i></i><div :class="{ complete: ['approved', 'handed_off'].includes(selectedIdea.status) }"><span>3</span><small>Approve</small></div><i></i><div :class="{ complete: selectedIdea.status === 'handed_off' }"><span>4</span><small>Handoff</small></div></div>
            <p class="section-description">Content must be assigned to a weekly plan and have a complete brief before review. Approval applies to the exact brief version.</p>
            <div class="workflow-actions">
              <button v-if="selectedIdea.status === 'draft' || selectedIdea.status === 'ready_for_planning'" class="secondary-button full-button" :disabled="saving || !selectedBrief || !isIdeaAssigned(selectedIdea.id)" @click="submitForReview">Submit for review</button>
              <p v-if="['draft', 'ready_for_planning'].includes(selectedIdea.status) && !isIdeaAssigned(selectedIdea.id)" class="inline-hint">Add this idea to a weekly plan before submitting it for review.</p>
              <button v-if="selectedIdea.status === 'review'" class="primary-button full-button" :disabled="saving || !selectedBrief" @click="approveIdea">Approve this exact version <span>✓</span></button>
              <button v-if="selectedIdea.status === 'approved'" class="primary-button full-button" :disabled="saving" @click="handoffIdea">{{ saving ? 'Handing off…' : 'Create simulated handoff' }} <span>→</span></button>
              <div v-if="selectedIdea.status === 'handed_off'" class="handoff-success"><span>✓</span><div><strong>Handoff completed</strong><p>Persisted in the backend. No social platform was contacted and nothing was published.</p></div></div>
              <div v-if="handoffResult" class="handoff-payload"><div><span>HANDOFF ID</span><strong>{{ handoffResult.handoffId }}</strong></div><div><span>MODE</span><strong>SIMULATED</strong></div><div><span>IDEMPOTENT REPLAY</span><strong>{{ handoffResult.idempotentReplay ? 'YES' : 'NO' }}</strong></div></div>
            </div>
          </section>
        </div>
        <footer class="drawer-footer"><span>Version-bound approval · Human in control</span><button class="secondary-button" @click="closeIdea">Close</button></footer>
      </section>
    </div>
  </div>
</template>
