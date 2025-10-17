<script setup>
import { onMounted, ref } from 'vue'
import ProductData from './ProductData.vue'


</script>

<template>
  <div
      style="display:flex; align-items: stretch; justify-content: flex-start; gap: 20px;">
    <div style="width: 20%; padding: 20px; border-right: 1px solid #000;">
      <h3>Свойства товара</h3>
    </div>

    <div style="width: 80%; padding: 20px;">
      <ProductData></ProductData>
    </div>
  </div>
</template>
