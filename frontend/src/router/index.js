import { createRouter, createWebHistory } from 'vue-router'
import { useTitleBlink } from '@/composables/core/useTitleBlink.js'

import HomeView from '@/views/players/HomeView.vue'
import LoginView from '@/views/auth/LoginView.vue'
import RegisterView from '@/views/auth/RegisterView.vue'
import ProfileView from '@/views/players/ProfileView.vue'
import PlayerView from '@/views/players/PlayerView.vue'
import PlayersView from "@/views/players/PlayersView.vue";
import FriendsView from "@/views/friends/FriendsView.vue";
import NotificationsView from "@/views/notifications/NotificationsView.vue";
import ClansView from "@/views/clan/ClansView.vue";
import ClanCreateView from "@/views/clan/ClanCreateView.vue";
import ShopView from '@/views/shop/ShopView.vue'
import InventoryView from '@/views/shop/InventoryView.vue'
import ClanView from "@/views/clan/ClanView.vue";
import ForumView from '@/views/forum/ForumView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'home', component: HomeView, meta: { title: 'Главная' } },
    { path: '/login', name: 'login', component: LoginView, meta: { title: 'Вход', guest: true } },
    {
      path: '/messages/:id?',
      name: 'messages',
      component: () => import('@/views/chat/Messages.vue'),
      meta: { title: 'Сообщения', requiresAuth: true },
    },
    { path: '/register', name: 'register', component: RegisterView, meta: { title: 'Регистрация', guest: true } },
    { path: '/profile', name: 'profile', component: ProfileView, meta: { title: 'Мой профиль', auth: true } },
    { path: '/players', name: 'players', component: PlayersView, meta: { title: 'Игроки', auth: true } },
    { path: '/players/:id', name: 'player', component: PlayerView, meta: { title: 'Профиль игрока', auth: true } },
    // Каноничная красивая ссылка на профиль: /user/Ник (или /user/3)
    { path: '/user/:id', name: 'user', component: PlayerView, meta: { title: 'Профиль игрока', auth: true } },
    { path: '/friends', name: 'friends', component: FriendsView, meta: { title: 'Друзья', auth: true } },
    { path: '/notifications', name: 'notifications', component: NotificationsView, meta: { title: 'Уведомления', auth: true } },
    { path: '/clans', name: 'clans', component: ClansView, meta: { title: 'Кланы' } },
    { path: '/news', name: 'news', component: () => import('@/views/news/NewsView.vue'), meta: { title: 'Новости' } },
    { path: '/my-clan', name: 'my-clan', component: () => import('@/views/clan/MyClanView.vue'), meta: { title: 'Мой клан', auth: true } },
    { path: '/forgot-password', name: 'forgot-password', component: () => import('@/views/auth/ForgotPasswordView.vue'), meta: { title: 'Восстановление пароля' } },
    { path: '/reset-password', name: 'reset-password', component: () => import('@/views/auth/ResetPasswordView.vue'), meta: { title: 'Сброс пароля' } },
    { path: '/verify-email', name: 'verify-email', component: () => import('@/views/auth/VerifyEmailView.vue'), meta: { title: 'Подтверждение почты', guest: true } },
    { path: '/news/:id', name: 'news-item', component: () => import('@/views/news/NewsItemView.vue'), meta: { title: 'Новость' } },
    { path: '/shop', name: 'shop', component: ShopView, meta: { title: 'Магазин' } },
    { path: '/inventory', name: 'inventory', component: InventoryView, meta: { title: 'Инвентарь', auth: true } },
    { path: '/wallet', name: 'wallet', component: () => import('@/views/shop/WalletView.vue'), meta: { title: 'Кошелёк', auth: true } },
    { path: '/forum', name: 'forum', component: ForumView, meta: { title: 'Форум' } },
    { path: '/forum/new', name: 'forum-new', component: () => import('@/views/forum/ForumNewView.vue'), meta: { title: 'Новая тема', auth: true } },
    // Без whereNumber: при обновлении страницы маршрут должен совпасть.
    // Регулярка в строке JS требует двойного экранирования (\\d), иначе это литерал «d».
    { path: '/forum/:id', name: 'forum-topic', component: () => import('@/views/forum/ForumTopicView.vue'), meta: { title: 'Тема форума' } },
    { path: '/clans/create', name: 'clan-create', component: ClanCreateView, meta: { title: 'Создать клан', auth: true } },
    { path: '/admin', name: 'admin', component: () => import('@/views/admin/AdminView.vue'), meta: { title: 'Админ-панель', auth: true, role: ['moderator', 'admin'] } },
    { path: '/clans/:id', name: 'clan', component: ClanView, meta: { title: 'Клан' } },
    // Каноничная красивая ссылка на клан: /clan/Имя_клана (или /clan/7)
    { path: '/clan/:id', name: 'clan-slug', component: ClanView, meta: { title: 'Клан' } },
    { path: '/tournaments', name: 'tournaments', component: () => import('@/views/tournaments/TournamentsView.vue'), meta: { title: 'Турниры' } },
    { path: '/tester', name: 'tester', component: () => import('@/views/tiers/TesterView.vue'), meta: { title: 'Тестер', auth: true, role: ['tester', 'admin'] } },
    { path: '/bridge-review', name: 'bridge-review', component: () => import('@/views/bridge/BridgeReviewView.vue'), meta: { title: 'Проверка бриджа', auth: true, role: ['bridge_tester', 'admin'] } },
    { path: '/bridge-curator', name: 'bridge-curator', component: () => import('@/views/bridge/BridgeCuratorView.vue'), meta: { title: 'Виды бриджа', auth: true, role: ['bridge_curator', 'admin'] } },
    { path: '/tournaments/:id', name: 'tournament', component: () => import('@/views/tournaments/TournamentView.vue'), meta: { title: 'Турнир', auth: true } },
  ],
})

const APP_NAME = 'APEX TIERS'
const { setBaseTitle } = useTitleBlink()

router.afterEach((to) => {
  const title = to.meta?.title
  setBaseTitle(title ? `${title} · ${APP_NAME}` : APP_NAME)
})

export default router