<script setup lang="ts">
import { computed } from 'vue';
import {
  ScrollAreaCorner,
  ScrollAreaRoot,
  ScrollAreaScrollbar,
  ScrollAreaThumb,
  ScrollAreaViewport,
} from 'radix-vue';
import { cn } from '@/lib/utils';

interface ScrollAreaProps {
  class?: string;
  orientation?: 'vertical' | 'horizontal' | 'both';
}

const props = withDefaults(defineProps<ScrollAreaProps>(), {
  orientation: 'vertical',
});

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props;

  return delegated;
});
</script>

<template>
  <ScrollAreaRoot
    v-bind="delegatedProps"
    :class="cn('relative overflow-hidden', props.class)"
  >
    <ScrollAreaViewport class="h-full w-full rounded-[inherit]">
      <slot />
    </ScrollAreaViewport>
    <ScrollAreaScrollbar
      v-if="orientation === 'vertical' || orientation === 'both'"
      class="flex touch-none select-none transition-colors"
      orientation="vertical"
    >
      <ScrollAreaThumb class="relative flex-1 rounded-full bg-border" />
    </ScrollAreaScrollbar>
    <ScrollAreaScrollbar
      v-if="orientation === 'horizontal' || orientation === 'both'"
      class="flex touch-none select-none transition-colors"
      orientation="horizontal"
    >
      <ScrollAreaThumb class="relative flex-1 rounded-full bg-border" />
    </ScrollAreaScrollbar>
    <ScrollAreaCorner />
  </ScrollAreaRoot>
</template>
