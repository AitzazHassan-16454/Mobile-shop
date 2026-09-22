<script setup lang="ts">
import type { DropdownMenuCheckboxItemEmits, DropdownMenuCheckboxItemProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { Check } from "@lucide/vue"
import { reactiveOmit } from "@vueuse/core"
import {
  DropdownMenuCheckboxItem,
  DropdownMenuItemIndicator,
  useForwardPropsEmits,
} from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<DropdownMenuCheckboxItemProps & { class?: HTMLAttributes["class"] }>()
const emits = defineEmits<DropdownMenuCheckboxItemEmits>()

const delegatedProps = reactiveOmit(props, "class")

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <DropdownMenuCheckboxItem
    data-slot="dropdown-menu-checkbox-item"
    v-bind="forwarded"
    :class="cn(
      'focus:bg-gray-100 dark:focus:bg-gray-800 relative flex cursor-pointer items-center justify-between gap-3 rounded-lg px-2.5 py-2 text-xs font-medium text-gray-800 dark:text-gray-200 outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 transition-colors',
      props.class,
    )"
  >
    <span><slot /></span>
    <span class="flex size-4 shrink-0 items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 transition-colors group-data-[state=checked]:border-[#003B7D] group-data-[state=checked]:bg-[#003B7D] group-data-[state=checked]:text-white">
      <DropdownMenuItemIndicator>
        <slot name="indicator-icon">
          <Check class="size-3.5 stroke-[3] text-[#003B7D] dark:text-blue-400" />
        </slot>
      </DropdownMenuItemIndicator>
    </span>
  </DropdownMenuCheckboxItem>
</template>
