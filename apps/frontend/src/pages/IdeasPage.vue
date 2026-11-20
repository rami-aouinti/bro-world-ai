<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useWorkspace } from '../composables/useWorkspace'
import IdeaCard from '../components/ideas/IdeaCard.vue'
import type { Channel } from '../types'
const { state, channels, channelLabels, generateIdeas, addSuggestion, createIdea, openIdea } = useWorkspace()
const router = useRouter()
function openIdeaDetails(idea: import('../types').ContentIdea) { void openIdea(idea); void router.push('/review') }
const tab = ref<'library'|'ai'>('ai')
const search = ref('')
const showForm = ref(false)
const form = reactive({ title:'', topic:'', targetAudience:'Adults aged 25 to 40', tone:'Friendly and informative', channels:['instagram','facebook'] as Channel[] })
const aiForm = reactive({ audience:'Adults aged 25 to 40', tone:'Friendly, modern and informative', channels:['instagram','tiktok'] as Channel[] })
const filtered = computed(()=>state.ideas.filter(i=>[i.title,i.topic,i.targetAudience].some(v=>v.toLowerCase().includes(search.value.toLowerCase()))))
function toggle(list:Channel[], channel:Channel, setter:(v:Channel[])=>void){ setter(list.includes(channel)?list.filter(x=>x!==channel):[...list,channel]) }
function toggleForm(c:Channel){ form.channels=form.channels.includes(c)?form.channels.filter(x=>x!==c):[...form.channels,c] }
function toggleAi(c:Channel){ aiForm.channels=aiForm.channels.includes(c)?aiForm.channels.filter(x=>x!==c):[...aiForm.channels,c] }
async function submit(){ const result=await createIdea({...form,channels:[...form.channels]}); if(result){showForm.value=false;form.title='';form.topic=''} }
</script>
<template>
  <section class="page-section">
    <div class="page-intro-row"><div><span class="eyebrow">IDEA LIBRARY & AI STUDIO</span><h1>Ideas worth <em>sharing.</em></h1><p>Explore your library or ask AI for a fresh creative starting point.</p></div><button class="button button-primary" @click="showForm=!showForm">＋ Create an idea</button></div>
    <div class="studio-tabs"><button :class="{active:tab==='ai'}" @click="tab='ai'"><span>✦</span> AI idea studio</button><button :class="{active:tab==='library'}" @click="tab='library'">Content library <span class="tab-count">{{state.ideas.length}}</span></button></div>
    <section v-if="tab==='ai'" class="ai-workspace">
      <div class="ai-intro"><div class="ai-symbol">✳</div><div><span class="eyebrow">AI-ASSISTED IDEATION</span><h2>Start with a spark.</h2><p>Five suggestions, informed by your synthetic brand and inspiration library. Every idea stays a draft until you choose it.</p></div><span v-if="state.provider" class="provider-chip">{{state.provider}}</span></div>
      <div class="ai-controls"><label class="field"><span>Audience</span><input v-model="aiForm.audience"/></label><label class="field"><span>Tone of voice</span><input v-model="aiForm.tone"/></label><div class="field"><span>Channels</span><div class="channel-options"><button v-for="c in channels" :key="c" type="button" :class="{selected:aiForm.channels.includes(c)}" @click="toggleAi(c)">{{channelLabels[c]}} <b>{{aiForm.channels.includes(c)?'✓':'+'}}</b></button></div></div><button class="button button-primary generate-button" :disabled="state.aiLoading || !aiForm.channels.length" @click="generateIdeas({...aiForm,channels:[...aiForm.channels]})">{{state.aiLoading?'Generating ideas…':'✦ Generate five ideas'}}</button></div>
      <div v-if="state.sources.length" class="source-note">Knowledge context · {{state.sources.join(' · ')}} <small>Sources are synthetic; metrics are not verified.</small></div>
      <div v-if="state.suggestions.length" class="suggestion-grid"><article v-for="(idea,index) in state.suggestions" :key="idea.title+index" class="suggestion-card"><div class="idea-card-top"><span class="card-eyebrow">AI SUGGESTION 0{{index+1}}</span><span class="status-badge status-draft">Draft</span></div><h3>{{idea.title}}</h3><p>{{idea.topic}}</p><div class="suggestion-details"><span>Audience</span><strong>{{idea.targetAudience}}</strong></div><div class="channel-tags"><span v-for="c in idea.channels" :key="c">{{c}}</span></div><button class="button button-secondary add-button" :disabled="state.saving" @click="addSuggestion(idea)">Add to library <span>＋</span></button></article></div>
      <div v-else class="empty-state ai-empty"><span>✦</span><h3>Your next campaign starts here.</h3><p>Generate five draft suggestions. Nothing is saved until you choose it.</p></div>
    </section>
    <section v-else class="library-panel">
      <div class="library-toolbar"><label class="search-field"><span>⌕</span><input v-model="search" placeholder="Search titles, topics or audiences…"/></label><span>{{filtered.length}} ideas</span></div>
      <div v-if="filtered.length" class="idea-grid"><IdeaCard v-for="idea in filtered" :key="idea.id" :idea="idea" @open="openIdeaDetails"/></div>
      <div v-else class="empty-state"><span>⌕</span><h3>No ideas found</h3><p>Try another search or generate new suggestions.</p></div>
    </section>
    <div v-if="showForm" class="inline-form-panel"><div class="panel-heading"><div><span class="eyebrow">NEW CONTENT IDEA</span><h3>Give your idea a direction</h3></div><button class="icon-button" @click="showForm=false">×</button></div><form class="form-grid" @submit.prevent="submit"><label class="field"><span>Title</span><input v-model="form.title" required maxlength="160" placeholder="A fresh perspective on frames"/></label><label class="field"><span>Topic</span><input v-model="form.topic" required maxlength="255"/></label><label class="field"><span>Audience</span><input v-model="form.targetAudience" required/></label><label class="field"><span>Tone</span><input v-model="form.tone" required/></label><div class="field full-span"><span>Channels</span><div class="channel-options"><button v-for="c in channels" :key="c" type="button" :class="{selected:form.channels.includes(c)}" @click="toggleForm(c)">{{channelLabels[c]}} <b>{{form.channels.includes(c)?'✓':'+'}}</b></button></div></div><div class="form-actions"><button type="button" class="button button-secondary" @click="showForm=false">Cancel</button><button class="button button-primary" :disabled="state.saving || !form.channels.length">{{state.saving?'Saving…':'Save idea'}}</button></div></form></div>
  </section>
</template>
