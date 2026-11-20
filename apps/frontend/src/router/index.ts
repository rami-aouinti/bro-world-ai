import { createRouter, createWebHistory } from 'vue-router'
import DashboardPage from '../pages/DashboardPage.vue'
import IdeasPage from '../pages/IdeasPage.vue'
import WeeklyPlanPage from '../pages/WeeklyPlanPage.vue'
import ReviewPage from '../pages/ReviewPage.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'dashboard', component: DashboardPage, meta: { title: 'Overview', description: 'Your content workspace at a glance.' } },
    { path: '/ideas', name: 'ideas', component: IdeasPage, meta: { title: 'Content studio', description: 'Create, discover and curate ideas for your next campaign.' } },
    { path: '/planning', name: 'planning', component: WeeklyPlanPage, meta: { title: 'Weekly planner', description: 'Balance priorities, reserve ideas and team capacity.' } },
    { path: '/review', name: 'review', component: ReviewPage, meta: { title: 'Review & approval', description: 'Keep every published handoff human-approved.' } },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
  scrollBehavior: () => ({ top: 0, behavior: 'smooth' }),
})
