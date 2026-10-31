<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { dungeonApi } from '@/services/pets/dungeon.js'
import AppIcon from '@/components/core/AppIcon.vue'
import PetFigure from '@/components/pets/PetFigure.vue'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import { PET_RARITY } from '@/data/players/petTraits.js'

/**
 * Экипировка питомцев: три слота на питомца, предметы из подземелий.
 *
 * Выбрал питомца — сразу видно, что можно надеть в каждый слот: список
 * предметов, а не выпадающий список. Поиск по имени помогает найти
 * питомца, когда их полтора десятка.
 */
const SLOTS = [
  { key: 'weapon', label: 'Оружие', icon: 'sword' },
  { key: 'armor', label: 'Доспех', icon: 'shield' },
  { key: 'trinket', label: 'Талисман', icon: 'gem' },
]

const STATS = [
  { key: 'atk', icon: 'sword', title: 'атака' },
  { key: 'def', icon: 'shield', title: 'защита' },
  { key: 'hp', icon: 'heart', title: 'здоровье' },
  { key: 'spd', icon: 'bolt', title: 'скорость' },
]

const hub = ref(null)
const busy = ref(false)
const error = ref('')
const petId = ref(null)
const query = ref('')

const pets = computed(() => hub.value?.pets ?? [])
const bag = computed(() => hub.value?.gear_bag ?? [])

/** Поиск по имени питомца. */
const found = computed(() => {
    const needle = query.value.trim().toLowerCase()

    if (!needle) return pets.value

    return pets.value.filter((row) => String(row.name).toLowerCase().includes(needle))
})

const pet = computed(
    () => pets.value.find((row) => row.inventory_id === petId.value) ?? found.value[0] ?? null,
)

/** Свободные предметы слота: не надетые ни на кого. */
const freeFor = (slot) => bag.value.filter((item) => item.slot === slot && !item.worn_by)

const totalGear = computed(() => bag.value.length)

async function load() {
    error.value = ''

    try {
        hub.value = await dungeonApi.overview()

        if (!petId.value && pets.value.length) petId.value = pets.value[0].inventory_id
    } catch (e) {
        error.value = e.message ?? 'Не удалось загрузить экипировку'
    }
}

async function act(call) {
    busy.value = true
    error.value = ''

    try {
        await call()
        await load()
    } catch (e) {
        error.value = e.message ?? 'Не получилось'
    } finally {
        busy.value = false
    }
}

const equip = (gearId) => {
    if (!pet.value) {
        error.value = 'Сначала выбери питомца'

        return
    }

    return act(() => dungeonApi.equip(pet.value.inventory_id, gearId))
}

const unequip = (slot) => act(() => dungeonApi.unequip(pet.value.inventory_id, slot))

/** Только ненулевые характеристики — чтобы не было «+0». */
const statList = (source) =>
    STATS.filter((stat) => source?.[stat.key]).map((stat) => ({ ...stat, value: source[stat.key] }))

onMounted(load)
</script>

