<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { RouterLink } from 'vue-router'
import { useWorkspace } from '../composables/useWorkspace'
import MetricCard from '../components/ui/MetricCard.vue'
import IdeaCard from '../components/ideas/IdeaCard.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'
const { state, draftCount, reviewQueue, openIdea } = useWorkspace()
const router = useRouter()
function openRecent(idea: import('../types').ContentIdea) { void openIdea(idea); void router.push('/review') }
const priority = computed(() => state.plan?.slots.filter(s=>s.type==='priority').length || 0)
const reserves = computed(() => state.plan?.slots.filter(s=>s.type==='reserve').length || 0)
const latest = computed(() => state.ideas.slice(0,3))
</script>
<template>
  <section class="page-section">
    <div class="hero-panel"><div class="hero-copy"><span class="eyebrow">YOUR CONTENT WORKSPACE</span><h1>Make room for<br><em>great ideas.</em></h1><p>One calm place to plan, create and approve social content for your optical studio.</p><div class="hero-actions"><RouterLink to="/planning" class="button button-primary">Build this week's plan <span>↗</span></RouterLink><RouterLink to="/ideas" class="button button-secondary">Explore content studio</RouterLink></div></div><div class="hero-art"><div class="orbit orbit-one"></div><div class="orbit orbit-two"></div><div class="hero-spark">✳</div><div class="hero-note note-top"><span>THIS WEEK</span><strong>{{ priority }} priorities</strong></div><div class="hero-note note-bottom"><span>HUMAN REVIEW</span><strong>{{ reviewQueue.length }} waiting</strong></div></div></div>
    <div class="section-heading"><div><span class="eyebrow">WORKSPACE PULSE</span><h2>At a glance</h2><p>A quick overview of what needs your attention.</p></div><span class="live-indicator"><i></i>{{ state.online ? 'Connected' : 'Connecting' }}</span></div>
    <div class="metric-grid">
      <MetricCard label="Content ideas" :value="state.ideas.length" :note="draftCount + ' drafts to shape'" icon="✳" tone="mint"/>
      <MetricCard label="Priority posts" :value="priority" :note="reserves + ' reserve ideas'" icon="▦" tone="blue"/>
      <MetricCard label="In review" :value="reviewQueue.length" note="Human decision required" icon="✓" tone="amber"/>
      <MetricCard label="Handed off" :value="state.ideas.filter(i=>i.status==='handed_off').length" note="Simulated delivery only" icon="↗" tone="violet"/>
    </div>
    <div class="content-columns">
      <section class="panel"><div class="panel-heading"><div><span class="eyebrow">RECENT ACTIVITY</span><h3>Recently added</h3></div><RouterLink to="/ideas" class="text-link">View all ↗</RouterLink></div>
        <div v-if="latest.length" class="compact-list"><button v-for="idea in latest" :key="idea.id" class="compact-idea" @click="openRecent(idea)"><span class="compact-mark">✳</span><span class="compact-copy"><strong>{{ idea.title }}</strong><small>{{ idea.topic }}</small></span><StatusBadge :status="idea.status"/><span class="chevron">›</span></button></div>
        <div v-else class="empty-state"><span>✳</span><h3>Your idea library is waiting.</h3><p>Generate your first ideas with the AI content studio.</p><RouterLink to="/ideas" class="button button-primary">Open content studio</RouterLink></div>
      </section>
      <section class="panel plan-summary"><div class="panel-heading"><div><span class="eyebrow">WEEKLY PLAN</span><h3>{{ state.plan ? 'Your week is taking shape' : 'Start with a clear plan' }}</h3></div><span class="panel-icon">▦</span></div>
        <template v-if="state.plan"><div class="plan-date">{{ state.plan.weekStart }}</div><div class="plan-counts"><div><strong>{{ priority }}</strong><span>Priorities</span></div><div><strong>{{ reserves }}</strong><span>Reserves</span></div></div><div class="capacity-line" v-for="person in (['A','B'] as const)" :key="person"><div><span>Person {{ person }}</span><strong>{{ state.plan.allocatedMinutes[person] }} / {{ state.plan.capacity[person] }} min</strong></div><div class="progress-track"><i :style="{width: Math.min(100,state.plan.allocatedMinutes[person]/Math.max(1,state.plan.capacity[person])*100)+'%'}"></i></div></div></template>
        <div v-else class="empty-plan"><p>Choose five ideas, assign owners and protect each person's weekly capacity.</p><RouterLink to="/planning" class="button button-primary">Create weekly plan ↗</RouterLink></div>
      </section>
    </div>
    <div v-if="state.loading" class="loading-line">Refreshing workspace data…</div>
  </section>
</template>
