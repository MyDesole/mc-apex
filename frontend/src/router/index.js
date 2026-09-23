import { createRouter, createWebHistory } from 'vue-router'

import HomeView from '../views/HomeView.vue'
import LoginView from '../views/LoginView.vue'
import RegisterView from '../views/RegisterView.vue'
import ProfileView from '../views/ProfileView.vue'
import PlayerView from '@/views/PlayerView.vue'
import PlayersView from "@/views/PlayersView.vue";

const router = createRouter({
  history: createWebHistory(),

  routes: [
    { path: '/', name: 'home', component: HomeView },
    { path: '/login', name: 'login', component: LoginView, meta: { guest: true } },
    { path: '/register', name: 'register', component: RegisterView, meta: { guest: true } },

    { path: '/profile', name: 'profile', component: ProfileView, meta: { auth: true } },
    {
      path: '/players',
      name: 'players',
      component: PlayersView,
      meta: { auth: true },
    },
    {
      path: '/friends',
      name: 'friends',
      component: () => import('@/views/FriendsView.vue'),
      meta: { auth: true },
    },
    {
      path: '/notifications',
      name: 'notifications',
      component: () => import('@/views/NotificationsView.vue'),
      meta: { auth: true },
    },

    { path: '/clans', name: 'clans', component: () => import('@/views/ClansView.vue') },
    { path: '/clans/create', name: 'clan-create', component: () => import('@/views/ClanCreateView.vue'), meta: { auth: true } },
    { path: '/clans/:id', name: 'clan', component: () => import('@/views/ClanView.vue') },
    { path: '/players/:id', name: 'player', component: PlayerView, meta: { auth: true } },
  ],
})

export default router