<template>
    <section class="pg">
        <header class="pg__head">
            <div>
                <h2>Экипировка</h2>
                <p class="pg__sub">
                    Оружие, доспех и талисман падают в подземельях. Надетое работает
                    на арене и в подземельях.
                </p>
            </div>

            <div class="pg__head-side">
                <span class="pg__count">
                    <AppIcon icon="box" :size="14" />
                    {{ totalGear }}
                </span>
                <RouterLink to="/pets/dungeon" class="pg__go">
                    <AppIcon icon="rocket" :size="13" /> В подземелье
                </RouterLink>
            </div>
        </header>

        <p v-if="error" class="pg__error">{{ error }}</p>
        <p v-if="!pets.length" class="pg__hint">Питомцев пока нет — экипировку надевать не на кого.</p>

        <template v-else>
            <!-- Поиск и выбор питомца -->
            <div class="pg__bar">
                <label class="pg__search">
                    <AppIcon icon="target" :size="14" />
                    <input v-model="query" type="search" placeholder="Поиск по питомцу" />
                    <button v-if="query" type="button" class="pg__clear" @click="query = ''">
                        <AppIcon icon="close" :size="12" />
                    </button>
                </label>
                <span class="pg__found">найдено: {{ found.length }}</span>
            </div>

            <div v-if="found.length" class="pg__pets">
                <button
                    v-for="row in found"
                    :key="row.inventory_id"
                    type="button"
                    class="pg__pet"
                    :class="{ 'is-on': row.inventory_id === pet?.inventory_id }"
                    :style="{ '--rar': PET_RARITY[row.traits?.rarity]?.color || '#a8a3b8' }"
                    @click="petId = row.inventory_id"
                >
                    <PetFigure :species="row.species" :traits="row.traits" :size="34" :backdrop="false" />
                    <strong>{{ row.name }}</strong>
                    <small>ур. {{ row.level }}</small>
                    <span v-if="row.gear && Object.keys(row.gear).length" class="pg__pet-gear">
                        {{ Object.keys(row.gear).length }} / 3
                    </span>
                </button>
            </div>

            <p v-else class="pg__hint">Никого не нашли по запросу «{{ query }}».</p>

            <!-- Слоты выбранного питомца -->
            <div v-if="pet" class="pg__slots">
                <article v-for="slot in SLOTS" :key="slot.key" class="pg__slot">
                    <span class="pg__slot-label">
                        <AppIcon :icon="slot.icon" :size="13" />
                        {{ slot.label }}
                    </span>

                    <!-- Надето -->
                    <div v-if="pet.gear?.[slot.key]" class="pg__item">
                        <span class="pg__art">
                            <PixelSprite :sprite="pet.gear[slot.key].sprite || 'mob-rat'" :size="30" />
                        </span>
                        <div class="pg__item-body">
                            <strong>{{ pet.gear[slot.key].name }}</strong>
                            <small class="pg__rarity">{{ pet.gear[slot.key].rarity_name }}</small>
                            <div class="pg__chips">
                                <span v-for="stat in statList(pet.gear[slot.key])" :key="stat.key" class="pg__chip">
                                    <AppIcon :icon="stat.icon" :size="11" /> +{{ stat.value }}
                                </span>
                            </div>
                        </div>
                        <button type="button" class="pg__btn" :disabled="busy" @click="unequip(slot.key)">
                            снять
                        </button>
                    </div>

                    <!-- Что можно надеть: списком, без селекта -->
                    <template v-else-if="freeFor(slot.key).length">
                        <span class="pg__pick-label">надеть:</span>
                        <div class="pg__pick">
                            <button
                                v-for="item in freeFor(slot.key)"
                                :key="item.inventory_id"
                                type="button"
                                class="pg__pick-item"
                                :class="`pg__rar-${item.rarity}`"
                                :disabled="busy"
                                @click="equip(item.inventory_id)"
                            >
                                <PixelSprite :sprite="item.sprite || 'mob-rat'" :size="24" />
                                <span class="pg__pick-name">
                                    {{ item.name }}
                                    <small class="pg__rarity">{{ item.rarity_name }}</small>
                                </span>
                                <span class="pg__chips">
                                    <span v-for="stat in statList(item)" :key="stat.key" class="pg__chip">
                                        <AppIcon :icon="stat.icon" :size="11" /> +{{ stat.value }}
                                    </span>
                                </span>
                            </button>
                        </div>
                    </template>

                    <span v-else class="pg__empty">пусто — ищи в подземельях</span>
                </article>
            </div>

            <!-- Итоговый бонус -->
            <div v-if="pet" class="pg__bonus">
                <span>Бонус к характеристикам</span>
                <div class="pg__chips">
                    <span v-for="stat in statList(pet.bonus)" :key="stat.key" class="pg__chip pg__chip--hot">
                        <AppIcon :icon="stat.icon" :size="11" /> +{{ stat.value }}
                    </span>
                    <span v-if="!statList(pet.bonus).length" class="pg__empty">пока ничего не надето</span>
                </div>
            </div>
        </template>
    </section>
</template>

<style scoped>
.pg {
  display: flex;
  flex-direction: column;
  gap: 16px;

  padding: 22px;

  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 20px;

  background: rgba(255, 255, 255, 0.025);
}

.pg__head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.pg__head h2 {
  margin: 0 0 6px;

  color: #fff;

  font-family: var(--font-display);
  font-size: 22px;
}

.pg__sub {
  max-width: 60ch;
  margin: 0;

  color: rgba(255, 255, 255, 0.5);

  font-size: 13px;
  line-height: 1.5;
}

.pg__head-side {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.pg__count {
  display: inline-flex;
  align-items: center;
  gap: 6px;

  padding: 6px 12px;

  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 999px;

  color: rgba(255, 255, 255, 0.6);

  font-size: 12px;
  font-weight: 700;
}

/* Кнопка в подземелье — как «Ещё бой» на арене */
.pg__go {
  display: inline-flex;
  align-items: center;
  gap: 6px;

  min-height: 34px;
  padding: 0 12px;

  border: 1px solid rgba(249, 115, 22, 0.35);
  border-radius: 10px;

  background: rgba(249, 115, 22, 0.12);

  color: #fdba74;

  font-size: 12.5px;
  font-weight: 800;
  text-decoration: none;
}

.pg__go:hover {
  background: rgba(249, 115, 22, 0.22);
}

.pg__error {
  margin: 0;
  padding: 10px 14px;

  border: 1px solid rgba(248, 113, 113, 0.35);
  border-radius: 12px;

  background: rgba(248, 113, 113, 0.1);

  color: #fca5a5;

  font-size: 13px;
}

.pg__hint,
.pg__empty,
.pg__pick-label {
  color: rgba(255, 255, 255, 0.4);

  font-size: 12px;
}

/* Поиск */

.pg__bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
}

.pg__search {
  display: inline-flex;
  align-items: center;
  gap: 8px;

  flex: 1;
  min-width: 200px;

  padding: 8px 12px;

  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 12px;

  background: rgba(0, 0, 0, 0.32);

  color: rgba(255, 255, 255, 0.45);
}

