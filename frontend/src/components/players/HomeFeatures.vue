<script setup>
/**
 * Что есть на APEX — для гостей главной: разделы сайта карточками
 * с пиксельными картинками и кнопкой регистрации.
 */
import { RouterLink } from 'vue-router'
import PixelSprite from '@/components/shop/PixelSprite.vue'

const FEATURES = [
  { sprite: 'crown', color: '#facc15', title: 'Тиры и рейтинг', text: 'Тестеры оценивают твою игру — получаешь тир от E до S и место в общем рейтинге.', to: '/rules' },
  { sprite: 'warden', color: '#2dd4bf', title: 'Кланы и войны', text: 'Собери команду, отмечайся каждый день, качай уровень клана и вызывай соперников.', to: '/clans' },
  { sprite: 'totem', color: '#4ade80', title: 'Турниры', text: 'Регулярные турниры с сеткой, трансляциями и призами в ApexCoin.', to: '/tournaments' },
  { sprite: 'cat', color: '#fb923c', title: 'Питомцы', text: 'Уникальные питомцы с номером и чертами: окрас, фон, аксессуар. Корми, качай уровень.', to: '/shop' },
  { sprite: 'flame', color: '#f97316', title: 'Аукцион', text: 'Торгуй питомцами со ставками в реальном времени и чатом у каждого лота.', to: '/auction' },
  { sprite: 'diamond', color: '#22d3ee', title: 'Шахтёр', text: 'Мини-игра: копай вглубь за руду и монеты, но осторожно с TNT.', to: '/miner' },
  { sprite: 'heart', color: '#f43f5e', title: 'Студия профиля', text: 'Собери профиль из блоков: шапка, шрифты, фоны, эффекты и косметика из магазина.', to: '/register' },
  { sprite: 'skull', color: '#a78bfa', title: 'Сундуки и косметика', text: 'Бесплатный сундук каждый день, рамки, эффекты ника, бейджи и титулы.', to: '/cases' },
]
</script>

<template>
  <section class="hf">
    <header class="hf__head">
      <span class="hf__kicker">Что есть на APEX</span>
      <h2>Не только рейтинг</h2>
      <p>Тир-тесты, кланы и турниры — и вокруг них целый мир: питомцы, торги, мини-игры и профиль, который собираешь сам.</p>
    </header>

    <div class="hf__grid">
      <RouterLink
          v-for="f in FEATURES"
          :key="f.title"
          :to="f.to"
          class="hf-card"
          :style="{ '--c': f.color }"
      >
        <span class="hf-card__art"><PixelSprite :sprite="f.sprite" :size="30" anim="bounce" /></span>
        <strong>{{ f.title }}</strong>
        <span>{{ f.text }}</span>
      </RouterLink>
    </div>

    <div class="hf__cta">
      <p>Регистрация бесплатная, а сундук с косметикой можно открывать бесплатно каждый день.</p>
      <RouterLink to="/register" class="btn btn-primary">Создать аккаунт <span aria-hidden="true">→</span></RouterLink>
    </div>
  </section>
</template>

<style scoped>
.hf { display: flex; flex-direction: column; gap: 16px; }

.hf__kicker { color: #c4b5fd; font-size: 12px; font-weight: 800; letter-spacing: 0.14em; text-transform: uppercase; }
.hf__head h2 { margin: 6px 0 4px; color: #fff; font-family: var(--font-display); font-size: clamp(24px, 3.4vw, 34px); line-height: 1.1; }
.hf__head p { max-width: 620px; margin: 0; color: #8a8598; font-size: 14px; line-height: 1.55; }

.hf__grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}

.hf-card {
  display: flex;
  flex-direction: column;
  gap: 6px;

  padding: 16px;

  color: inherit;
  background:
      radial-gradient(circle at 0 0, color-mix(in srgb, var(--c) 16%, transparent), transparent 55%),
      var(--bg-card, #15131c);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 16px;

  text-decoration: none;
  transition: transform 0.15s ease, border-color 0.15s ease;
}

.hf-card:hover { border-color: color-mix(in srgb, var(--c) 55%, transparent); transform: translateY(-3px); }

.hf-card__art {
  width: 50px;
  height: 50px;
  margin-bottom: 4px;

  display: grid;
  place-items: center;

  background: color-mix(in srgb, var(--c) 14%, rgba(0, 0, 0, 0.3));
  border: 1px solid color-mix(in srgb, var(--c) 35%, transparent);
  border-radius: 12px;
}

.hf-card strong { color: #fff; font-size: 15px; }
.hf-card > span:last-child { color: #8a8598; font-size: 13px; line-height: 1.45; }

.hf__cta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;

  padding: 16px 18px;

  background: linear-gradient(90deg, rgba(139, 92, 246, 0.16), rgba(139, 92, 246, 0.04));
  border: 1px solid rgba(139, 92, 246, 0.3);
  border-radius: 16px;
}

.hf__cta p { margin: 0; color: #d6d2e2; font-size: 14px; }

@media (max-width: 980px) { .hf__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

@media (max-width: 560px) {
  .hf__grid { grid-template-columns: minmax(0, 1fr); }
  .hf__cta { flex-direction: column; align-items: stretch; text-align: center; }
}
</style>
