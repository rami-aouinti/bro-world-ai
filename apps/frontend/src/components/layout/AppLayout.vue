<script setup lang="ts">
import { onMounted } from 'vue'
import AppSidebar from './AppSidebar.vue'
import AppHeader from './AppHeader.vue'
import { useWorkspace } from '../../composables/useWorkspace'
const { state, refresh, clearMessages } = useWorkspace()
onMounted(() => { if (!state.ideas.length) void refresh() })
</script>
<template>
  <div class="app-shell"><AppSidebar/><main class="main-area"><AppHeader/><div class="page-content">
    <div v-if="state.error" class="toast toast-error" role="alert"><span>!</span><p>{{ state.error }}</p><button aria-label="Dismiss error" @click="clearMessages(); state.error = ''">×</button></div>
    <div v-if="state.success" class="toast toast-success" role="status"><span>✓</span><p>{{ state.success }}</p><button aria-label="Dismiss message" @click="state.success = ''">×</button></div>
    <RouterView/>
    <footer class="app-footer"><span>© 2026 Bro World AI · Demo workspace</span><span>AI-assisted. Human-approved. <b>✦</b></span></footer>
  </div></main></div>
</template>
