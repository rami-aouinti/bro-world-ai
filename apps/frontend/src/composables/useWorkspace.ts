import { computed, reactive, ref } from 'vue'
import { api, ApiError } from '../api'
import type { Assignee, BriefResponse, Channel, ContentIdea, HandoffResponse, IdeaSuggestion, WeeklyPlan } from '../types'

const state = reactive({
  ideas: [] as ContentIdea[],
  suggestions: [] as IdeaSuggestion[],
  provider: '',
  sources: [] as string[],
  plan: null as WeeklyPlan | null,
  loading: false,
  saving: false,
  aiLoading: false,
  online: false,
  error: '',
  success: '',
  selected: null as ContentIdea | null,
  brief: null as BriefResponse | null,
  handoff: null as HandoffResponse | null,
})
const selectedIds = ref<string[]>([])
const slots = reactive<Record<string, { assignee: Assignee; minutes: number }>>({})
const weekStart = ref(mondayOf(new Date()))
const capacityA = ref(90)
const capacityB = ref(150)
const channels: Channel[] = ['instagram', 'facebook', 'tiktok', 'linkedin']
const channelLabels: Record<Channel, string> = { instagram: 'Instagram', facebook: 'Facebook', tiktok: 'TikTok', linkedin: 'LinkedIn' }
const draftCount = computed(() => state.ideas.filter(i => ['draft', 'ready_for_planning'].includes(i.status)).length)
const reviewQueue = computed(() => state.ideas.filter(i => ['review', 'approved'].includes(i.status)))
const plannedMinutesA = computed(() => selectedIds.value.reduce((n,id) => n + (slots[id]?.assignee === 'A' ? Number(slots[id].minutes) : 0), 0))
const plannedMinutesB = computed(() => selectedIds.value.reduce((n,id) => n + (slots[id]?.assignee === 'B' ? Number(slots[id].minutes) : 0), 0))

