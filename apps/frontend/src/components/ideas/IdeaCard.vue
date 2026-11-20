<script setup lang="ts">
import type { ContentIdea } from '../../types'
import StatusBadge from '../ui/StatusBadge.vue'
defineProps<{ idea:ContentIdea; assigned?:boolean }>()
defineEmits<{ open:[idea:ContentIdea] }>()
</script>
<template>
  <article class="idea-card">
    <div class="idea-card-top"><span class="card-eyebrow">CONTENT IDEA</span><StatusBadge :status="idea.status"/></div>
    <h3>{{ idea.title }}</h3><p class="idea-topic">{{ idea.topic }}</p>
    <div class="idea-meta"><span>Audience</span><strong>{{ idea.targetAudience }}</strong></div>
    <div class="channel-tags"><span v-for="channel in idea.channels" :key="channel">{{ channel }}</span></div>
    <div class="idea-card-bottom"><span>{{ assigned ? 'In weekly plan' : idea.tone }}</span><button class="button button-quiet" @click="$emit('open',idea)">View details <span>↗</span></button></div>
  </article>
</template>
