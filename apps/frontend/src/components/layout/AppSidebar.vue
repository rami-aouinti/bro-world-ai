<script setup lang="ts">
import { useRoute } from 'vue-router'
import { useWorkspace } from '../../composables/useWorkspace'
const route = useRoute()
const { state, reviewQueue } = useWorkspace()
const links = [
  { to:'/', label:'Overview', icon:'◫' },
  { to:'/ideas', label:'Content studio', icon:'✳' },
  { to:'/planning', label:'Weekly planner', icon:'▦' },
  { to:'/review', label:'Review & approval', icon:'✓' },
]
</script>
<template>
  <aside class="sidebar">
    <RouterLink to="/" class="brand"><span class="brand-mark">bw</span><span><strong>bro world</strong><small>AI CONTENT STUDIO</small></span></RouterLink>
    <div class="nav-caption">WORKSPACE</div>
    <nav class="side-nav">
      <RouterLink v-for="link in links" :key="link.to" :to="link.to" class="nav-link" :class="{ active: route.path === link.to }">
        <span class="nav-icon">{{ link.icon }}</span><span>{{ link.label }}</span>
        <span v-if="link.to === '/ideas'" class="nav-count">{{ state.ideas.length }}</span>
        <span v-if="link.to === '/review' && reviewQueue.length" class="nav-count">{{ reviewQueue.length }}</span>
      </RouterLink>
    </nav>
    <div class="sidebar-spacer"></div>
    <div class="connection-card"><span class="connection-dot" :class="{ online: state.online }"></span><div><strong>{{ state.online ? 'Workspace connected' : 'Connecting to API' }}</strong><small>Symfony backend</small></div></div>
    <div class="profile-row"><span class="profile-avatar">OS</span><span><strong>Optical Studio</strong><small>Demo workspace</small></span><span class="profile-more">···</span></div>
  </aside>
</template>
