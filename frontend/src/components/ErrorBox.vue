<script setup>
import { computed } from 'vue'

const props = defineProps({ error: { type: Object, default: null } })

const details = computed(() => Object.values(props.error?.errors ?? {}).flat())
</script>

<template>
  <div v-if="error" class="alert alert-error" role="alert">
    <strong>{{ error.message }}</strong>
    <ul v-if="details.length">
      <li v-for="(detail, i) in details" :key="i">{{ detail }}</li>
    </ul>
  </div>
</template>
