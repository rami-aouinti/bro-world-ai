<script setup lang="ts">
defineProps<{ label:string; value:string|number; note:string; icon:string; tone?:string }>()
</script>
<template><article class="metric-card"><div class="metric-head"><span>{{ label }}</span><span class="metric-icon" :class="tone">{{ icon }}</span></div><strong>{{ value }}</strong><small>{{ note }}</small></article></template>
