import { createRouter, createWebHistory } from 'vue-router'

import HomeView from '../views/HomeView.vue'
import LoginView from '../views/LoginView.vue'
import RegisterView from '../views/RegisterView.vue'
import ProfileView from '../views/ProfileView.vue'
import PlayerView from '@/views/PlayerView.vue'
import PlayersView from "@/views/PlayersView.vue";
import FriendsView from "@/views/FriendsView.vue";
import NotificationsView from "@/views/NotificationsView.vue";
import ClansView from "@/views/ClansView.vue";
import ClanCreateView from "@/views/ClanCreateView.vue";
import ClanView from "@/views/ClanView.vue";

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
      meta: { title: 'Главная' },
    },
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { title: 'Вход', guest: true },
    },
    {
      path: '/messages/:id?',
      name: 'messages',
      component: () => import('../views/Messages.vue'),
      meta: { title: 'Сообщения', requiresAuth: true },
    },
    {
      path: '/register',
      name: 'register',
      component: RegisterView,
      meta: { title: 'Регистрация', guest: true },
    },
    {
      path: '/profile',
      name: 'profile',
      component: ProfileView,
      meta: { title: 'Мой профиль', auth: true },
    },
    {
      path: '/players',
      name: 'players',
      component: PlayersView,
      meta: { title: 'Игроки', auth: true },
    },
    {
      path: '/players/:id',
      name: 'player',
      component: PlayerView,
      meta: { title: 'Профиль игрока', auth: true },
    },
    {
      path: '/friends',
      name: 'friends',
      component: FriendsView,
      meta: { title: 'Друзья', auth: true },
    },
    {
      path: '/notifications',
      name: 'notifications',
      component: NotificationsView,
      meta: { title: 'Уведомления', auth: true },
    },
    {
      path: '/clans',
      name: 'clans',
      component: ClansView,
      meta: { title: 'Кланы' },
    },
    {
      path: '/news',
      name: 'news',
      component: () => import('@/views/NewsView.vue'),
      meta: { title: 'Новости' },
    },
    {
      path: '/my-clan',
      name: 'my-clan',
      component: () => import('@/views/MyClanView.vue'),
      meta: { title: 'Мой клан', auth: true },
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: () => import('../views/ForgotPasswordView.vue'),
      meta: { title: 'Восстановление пароля' },
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: () => import('../views/ResetPasswordView.vue'),
      meta: { title: 'Сброс пароля' },
    },
    {
      path: '/verify-email',
      name: 'verify-email',
      component: () => import('@/views/VerifyEmailView.vue'),
      meta: { title: 'Подтверждение почты', guest: true },
    },
    {
      path: '/news/:id',
      name: 'news-item',
      component: () => import('@/views/NewsItemView.vue'),
      meta: { title: 'Новость' },
    },
    {
      path: '/clans/create',
      name: 'clan-create',
      component: ClanCreateView,
      meta: { title: 'Создать клан', auth: true },
    },
    {
      path: '/admin',
      name: 'admin',
      component: () => import('@/views/AdminView.vue'),
      meta: { title: 'Админ-панель', auth: true, role: ['moderator', 'admin'] },
    },
    {
      path: '/clans/:id',
      name: 'clan',
      component: ClanView,
      meta: { title: 'Клан' },
    },
    {
      path: '/tournaments',
      name: 'tournaments',
      component: () => import('@/views/TournamentsView.vue'),
      meta: { title: 'Турниры' },
    },
    {
      path: '/tester',
      name: 'tester',
      component: () => import('@/views/TesterView.vue'),
      meta: { title: 'Тестер', auth: true, role: ['tester', 'admin'] },
    },
    {
      path: '/tournaments/:id',
      name: 'tournament',
      component: () => import('@/views/TournamentView.vue'),
      meta: { title: 'Турнир', auth: true },
    },
  ],
})

const APP_NAME = 'APEX TIERS'

router.afterEach((to) => {
  const title = to.meta?.title
  document.title = title ? `${title} · ${APP_NAME}` : APP_NAME
})

export default router