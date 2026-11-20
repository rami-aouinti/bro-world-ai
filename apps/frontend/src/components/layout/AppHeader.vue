<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useWorkspace } from '../../composables/useWorkspace'
const route = useRoute()
const { state, refresh } = useWorkspace()
const title = computed(() => String(route.meta.title || 'Workspace'))
const date = new Intl.DateTimeFormat('en-GB',{weekday:'short',day:'2-digit',month:'short'}).format(new Date())
</script>
<template>
  <header class="topbar">
    <div><div class="topbar-kicker">OPTICAL STUDIO <span>/</span> WORKSPACE</div><div class="topbar-title">{{ title }}</div></div>
    <div class="topbar-actions"><span class="today-label">{{ date }}</span><button class="icon-button" title="Refresh workspace" :disabled="state.loading" @click="refresh">↻</button><RouterLink class="button button-primary button-small" to="/ideas">＋ New idea</RouterLink></div>
  </header>
</template>
