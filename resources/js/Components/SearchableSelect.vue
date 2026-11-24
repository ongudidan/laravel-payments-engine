<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useElementBounding, useWindowSize } from '@vueuse/core';
import { Check, ChevronDown, Loader2, Search, X } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue?: string | number | Record<string, any> | unknown[] | null;
        options: any[];
        label?: string | ((option: any) => string);
        valueKey?: string;
        placeholder?: string;
        searchable?: boolean;
        clearable?: boolean;
        disabled?: boolean;
        loading?: boolean;
        multiple?: boolean;
        selectedFirst?: boolean;
        error?: string | null | any;
        align?: 'left' | 'right';
        class?: string;
        teleport?: boolean;
        maxDisplay?: number;
    }>(),
    {
        modelValue: null,
        label: 'label',
        valueKey: 'id',
        placeholder: 'Select option...',
        searchable: true,
        clearable: true,
        disabled: false,
        loading: false,
        multiple: false,
        selectedFirst: true,
        error: null,
        align: 'left',
        class: '',
        teleport: true,
        maxDisplay: 20,
    },
);

const emit = defineEmits(['update:modelValue', 'search']);

const isOpen = ref(false);
const searchQuery = ref('');
const selectRef = ref<HTMLElement | null>(null);
const dropdownRef = ref<HTMLElement | null>(null);
const searchInputRef = ref<HTMLInputElement | null>(null);
let removeNavigationListener: (() => void) | null = null;
watch(searchQuery, (val) => {
    emit('search', val);
});

// Use VueUse for bounding box calculations
const { top, left, width, bottom, update } = useElementBounding(selectRef);
const { height: windowHeight, width: windowWidth } = useWindowSize();

const dropdownStyle = computed(() => {
    if (!isOpen.value) return {};

    // Check if there's enough space below, otherwise flip up
    const spaceBelow = windowHeight.value - bottom.value;
    const minHeight = 300; // Accurate max height of dropdown + buffer
    const flip = spaceBelow < minHeight && top.value > minHeight;

    if (props.teleport) {
        return {
            top: flip ? 'auto' : `${bottom.value + 4}px`, // Viewport relative
            bottom: flip ? `${windowHeight.value - top.value + 4}px` : 'auto',
            left: `${left.value}px`,
            minWidth: `${width.value}px`,
            width: 'max-content',
            maxWidth: `${Math.max(width.value, windowWidth.value - left.value - 16)}px`, // Ensure it doesn't go off-screen
            position: 'fixed' as const,
            zIndex: 9999,
            pointerEvents: 'auto' as const,
        };
    } else {
        return {
            top: flip ? 'auto' : '100%',
            bottom: flip ? '100%' : 'auto',
            left: '0px',
            minWidth: '100%',
            width: 'max-content',
            maxWidth: '200%', // Limit relative expansion
            position: 'absolute' as const,
            zIndex: 50,
            marginTop: flip ? '0' : '4px',
            marginBottom: flip ? '4px' : '0',
        };
    }
});

function isSelected(option: any) {
    if (option === null || option === undefined) return false;
    const value =
        typeof option === 'object' ? option[props.valueKey] : option;

    if (props.multiple) {
        return (
            Array.isArray(props.modelValue) &&
            props.modelValue.some((v) => v == value)
        );
    }

    return props.modelValue == value;
}

const selectedOptions = computed(() => {
    if (!props.modelValue) return props.multiple ? [] : null;
    const opts = Array.isArray(props.options) ? props.options : [];

    if (props.multiple) {
        if (!Array.isArray(props.modelValue)) return [];
        return opts.filter((opt) => {
            const optValue =
                typeof opt === 'object' ? (opt as any)[props.valueKey] : opt;
            return (props.modelValue as any[]).some((v) => v == optValue);
        });
    }

    return opts.find((opt) => {
        const optValue =
            typeof opt === 'object' ? (opt as any)[props.valueKey!] : opt;
        return optValue == props.modelValue;
    });
});

const getOptionLabel = (option: any): string => {
    if (option === null || option === undefined) return '';
    if (typeof props.label === 'function') {
        return props.label(option);
    }
    if (typeof option === 'object') {
        const key = props.label || 'label';
        return option[key] !== undefined && option[key] !== null ? String(option[key]) : '';
    }
    return String(option);
};

const displayText = computed(() => {
    if (props.loading) return 'Loading options...';
    if (props.multiple) {
        if (
            !selectedOptions.value ||
            (selectedOptions.value as any[]).length === 0
        )
            return props.placeholder;
        if ((selectedOptions.value as any[]).length === 1) {
            const opt = (selectedOptions.value as any[])[0];
            return getOptionLabel(opt);
        }
        return `${(selectedOptions.value as any[]).length} selected`;
    }

    if (!selectedOptions.value) return props.placeholder;

    return getOptionLabel(selectedOptions.value);
});

