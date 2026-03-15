<script setup lang="ts">
import { RouterView } from "vue-router";
import { useTheme } from "@/composables/useTheme";

// Initialize theme watcher on app mount
useTheme();
</script>

<template>
  <RouterView />
</template>