function mondayOf(date: Date): string {
  const d = new Date(date); d.setDate(d.getDate() - ((d.getDay() + 6) % 7)); return dateInput(d)
}
function dateInput(d: Date): string {
  return [d.getFullYear(), String(d.getMonth()+1).padStart(2,'0'), String(d.getDate()).padStart(2,'0')].join('-')
}
function clearMessages() { state.error = ''; state.success = '' }
function report(error: unknown) {
  if (error instanceof ApiError) {
    const warnings = error.body.capacityWarnings
    state.error = warnings ? error.message + ' ' + Object.entries(warnings).map(([p,v]) => `Person ${p}: ${v.plannedMinutes}/${v.capacityMinutes} min (+${v.overByMinutes}).`).join(' ') : error.message
  } else state.error = error instanceof Error ? error.message : 'An unexpected error occurred.'
}
async function refresh() {
  state.loading = true; clearMessages()
  try {
    state.ideas = await api.listIdeas()
    const id = window.localStorage.getItem('bro-world-current-plan')
    if (id) {
      try { state.plan = await api.getWeeklyPlan(id); weekStart.value = state.plan.weekStart }
      catch { state.plan = null; window.localStorage.removeItem('bro-world-current-plan') }
    }
    state.online = true
  } catch (e) { state.online = false; report(e) }
  finally { state.loading = false }
}
async function generateIdeas(input: { audience: string; tone: string; channels: Channel[] }) {
  clearMessages(); state.aiLoading = true
  try {
    const result = await api.generateIdeas({ campaign: 'Weekly social media campaign for a fictional optical retailer', ...input })
    state.suggestions = result.ideas; state.provider = result.ai.provider + (result.ai.simulated ? ' · simulated' : '')
    state.sources = result.knowledgeSources?.map(s => s.sourceId) || []
    state.success = 'Five AI suggestions are ready. Review them before adding them to your library.'
  } catch(e) { report(e) } finally { state.aiLoading = false }
}
async function createIdea(input: { title: string; topic: string; targetAudience: string; tone: string; channels: Channel[] }) {
  clearMessages(); state.saving = true
  try { const idea = await api.createIdea(input); state.ideas.unshift(idea); state.success = 'Idea added to your content library.'; return idea }
  catch(e) { report(e); return null } finally { state.saving = false }
}
async function addSuggestion(s: IdeaSuggestion) {
  const idea = await createIdea(s)
  if (idea) state.suggestions = state.suggestions.filter(item => item !== s)
}
function togglePlanIdea(id: string) {
  clearMessages()
  if (selectedIds.value.includes(id)) { selectedIds.value = selectedIds.value.filter(x => x !== id); return }
  if (selectedIds.value.length >= 5) { state.error = 'A weekly plan contains exactly five ideas.'; return }
  selectedIds.value.push(id)
  if (!slots[id]) slots[id] = { assignee: selectedIds.value.length % 2 ? 'A' : 'B', minutes: 30 }
}
async function createPlan() {
  clearMessages()
  if (selectedIds.value.length !== 5) { state.error = 'Select exactly five ideas: three priorities and two reserves.'; return }
  if (plannedMinutesA.value > capacityA.value || plannedMinutesB.value > capacityB.value) { state.error = 'Capacity exceeded. Reassign work or adjust estimates.'; return }
  state.saving = true
  try {
    state.plan = await api.createWeeklyPlan({
      weekStart: weekStart.value, capacityPersonA: Number(capacityA.value), capacityPersonB: Number(capacityB.value),
      slots: selectedIds.value.map((id,index) => ({ ideaId:id, type:index < 3 ? 'priority' : 'reserve', assignee:slots[id]?.assignee || 'A', estimatedMinutes:Number(slots[id]?.minutes || 30) })),
    })
    window.localStorage.setItem('bro-world-current-plan', state.plan.id); selectedIds.value = []
    state.ideas = await api.listIdeas(); state.success = 'Weekly plan saved with three priorities and two reserves.'
  } catch(e) { report(e) } finally { state.saving = false }
}
async function openIdea(idea: ContentIdea) {
  state.selected = idea; state.brief = null; state.handoff = null; clearMessages()
  try { state.brief = await api.getBrief(idea.id) }
  catch(e) { if (!(e instanceof ApiError) || e.status !== 404) report(e) }
}
async function generateBrief(mediaReference: string, scheduledAt: string) {
  if (!state.selected) return
  clearMessages(); state.saving = true
  try { state.brief = await api.generateBrief(state.selected.id, { mediaReference, scheduledAt: new Date(scheduledAt).toISOString() }); await refreshIdeas(); state.success = 'Brief generated. Review it before submitting.' }
  catch(e) { report(e) } finally { state.saving = false }
}
async function refreshIdeas() {
  state.ideas = await api.listIdeas()
  if (state.selected) state.selected = state.ideas.find(i => i.id === state.selected?.id) || state.selected
}
async function submitReview() { await runSelected(() => api.review(state.selected!.id), 'Content submitted for human review.') }
async function approve() { await runSelected(() => api.approve(state.selected!.id), 'The exact brief version has been approved.') }
async function handoff() {
  if (!state.selected) return
  await runSelected(async () => { state.handoff = await api.handoff(state.selected!.id, 'bro-world-' + state.selected!.id + '-' + (state.brief?.versionHash || 'v1')) }, 'Simulated handoff saved. No social network was contacted.')
}
async function runSelected(action: () => Promise<unknown>, message: string) {
  if (!state.selected) return
  clearMessages(); state.saving = true
  try { await action(); await refreshIdeas(); state.success = message }
  catch(e) { report(e) } finally { state.saving = false }
}
async function saveIdeaChanges(payload: Partial<Pick<ContentIdea,'title'|'topic'|'targetAudience'|'tone'|'channels'>>) {
  if (!state.selected) return
  await runSelected(() => api.editIdea(state.selected!.id, payload), 'Changes saved. Previous brief and approval are invalidated.')
  state.brief = null
}
function closeIdea() { state.selected = null; state.brief = null; state.handoff = null }

export function useWorkspace() {
  return {
    state, channels, channelLabels, draftCount, reviewQueue, selectedIds, slots, weekStart, capacityA, capacityB,
    plannedMinutesA, plannedMinutesB, refresh, generateIdeas, createIdea, addSuggestion, togglePlanIdea, createPlan,
    openIdea, generateBrief, submitReview, approve, handoff, saveIdeaChanges, closeIdea, clearMessages,
  }
}