const allFilteredOptions = computed(() => {
    const opts = Array.isArray(props.options) ? props.options : [];
    let list = opts;
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        list = opts.filter((option) => {
            const text = getOptionLabel(option);
            return String(text).toLowerCase().includes(query);
        });
    }

    if (props.selectedFirst && props.multiple) {
        return [...list].sort((a, b) => {
            const aSel = isSelected(a) ? 1 : 0;
            const bSel = isSelected(b) ? 1 : 0;
            return bSel - aSel;
        });
    }

    return list;
});

const totalFilteredCount = computed(() => allFilteredOptions.value.length);

const filteredOptions = computed(() => {
    const list = allFilteredOptions.value;
    if (props.maxDisplay && props.maxDisplay > 0) {
        if (props.multiple && Array.isArray(props.modelValue)) {
            const selectedCount = props.modelValue.length;
            const limit = Math.max(props.maxDisplay, selectedCount + 10);
            return list.slice(0, limit);
        }
        return list.slice(0, props.maxDisplay);
    }
    return list;
});

function toggleDropdown() {
    if (props.disabled) return;
    update(); // Update dimensions before opening
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        searchQuery.value = '';
        nextTick(() => {
            if (searchInputRef.value) {
                searchInputRef.value.focus();
            }
        });
    }
}

function selectOption(option: any) {
    if (typeof option === 'object' && option.disabled) {
        return;
    }

    const value =
        typeof option === 'object' ? option[props.valueKey] : option;

    if (props.multiple) {
        const currentValues = Array.isArray(props.modelValue)
            ? [...props.modelValue]
            : [];
        const index = currentValues.findIndex((v) => v == value);

        if (index > -1) {
            currentValues.splice(index, 1);
        } else {
            currentValues.push(value);
        }

        emit('update:modelValue', currentValues);
    } else {
        emit('update:modelValue', value);
        isOpen.value = false;
    }
}

function clearSelection(event: Event) {
    event.stopPropagation();
    emit('update:modelValue', props.multiple ? [] : null);
}

function handleClickOutside(event: MouseEvent) {
    const target = event.target as Node;
    if (
        selectRef.value &&
        !selectRef.value.contains(target) &&
        dropdownRef.value &&
        !dropdownRef.value.contains(target)
    ) {
        isOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    removeNavigationListener = router.on('start', (event: any) => {
        if (!event?.detail?.visit?.preserveState) {
            isOpen.value = false;
        }
    });
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    if (removeNavigationListener) {
        removeNavigationListener();
    }
});
</script>

