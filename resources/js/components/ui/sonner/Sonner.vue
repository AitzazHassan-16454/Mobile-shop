<script lang="ts" setup>
import type { ToasterProps } from "vue-sonner"
import { CircleCheckIcon, InfoIcon, Loader2Icon, OctagonXIcon, TriangleAlertIcon, XIcon } from "@lucide/vue"
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
    :close-button="true"
    :rich-colors="false"
    :visible-toasts="5"
    :close-button-position="'top-right'"
    :toast-options="{
      duration: 3000,
      classes: {
        toast: 'group toast',
        title: 'font-semibold',
        description: 'mt-0.5',
        actionButton: 'inline-flex items-center rounded-lg bg-slate-900 px-2.5 py-1 text-xs font-semibold text-white hover:bg-slate-800',
        cancelButton: 'inline-flex items-center rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50',
      },
    }"
    v-bind="restProps"
  >
    <template #error-icon>
      <OctagonXIcon class="h-5 w-5 text-red-500" />
    </template>
    <template #success-icon>
      <CircleCheckIcon class="h-5 w-5 text-emerald-500" />
    </template>
    <template #warning-icon>
      <TriangleAlertIcon class="h-5 w-5 text-amber-500" />
    </template>
    <template #info-icon>
      <InfoIcon class="h-5 w-5 text-blue-500" />
    </template>
    <template #loading-icon>
      <Loader2Icon class="h-5 w-5 animate-spin text-slate-400" />
    </template>
    <template #close-icon>
      <XIcon class="h-3.5 w-3.5" />
    </template>
  </Sonner>
</template>

<style>
/* Clean shadcn-style toasts that match the rest of the website UI */

[data-sonner-toaster] {
  --normal-bg: #ffffff;
  --normal-text: #0f172a;
  --normal-border: #e2e8f0;
  --border-radius: 0.75rem;
  --width: 380px;
  font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif;
  gap: 6px;
}

[data-sonner-toast][data-styled='true'] {
  position: relative;
  align-items: flex-start;
  gap: 0;
  padding: 12px 44px 12px 16px;
  border-radius: 0.75rem;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  color: #0f172a;
  box-shadow: 0 4px 16px -4px rgb(15 23 42 / 0.08), 0 2px 6px -2px rgb(15 23 42 / 0.04);
  transition: transform 250ms cubic-bezier(0.2, 0.8, 0.2, 1), box-shadow 250ms ease, opacity 250ms ease;
}

[data-sonner-toast][data-styled='true']:hover {
  box-shadow: 0 10px 28px -8px rgb(15 23 42 / 0.14), 0 4px 10px -4px rgb(15 23 42 / 0.06);
}

/* Icon */
[data-sonner-toast] [data-icon] {
  height: auto;
  width: auto;
  margin: 1px 10px 0 0;
}

/* Content */
[data-sonner-toast] [data-content] {
  flex: 1;
  min-width: 0;
}

[data-sonner-toast] [data-title] {
  color: #0f172a;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.35;
}

[data-sonner-toast] [data-description] {
  color: #64748b;
  font-size: 12px;
  font-weight: 400;
  line-height: 1.45;
  margin-top: 2px;
}

/* Close button */
[data-sonner-toast] [data-close-button] {
  height: 1.5rem;
  width: 1.5rem;
  color: #94a3b8;
  background: transparent;
  border: none;
  border-radius: 0.5rem;
  transition: background 150ms ease, color 150ms ease;
}

[data-sonner-toast] [data-close-button]:hover {
  background: #f1f5f9;
  color: #475569;
}

/* ERROR: light red alert styling so it clearly reads as an error */
[data-sonner-toast][data-type='error'] {
  background: #fef2f2;
  border-color: #fecaca;
  box-shadow: 0 4px 16px -4px rgb(239 68 68 / 0.12), 0 2px 6px -2px rgb(239 68 68 / 0.06);
}

[data-sonner-toast][data-type='error'] [data-title] {
  color: #991b1b;
}

[data-sonner-toast][data-type='error'] [data-description] {
  color: #b91c1c;
}

[data-sonner-toast][data-type='error'] [data-close-button] {
  color: #fca5a5;
}

[data-sonner-toast][data-type='error'] [data-close-button]:hover {
  background: #fee2e2;
  color: #b91c1c;
}
</style>