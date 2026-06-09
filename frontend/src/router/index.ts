import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

import AppLayout from '../components/AppLayout.vue'
import LoginView from '../views/LoginView.vue'
import SkillTypesView from '../views/SkillTypesView.vue'
import SkillsView from '../views/SkillsView.vue'
import StaffsView from '../views/StaffsView.vue'
import EventsView from '../views/EventsView.vue'
import SurveysView from '../views/SurveysView.vue'
import SurveyDetailView from '../views/SurveyDetailView.vue'
import SurveyMatrixView from '../views/SurveyMatrixView.vue'
import SurveyResponseView from '../views/SurveyResponseView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', component: LoginView },
    { path: '/survey/:token', component: SurveyResponseView },
    {
      path: '/',
      component: AppLayout,
      meta: { requiresAuth: true },
      children: [
        { path: '', redirect: '/skill-types' },
        { path: 'skill-types', component: SkillTypesView },
        { path: 'skills', component: SkillsView },
        { path: 'staffs', component: StaffsView },
        { path: 'events', component: EventsView },
        { path: 'surveys', component: SurveysView },
        { path: 'surveys/:id', component: SurveyDetailView },
        { path: 'surveys/:id/matrix', component: SurveyMatrixView, meta: { fullWidth: true } },
      ],
    },
  ],
})

router.beforeEach(to => {
  const auth = useAuthStore()
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return '/login'
  }
})

export default router