<template>
    <div ref="selectRef" class="relative w-full">
        <div
            @click="toggleDropdown"
            class="flex min-h-[30px] w-full items-center justify-between gap-1.5 rounded-lg border bg-background px-2.5 py-1 text-xs text-foreground transition-colors cursor-pointer select-none focus:outline-none focus:ring-2 focus:ring-accent/20"
            :class="[
                disabled
                    ? 'cursor-not-allowed opacity-50 bg-muted'
                    : 'hover:border-accent',
                error ? 'border-destructive' : 'border-input',
                isOpen ? 'border-accent ring-2 ring-accent/20' : '',
                props.class,
            ]"
        >
            <div class="flex flex-1 flex-nowrap items-center gap-1 overflow-hidden min-w-0">
                <template v-if="multiple && Array.isArray(selectedOptions) && selectedOptions.length > 0">
                    <template v-if="selectedOptions.length === 1">
                        <span
                            class="inline-flex max-w-full items-center gap-1 rounded bg-accent/20 px-1.5 py-0.5 text-[10.5px] font-semibold text-foreground shrink-0 overflow-hidden"
                        >
                            <span class="truncate">{{ getOptionLabel(selectedOptions[0]) }}</span>
                            <X
                                @click.stop="selectOption(selectedOptions[0])"
                                class="h-3 w-3 shrink-0 cursor-pointer text-muted-foreground hover:text-foreground"
                            />
                        </span>
                    </template>
                    <template v-else>
                        <span
                            class="inline-flex max-w-[110px] sm:max-w-[130px] items-center gap-1 rounded bg-accent/20 px-1.5 py-0.5 text-[10.5px] font-semibold text-foreground shrink-0 overflow-hidden"
                        >
                            <span class="truncate">{{ getOptionLabel(selectedOptions[0]) }}</span>
                            <X
                                @click.stop="selectOption(selectedOptions[0])"
                                class="h-3 w-3 shrink-0 cursor-pointer text-muted-foreground hover:text-foreground"
                            />
                        </span>
                        <span class="inline-flex items-center rounded bg-primary/20 text-primary px-1.5 py-0.5 text-[9.5px] font-black tracking-wider uppercase shrink-0 whitespace-nowrap">
                            +{{ selectedOptions.length - 1 }} more
                        </span>
                    </template>
                </template>
                <template v-else>
                    <span
                        :class="[
                            'truncate font-medium whitespace-nowrap text-inherit',
                            !modelValue || (Array.isArray(modelValue) && modelValue.length === 0) ? 'text-muted-foreground' : '',
                        ]"
                    >
                        {{ displayText }}
                    </span>
                </template>
            </div>

            <div class="flex items-center gap-1 shrink-0">
                <X
                    v-if="clearable && modelValue && !disabled && (!Array.isArray(modelValue) || modelValue.length > 0)"
                    @click="clearSelection"
                    class="h-3.5 w-3.5 text-muted-foreground hover:text-foreground transition-colors"
                />
                <Loader2
                    v-if="loading"
                    class="h-3.5 w-3.5 animate-spin text-emerald-600 shrink-0"
                />
                <ChevronDown
                    v-else
                    class="h-3.5 w-3.5 text-muted-foreground transition-transform duration-200"
                    :class="{ 'rotate-180': isOpen }"
                />
            </div>
        </div>

        <Teleport to="body" :disabled="!props.teleport">
            <div
                v-if="isOpen"
                ref="dropdownRef"
                class="searchable-select-dropdown rounded-lg border border-border bg-card shadow-xl dark:bg-zinc-950 dark:border-zinc-800"
                :style="dropdownStyle"
            >
                <div v-if="searchable" class="p-1.5 border-b border-border">
                    <div class="relative">
                        <Search class="absolute left-2.5 top-2 h-3.5 w-3.5 text-muted-foreground" />
                        <input
                            ref="searchInputRef"
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search..."
                            class="w-full rounded border border-border bg-card/40 py-1 pr-2 pl-7 text-xs text-foreground backdrop-blur-xl placeholder:text-muted-foreground focus:border-accent focus:ring-1 focus:ring-accent/20 focus:outline-none"
                        />
                    </div>
                </div>

                <div class="custom-scrollbar max-h-60 overflow-y-auto p-1">
                    <div
                        v-for="(option, index) in filteredOptions"
                        :key="index"
                        @click="selectOption(option)"
                        class="group flex items-center justify-between gap-1.5 rounded px-2.5 py-1.5 text-xs transition-colors"
                        :class="{
                            'bg-primary/15 font-bold text-primary dark:bg-primary/25 dark:text-primary-foreground':
                                isSelected(option),
                            'cursor-not-allowed bg-rose-500/10 text-rose-500/80 font-medium border border-rose-500/20':
                                typeof option === 'object' && option.disabled,
                            'cursor-pointer text-foreground hover:bg-accent/10':
                                !(typeof option === 'object' && option.disabled) && !isSelected(option),
                        }"
                    >
                        <div class="flex items-center gap-2 min-w-0 flex-1">
                            <div
                                v-if="multiple"
                                class="h-3.5 w-3.5 shrink-0 rounded border flex items-center justify-center transition-colors"
                                :class="[
                                    isSelected(option)
                                        ? 'bg-primary border-primary text-primary-foreground'
                                        : 'border-muted-foreground/40 bg-background/50 group-hover:border-primary/60'
                                ]"
                            >
                                <Check v-if="isSelected(option)" class="h-2.5 w-2.5 stroke-[3]" />
                            </div>
                            <slot name="option" :option="option">
                                <template
                                    v-if="
                                        typeof option === 'object' &&
                                        typeof props.label === 'string' &&
                                        String(option[props.label]).includes('|')
                                    "
                                >
                                    <span class="whitespace-nowrap">{{
                                        String(option[props.label]).split('|')[0]
                                    }}</span>
                                    <span
                                        :class="[
                                            'ml-auto shrink-0 text-[10px] font-black tracking-tight whitespace-nowrap uppercase transition-colors',
                                            typeof option === 'object' && option.disabled ? 'text-rose-500' : 'text-blue-500 group-hover:text-blue-600'
                                        ]"
                                    >
                                        {{
                                            String(option[props.label]).split(
                                                '|',
                                            )[1]
                                        }}
                                    </span>
                                </template>
                                <template v-else>
                                    <span class="whitespace-nowrap">
                                        {{ getOptionLabel(option) }}
                                    </span>
                                </template>
                            </slot>
                        </div>
                    </div>
                    <div
                        v-if="loading"
                        class="flex items-center gap-2 px-2.5 py-2 text-xs font-semibold text-emerald-600"
                    >
                        <Loader2 class="h-3.5 w-3.5 animate-spin text-emerald-600" />
                        <span>Loading options...</span>
                    </div>
                    <div
                        v-else-if="filteredOptions.length === 0"
                        class="px-2.5 py-1.5 text-xs whitespace-nowrap text-gray-500"
                    >
                        No options found
                    </div>
                    <div
                        v-if="!loading && props.maxDisplay && totalFilteredCount > props.maxDisplay"
                        class="border-t border-border px-2.5 py-1 text-center text-[9.5px] font-bold tracking-tight text-slate-400 italic uppercase"
                    >
                        Showing 20 of {{ totalFilteredCount }} items. Type to refine...
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
