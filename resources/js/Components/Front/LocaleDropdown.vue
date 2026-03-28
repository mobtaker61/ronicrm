<template>
    <div v-if="locales.length > 1" ref="rootRef" class="relative">
        <button
            type="button"
            class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition-colors hover:border-gray-300 hover:bg-gray-50"
            :class="block ? 'w-full justify-between' : ''"
            :aria-expanded="open"
            aria-haspopup="listbox"
            :aria-label="ariaLabel"
            @click.stop="open = !open"
        >
            <span class="truncate">{{ triggerLabel }}</span>
            <svg
                class="w-3.5 h-3.5 shrink-0 text-gray-500 transition-transform"
                :class="{ 'rotate-180': open }"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <ul
                v-show="open"
                class="absolute z-[100] mt-1 max-h-64 min-w-[11rem] overflow-auto rounded-xl border border-gray-200 bg-white py-1 shadow-lg ring-1 ring-black/5"
                :class="[alignEnd ? 'end-0' : 'start-0', block ? 'w-full' : '']"
                role="listbox"
                :aria-label="ariaLabel"
                @click.stop
            >
                <li v-for="loc in locales" :key="loc.code" role="none">
                    <button
                        type="button"
                        role="option"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50"
                        :class="loc.code === currentLocale ? 'bg-blue-50 font-medium text-blue-800' : ''"
                        :aria-selected="loc.code === currentLocale"
                        @click="choose(loc.code)"
                    >
                        <span class="font-mono text-[11px] uppercase text-gray-500">{{ chip(loc.code) }}</span>
                        <span class="min-w-0 flex-1 truncate">{{ loc.name }}</span>
                    </button>
                </li>
            </ul>
        </Transition>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    locales: { type: Array, default: () => [] },
    currentLocale: { type: String, default: '' },
    ariaLabel: { type: String, default: 'Language' },
    /** Stretch trigger in narrow layouts (e.g. mobile drawer) */
    block: { type: Boolean, default: false },
    /** Align panel to the inline end (e.g. near “Get started” in LTR) */
    alignEnd: { type: Boolean, default: true },
});

const emit = defineEmits(['select']);

const open = ref(false);
const rootRef = ref(null);

const triggerLabel = computed(() => {
    const cur = props.locales.find((l) => l.code === props.currentLocale);
    if (cur?.name) {
        return cur.name;
    }
    return chip(props.currentLocale);
});

function chip(code) {
    const c = String(code || '');
    return c.length <= 4 ? c.toUpperCase() : c.slice(0, 4).toUpperCase();
}

function choose(code) {
    open.value = false;
    emit('select', code);
}

function onDocMouseDown(e) {
    if (!open.value || !rootRef.value) {
        return;
    }
    if (!rootRef.value.contains(e.target)) {
        open.value = false;
    }
}

function onKeydown(e) {
    if (e.key === 'Escape') {
        open.value = false;
    }
}

onMounted(() => {
    document.addEventListener('mousedown', onDocMouseDown);
    document.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', onDocMouseDown);
    document.removeEventListener('keydown', onKeydown);
});
</script>
