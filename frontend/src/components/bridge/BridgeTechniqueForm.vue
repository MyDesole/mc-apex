<script setup>
/**
 * Подтверждение вида бриджа.
 *
 * Сделано по образцу записи на тир-тест: та же модалка, тот же порядок
 * полей. Отличие в том, что вместо контактов игрок выбирает, какие подвиды
 * показывает, и прикладывает ролик.
 *
 * Классы с префиксом bridge-: CSS из @import попадает в глобальную область,
 * и общие имена вроде .modal подрались бы с модалкой тир-теста.
 */
import { computed, ref, watch } from 'vue'
import { bridgeApi } from '@/services/bridge/bridge.js'
import BridgeVideoUploader from '@/components/bridge/BridgeVideoUploader.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'created'])

const loading = ref(false)
const submitting = ref(false)
const error = ref('')
const success = ref(false)

const form = ref({ technique_id: null, upload_id: null, variants: [] })
const variants = ref([])
const catalog = ref([])

/** Виды, которые можно заявить: не подтверждённые. */
const available = computed(() =>
    catalog.value.filter(row => !row.submission || row.submission.status === 'rejected'),
)

const selectedTechnique = computed(() =>
    available.value.find(row => row.technique.id === form.value.technique_id) ?? null,
)

/** Каталог нужен, чтобы показать, какие виды ещё можно заявить. */
async function loadCatalog() {
  loading.value = true

  try {
    const data = await bridgeApi.techniques()

    catalog.value = data.data ?? []
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить виды бриджа'
  } finally {
    loading.value = false
  }
}

// Сброс и загрузка при каждом открытии
watch(() => props.modelValue, async (open) => {
  if (!open) return

  success.value = false
  error.value = ''

  await loadCatalog()

  form.value = {
    technique_id: available.value[0]?.technique?.id ?? null,
    upload_id: null,
    variants: [],
  }

  variants.value = (selectedTechnique.value?.technique?.variants ?? []).map(v => ({ ...v }))
})

// При смене вида пересобираем список подвидов
watch(() => form.value.technique_id, () => {
  form.value.variants = []
  variants.value = (selectedTechnique.value?.technique?.variants ?? []).map(v => ({ ...v }))
}, { immediate: true })

function toggleVariant(variant) {
  const id = variant.id

  form.value.variants = form.value.variants.includes(id)
      ? form.value.variants.filter(v => v !== id)
      : [...form.value.variants, id]
}

function close() {
  emit('update:modelValue', false)
}

async function submit() {
  if (!form.value.technique_id || !form.value.upload_id) return

  submitting.value = true
  error.value = ''

  try {
    await bridgeApi.declare(form.value.technique_id, form.value.upload_id, form.value.variants)

    success.value = true
    emit('created')
  } catch (e) {
    error.value = e.message || 'Не удалось отправить заявку'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <!-- ===== МОДАЛКА ПОДТВЕРЖДЕНИЯ ВИДА ===== -->
    <div
        v-if="modelValue && !success"
        class="bridge-modal-bg"
        @click.self="close"
    >
      <div class="bridge-modal">
        <header class="bridge-modal__head">
          <h2>Подтверждение вида</h2>

          <button class="bridge-modal__close" type="button" @click="close" aria-label="Закрыть">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 6 6 18M6 6l12 12" />
            </svg>
          </button>
        </header>

        <div class="bridge-modal__body">
          <div v-if="error" class="bridge-modal__error">{{ error }}</div>

          <div v-if="!available.length" class="bridge-modal__empty">
            Все виды уже заявлены или подтверждены
          </div>

          <template v-else>
            <div class="bridge-field">
              <label>Вид бриджа</label>

              <select v-model.number="form.technique_id">
                <option
                    v-for="row in available"
                    :key="row.technique.id"
                    :value="row.technique.id"
                >
                  {{ row.technique.label }}
                </option>
              </select>
            </div>

            <!-- Подвиды: игрок сам отмечает, что показывает в ролике -->
            <div v-if="variants.length" class="bridge-field">
              <label>Подвиды</label>

              <div class="bridge-variants">
                <button
                    v-for="variant in variants"
                    :key="variant.id"
                    type="button"
                    class="bridge-variant"
                    :class="{
                      'bridge-variant--on': form.variants.includes(variant.id),
                      'bridge-variant--special': variant.is_special,
                    }"
                    @click="toggleVariant(variant)"
                >
                  <span class="bridge-variant__mark">
                    {{ form.variants.includes(variant.id) ? '✓' : '+' }}
                  </span>
                  {{ variant.label }}
                </button>
              </div>

              <small class="bridge-field__hint">
                Отметь те, что показываешь в ролике
              </small>
            </div>

            <div class="bridge-field">
              <label>Видео</label>

              <BridgeVideoUploader v-model="form.upload_id" />
            </div>

            <!-- Требования к ролику: не отклонить нельзя, поэтому акцентно -->
            <aside class="bridge-requirements">
              <div class="bridge-requirements__title">
                <span class="bridge-requirements__icon">!</span>
                Требования к видео
              </div>

              <ul class="bridge-requirements__list">
                <li>Записано <b>на сервере</b>, не в одиночном мире</li>
                <li>Длительность <b>около 2 минут</b></li>
                <li><b>Неудачи обрезать нельзя</b></li>
                <li>Должен быть виден <b>CPS-мод и Keystrokes</b></li>
                <li>Несколько бриджей — <b>отдельный ролик на каждый</b></li>
              </ul>

              <p class="bridge-requirements__warn">
                За подозрение в читах, монтаже или чужом клипе вызовем на проверку.
              </p>
            </aside>

            <div class="bridge-modal__actions">
              <button type="button" class="bridge-modal__cancel" @click="close">
                Отмена
              </button>

              <button
                  type="button"
                  class="bridge-modal__submit"
                  :disabled="submitting || !form.technique_id || !form.upload_id"
                  @click="submit"
              >
                {{ submitting ? 'Отправка...' : 'Отправить на подтверждение' }}
              </button>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- ===== УСПЕХ ===== -->
    <div
        v-else-if="success"
        class="bridge-modal-bg"
        @click.self="close"
    >
      <div class="bridge-modal bridge-modal--done">
        <div class="bridge-done">
          <div class="bridge-done__icon">✓</div>

          <h2>Заявка отправлена</h2>

          <p>
            Бридж-тестер посмотрит ролик и подтвердит вид.
            До этого он будет отмечен как «на проверке».
          </p>

          <button type="button" class="bridge-modal__submit" @click="close">
            Понятно
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
@import "@/components/bridge/BridgeTechniqueForm.css";
</style>
