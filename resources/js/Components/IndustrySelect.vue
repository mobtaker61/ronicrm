<template>
    <div class="industry-select relative" ref="containerRef">
        <button
            type="button"
            @click.stop="open = !open"
            :disabled="disabled"
            class="w-full h-10 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm text-left rtl:text-right flex items-center justify-between bg-white"
        >
            <span :class="displayName ? 'text-gray-900' : 'text-gray-500'">{{ displayName || placeholder }}</span>
            <svg class="w-4 h-4 text-gray-400 flex-shrink-0 ltr:mr-2 rtl:ml-2" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div
            v-show="open"
            @click.stop
            class="absolute z-50 mt-1 w-full min-w-[220px] bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden"
            :class="dropdownClass"
        >
            <!-- Stage 2: subcategories + back -->
            <template v-if="currentParent">
                <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 border-b border-gray-200">
                    <button type="button" @click.stop="goBack" class="p-1 rounded hover:bg-gray-200 text-gray-600 rtl:rotate-180" title="Back">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                    </button>
                    <span class="text-sm text-gray-600 truncate flex-1">{{ currentParent.name }}</span>
                </div>
                <div class="max-h-60 overflow-y-auto py-1">
                    <button
                        type="button"
                        v-if="currentChildren.length"
                        @click.stop="select(currentParent.id)"
                        class="w-full px-3 py-2 text-left rtl:text-right text-sm hover:bg-blue-50 text-gray-700 flex items-center justify-between"
                    >
                        — {{ optionAllCategory }} —
                    </button>
                    <button
                        type="button"
                        v-for="c in currentChildren"
                        :key="c.id"
                        @click.stop="select(c.id)"
                        class="w-full px-3 py-2 text-left rtl:text-right text-sm hover:bg-blue-50 text-gray-700"
                    >
                        {{ c.name }}
                    </button>
                    <button
                        v-if="!currentChildren.length"
                        type="button"
                        @click.stop="select(currentParent.id)"
                        class="w-full px-3 py-2 text-left rtl:text-right text-sm hover:bg-blue-50 text-gray-700"
                    >
                        {{ currentParent.name }}
                    </button>
                </div>
            </template>

            <!-- Stage 1: root categories -->
            <template v-else>
                <div class="max-h-60 overflow-y-auto py-1">
                    <button
                        type="button"
                        @click.stop="chooseEmpty"
                        class="w-full px-3 py-2 text-left rtl:text-right text-sm hover:bg-blue-50 text-gray-500"
                    >
                        {{ placeholder }}
                    </button>
                    <button
                        type="button"
                        v-for="p in rootIndustries"
                        :key="p.id"
                        @click.stop.prevent="onParentClick(p)"
                        class="w-full px-3 py-2 text-left rtl:text-right text-sm hover:bg-blue-50 text-gray-700 flex items-center justify-between"
                    >
                        <span>{{ p.name }}</span>
                        <svg v-if="Array.isArray(p.children) && p.children.length" class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <span v-else class="w-4"></span>
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    modelValue: { type: [Number, String], default: null },
    industries: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Choose category...' },
    optionAllCategory: { type: String, default: 'All this category' },
    allowEmpty: { type: Boolean, default: true },
    disabled: { type: Boolean, default: false },
    dropdownClass: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const containerRef = ref(null);
const currentParent = ref(null);

const rootIndustries = computed(() => props.industries || []);

const flatMap = computed(() => {
    const map = {};
    const walk = (items, parentId = null) => {
        (items || []).forEach((item) => {
            map[item.id] = { parent_id: parentId, name: item.name };
            if (item.children?.length) walk(item.children, item.id);
        });
    };
    walk(props.industries);
    return map;
});

const displayName = computed(() => {
    const v = props.modelValue;
    if (v === null || v === undefined || v === '') return '';
    const id = typeof v === 'number' ? v : parseInt(v, 10);
    const info = flatMap.value[id];
    return info ? info.name : '';
});

const currentChildren = computed(() => {
    const kids = currentParent.value?.children;
    return Array.isArray(kids) ? kids : [];
});

function findParentById(id) {
    const find = (items) => {
        for (const item of items || []) {
            if (item.id === id) return item;
            const inChild = find(item.children);
            if (inChild) return inChild;
        }
        return null;
    };
    return find(props.industries);
}

function onParentClick(p) {
    const hasChildren = Array.isArray(p?.children) && p.children.length > 0;
    if (hasChildren) {
        currentParent.value = p;
    } else {
        select(p.id);
    }
}

function goBack() {
    currentParent.value = null;
}

function chooseEmpty() {
    if (props.allowEmpty) {
        emit('update:modelValue', null);
        open.value = false;
    }
}

function select(id) {
    emit('update:modelValue', id);
    open.value = false;
    currentParent.value = null;
}

function handleClickOutside(e) {
    if (containerRef.value && !containerRef.value.contains(e.target)) {
        open.value = false;
        currentParent.value = null;
    }
}

watch(open, (isOpen) => {
    if (!isOpen) currentParent.value = null;
});

onMounted(() => {
    document.addEventListener('click', handleClickOutside, true);
});
onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside, true);
});
</script>