.pg__search input {
  flex: 1;
  min-width: 0;

  border: 0;
  background: transparent;

  color: #fff;

  font: inherit;
  font-size: 13px;

  outline: none;
}

.pg__search input::placeholder {
  color: rgba(255, 255, 255, 0.32);
}

.pg__clear {
  display: inline-flex;
  padding: 2px;

  border: 0;
  background: transparent;

  color: rgba(255, 255, 255, 0.45);

  cursor: pointer;
}

.pg__found {
  color: rgba(255, 255, 255, 0.35);

  font-size: 11.5px;
}

/* Питомцы */

.pg__pets {
  display: flex;
  gap: 8px;

  padding-bottom: 6px;

  overflow-x: auto;

  scrollbar-width: thin;
}

.pg__pet {
  flex: 0 0 96px;

  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;

  padding: 10px 6px 8px;

  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 14px;

  background: var(--bg-card, #15131c);

  color: inherit;
  font: inherit;

  cursor: pointer;
}

.pg__pet.is-on {
  border-color: var(--rar);

  box-shadow: 0 0 0 1px var(--rar) inset;
}

.pg__pet strong {
  color: #fff;

  font-size: 12px;
  text-align: center;
}

.pg__pet small {
  color: rgba(255, 255, 255, 0.45);

  font-size: 10.5px;
}

.pg__pet-gear {
  color: #fdba74;

  font-size: 10px;
  font-weight: 700;
}

/* Слоты */

.pg__slots {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 10px;
}

.pg__slot {
  display: flex;
  flex-direction: column;
  gap: 8px;

  padding: 12px 14px;

  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 14px;

  background: rgba(0, 0, 0, 0.22);
}

.pg__slot-label {
  display: inline-flex;
  align-items: center;
  gap: 6px;

  color: rgba(255, 255, 255, 0.45);

  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.pg__item {
  display: flex;
  align-items: center;
  gap: 10px;
}

.pg__art {
  display: grid;
  place-items: center;

  width: 42px;
  height: 42px;

  border-radius: 10px;

  background: rgba(255, 255, 255, 0.05);
}

.pg__item-body {
  display: flex;
  flex-direction: column;
  gap: 3px;

  flex: 1;
  min-width: 0;
}

.pg__item-body strong {
  color: #fff;

  font-size: 13px;
}

.pg__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}

.pg__chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;

  padding: 2px 8px;

  border-radius: 999px;

  background: rgba(255, 255, 255, 0.07);

  color: rgba(255, 255, 255, 0.7);

  font-size: 11px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.pg__chip--hot {
  background: rgba(74, 222, 128, 0.14);

  color: #86efac;
}

/* Список доступных предметов вместо селекта */

.pg__pick {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.pg__pick-item {
  display: flex;
  align-items: center;
  gap: 10px;

  padding: 8px 10px;

  border: 1px solid rgba(255, 255, 255, 0.08);
  border-left: 3px solid rgba(255, 255, 255, 0.2);
  border-radius: 12px;

  background: rgba(255, 255, 255, 0.03);

  color: inherit;
  font: inherit;
  text-align: left;

  cursor: pointer;

  transition: background 0.14s ease, transform 0.14s ease;
}

.pg__pick-item:hover:not(:disabled) {
  transform: translateX(2px);

  background: rgba(255, 255, 255, 0.07);
}

.pg__pick-item:disabled {
  opacity: 0.5;

  cursor: default;
}

.pg__pick-name {
  flex: 1;
  min-width: 0;

  color: #fff;

  font-size: 12.5px;
  font-weight: 700;
}

.pg__rarity {
  display: block;

  color: rgba(255, 255, 255, 0.4);

  font-size: 10.5px;
  font-weight: 600;
  letter-spacing: 0.04em;
}

.pg__pick-item .pg__rarity {
  display: inline;
  margin-left: 6px;
}

.pg__rar-common { border-left-color: #94a3b8; }
.pg__rar-rare { border-left-color: #60a5fa; }
.pg__rar-epic { border-left-color: #c084fc; }
.pg__rar-legendary { border-left-color: #fbbf24; }

.pg__btn {
  padding: 6px 12px;

  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 10px;

  background: rgba(255, 255, 255, 0.06);

  color: #fff;

  font: inherit;
  font-size: 12px;
  font-weight: 700;

  cursor: pointer;
}

.pg__btn:hover:not(:disabled) {
  border-color: rgba(248, 113, 113, 0.45);

  background: rgba(248, 113, 113, 0.14);
}

.pg__btn:disabled {
  opacity: 0.5;

  cursor: default;
}

/* Бонус */

.pg__bonus {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;

  padding-top: 12px;

  border-top: 1px solid rgba(255, 255, 255, 0.07);

  color: rgba(255, 255, 255, 0.5);

  font-size: 12px;
  font-weight: 700;
}
</style>
