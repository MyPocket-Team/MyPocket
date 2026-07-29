<script setup>
import { ref, computed } from "vue";

const props = defineProps({
  modelValue: { type: String, default: "" },
  placeholder: { type: String, default: "" },
  minlength: { type: [String, Number], default: null },
  required: { type: Boolean, default: false },
});

defineEmits(["update:modelValue"]);

const visible = ref(false);
const inputType = computed(() => (visible.value ? "text" : "password"));
</script>

<template>
  <div class="password-field">
    <input
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
      :type="inputType"
      :placeholder="placeholder"
      :minlength="minlength"
      :required="required"
      class="field"
    />
    <button
      type="button"
      class="toggle-visibility"
      :aria-label="visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
      @click="visible = !visible"
    >
      <svg v-if="visible" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
        <circle cx="12" cy="12" r="3" />
      </svg>
      <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
        <line x1="1" y1="1" x2="23" y2="23" />
      </svg>
    </button>
  </div>
</template>

<style scoped>
.password-field {
  position: relative;
  width: 100%;
}

.password-field .field {
  width: 100%;
  background-color: var(--color-bg-soft);
  border: 1.5px solid transparent;
  border-radius: 14px;
  padding: 15px 46px 15px 18px;
  font-size: 0.95rem;
  color: var(--color-ink);
  outline: none;
  transition: all 0.2s ease;
}

.password-field .field:focus {
  border-color: var(--color-primary);
  background-color: var(--color-paper-raised);
}

.toggle-visibility {
  position: absolute;
  top: 50%;
  right: 6px;
  transform: translateY(-50%);
  width: 34px;
  height: 34px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-ink-soft);
  border-radius: 10px;
}

.toggle-visibility:hover {
  color: var(--color-ink);
}
</style>
