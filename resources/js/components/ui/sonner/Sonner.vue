<script lang="ts" setup>
import type { ToasterProps } from "vue-sonner"
import { CircleCheckIcon, InfoIcon, Loader2Icon, OctagonXIcon, TriangleAlertIcon } from "@lucide/vue"
import { Toaster as Sonner } from "vue-sonner"
import { cn } from "@/lib/utils"

import 'vue-sonner/style.css';

const props = withDefaults(defineProps<ToasterProps>(), {
  position: 'top-center',
})

const {
  class: cls,
  closeButton: _closeButton,
  closeButtonPosition: _closeButtonPosition,
  richColors: _richColors,
  visibleToasts: _visibleToasts,
  toastOptions: _toastOptions,
  ...restProps
} = props
</script>

<template>
  <Sonner
    :class="cn('toaster group', cls)"
    :close-button="false"
    :rich-colors="false"
    :visible-toasts="3"
    :toast-options="{
      duration: 2500,
      classes: {
        toast: 'group toast',
        title: 'font-semibold',
      },
    }"
    v-bind="restProps"
  >
    <template #error-icon>
      <OctagonXIcon class="h-4 w-4 shrink-0 text-white" />
    </template>
    <template #success-icon>
      <CircleCheckIcon class="h-4 w-4 shrink-0 text-white" />
    </template>
    <template #warning-icon>
      <TriangleAlertIcon class="h-4 w-4 shrink-0 text-slate-900" />
    </template>
    <template #info-icon>
      <InfoIcon class="h-4 w-4 shrink-0 text-white" />
    </template>
    <template #loading-icon>
      <Loader2Icon class="h-4 w-4 animate-spin text-white" />
    </template>
  </Sonner>
</template>

<style>
/* Simple Pill Toast styling with Green for Success and Red for Error */

[data-sonner-toaster] {
  --width: auto;
  font-family: 'Outfit', 'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif;
  gap: 8px;
  max-width: 90vw;
}

[data-sonner-toast][data-styled='true'] {
  position: relative;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 8px !important;
  padding: 8px 18px !important;
  min-height: 40px !important;
  border-radius: 9999px !important; /* Pill shape */
  border: 1px solid rgba(255, 255, 255, 0.2) !important;
  background: #16a34a !important; /* Green for Success */
  color: #ffffff !important;
  box-shadow: 0 8px 25px -4px rgba(22, 163, 74, 0.35), 0 3px 8px -2px rgba(0, 0, 0, 0.08) !important;
  transition: all 200ms cubic-bezier(0.16, 1, 0.3, 1) !important;
  white-space: nowrap !important;
  width: auto !important;
}

/* Success & Default Toasts (Vibrant Rich Green) */
[data-sonner-toast],
[data-sonner-toast][data-type='success'],
[data-sonner-toast][data-type='default'],
[data-sonner-toast][data-type='info'] {
  background: #16a34a !important;
  color: #ffffff !important;
  border-color: rgba(255, 255, 255, 0.2) !important;
  box-shadow: 0 8px 25px -4px rgba(22, 163, 74, 0.35) !important;
}

[data-sonner-toast][data-type='success'] [data-title],
[data-sonner-toast][data-type='default'] [data-title],
[data-sonner-toast][data-type='info'] [data-title] {
  color: #ffffff !important;
}

/* Warning Toasts (Warm Amber/Mustard Gold) */
[data-sonner-toast][data-type='warning'] {
  background: #d4a328 !important;
  color: #1c1917 !important;
  border-color: rgba(0, 0, 0, 0.08) !important;
  box-shadow: 0 8px 20px -4px rgba(212, 163, 40, 0.35) !important;
}

[data-sonner-toast][data-type='warning'] [data-title] {
  color: #1c1917 !important;
  font-weight: 700 !important;
}

/* Error Toasts (Real Error Red Pill) */
[data-sonner-toast][data-type='error'] {
  background: #dc2626 !important;
  color: #ffffff !important;
  border-color: rgba(255, 255, 255, 0.2) !important;
  box-shadow: 0 8px 20px -4px rgba(220, 38, 38, 0.35) !important;
}

[data-sonner-toast][data-type='error'] [data-title] {
  color: #ffffff !important;
}

/* Toast Icon */
[data-sonner-toast] [data-icon] {
  margin: 0 !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
}

/* Content Container */
[data-sonner-toast] [data-content] {
  display: flex !important;
  align-items: center !important;
  flex: none !important;
}

/* Title text */
[data-sonner-toast] [data-title] {
  font-size: 13px !important;
  font-weight: 600 !important;
  line-height: 1 !important;
}

/* Hide multi-line sub-description to keep simple 1-line pill toast */
[data-sonner-toast] [data-description] {
  display: none !important;
}

/* Close button hidden for simple clean pill look */
[data-sonner-toast] [data-close-button] {
  display: none !important;
}
</style>