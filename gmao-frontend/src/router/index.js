import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { guest: true },
    },
    {
      path: '/',
      name: 'dashboard',
      component: () => import('@/views/DashboardView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/sites',
      name: 'sites',
      component: () => import('@/views/SitesView.vue'),
      meta: { requiresAuth: true, permission: 'site:view_any' },
    },
    {
      path: '/equipments',
      name: 'equipments',
      component: () => import('@/views/EquipmentsView.vue'),
      meta: { requiresAuth: true, permission: 'equipment:view_any' },
    },
    {
      path: '/locations',
      name: 'locations',
      component: () => import('@/views/LocationsView.vue'),
      meta: { requiresAuth: true, permission: 'location:view_any' },
    },
    {
      path: '/parts',
      name: 'parts',
      component: () => import('@/views/PartsView.vue'),
      meta: { requiresAuth: true, permission: 'part:view_any' },
    },
    {
      path: '/work-orders',
      name: 'work-orders',
      component: () => import('@/views/WorkOrdersView.vue'),
      meta: { requiresAuth: true, permission: 'workorder:view_any' },
    },
    {
      path: '/users',
      name: 'users',
      component: () => import('@/views/UsersView.vue'),
      meta: { requiresAuth: true, permission: 'user:view_any' },
    },
    {
      path: '/intervention-requests',
      name: 'intervention-requests',
      component: () => import('@/views/InterventionRequestsView.vue'),
      meta: { requiresAuth: true, permission: 'intervention_request:view_any' },
    },
    {
      path: '/preventive-maintenance',
      name: 'preventive-maintenance',
      component: () => import('@/views/PreventiveMaintenanceView.vue'),
      meta: { requiresAuth: true, permission: 'preventive:view_any' },
    },
    {
      path: '/reports',
      name: 'reports',
      component: () => import('@/views/ReportsView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/notifications',
      name: 'notifications',
      component: () => import('@/views/NotificationsView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/drivers',
      name: 'drivers',
      component: () => import('@/views/DriversView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/trucks',
      name: 'trucks',
      component: () => import('@/views/TrucksView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/clients',
      name: 'clients',
      component: () => import('@/views/ClientsView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/habilitations',
      name: 'habilitations',
      component: () => import('@/views/HabilitationsView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/assignments',
      name: 'assignments',
      component: () => import('@/views/AssignmentsView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/roles',
      name: 'roles',
      component: () => import('@/views/RolesPermissionsView.vue'),
      meta: { requiresAuth: true, permission: 'role:view_any' },
    },
    {
      path: '/settings',
      name: 'settings',
      component: () => import('@/views/SettingsView.vue'),
      meta: { requiresAuth: true, permission: 'setting:view_any' },
    },
  ],
})

let isCheckingAuth = false

// Navigation guard
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  if (to.meta.guest) {
    if (authStore.isAuthenticated) {
      return next('/')
    }
    return next()
  }

  if (to.meta.requiresAuth) {
    if (!authStore.user && !isCheckingAuth) {
      isCheckingAuth = true
      await authStore.fetchUser()
      isCheckingAuth = false
    }

    if (!authStore.isAuthenticated) {
      return next('/login')
    }

    // Vérifier permission : exacte OU view_own OU view en fallback
    if (to.meta.permission) {
      const perm = to.meta.permission
      const basePerm = perm.replace(':view_any', '')

      const hasAccess =
        authStore.hasPermission(perm) ||
        authStore.hasPermission(`${basePerm}:view_own`) ||
        authStore.hasPermission(`${basePerm}:view`)

      if (!hasAccess) {
        return next('/')
      }
    }
  }

  next()
})

export default router
