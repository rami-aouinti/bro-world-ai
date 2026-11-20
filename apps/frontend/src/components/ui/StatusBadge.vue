<script setup lang="ts">
defineProps<{ status: string }>()
const labels: Record<string,string> = { draft:'Draft', ready_for_planning:'Planned', review:'In review', approved:'Approved', handed_off:'Handed off' }
</script>
<template><span class="status-badge" :class="'status-' + status">{{ labels[status] || status }}</span></template>
