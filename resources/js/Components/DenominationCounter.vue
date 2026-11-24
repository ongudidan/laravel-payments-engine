<script setup lang="ts">
import { computed } from 'vue';
import { Calculator, Minus, Plus } from 'lucide-vue-next';

interface Denomination {
    id: number | string;
    value: number;
    type?: 'bill' | 'coin';
}

const props = withDefaults(
    defineProps<{
        modelValue: Record<string | number, number>;
        denominations?: Denomination[];
        expectedTotal?: number;
        currency?: string;
        disabled?: boolean;
    }>(),
    {
        modelValue: () => ({}),
        denominations: () => [
            { id: 1000, value: 1000, type: 'bill' },
            { id: 500, value: 500, type: 'bill' },
            { id: 200, value: 200, type: 'bill' },
            { id: 100, value: 100, type: 'bill' },
            { id: 50, value: 50, type: 'bill' },
            { id: 40, value: 40, type: 'coin' },
            { id: 20, value: 20, type: 'coin' },
            { id: 10, value: 10, type: 'coin' },
            { id: 5, value: 5, type: 'coin' },
            { id: 1, value: 1, type: 'coin' },
        ],
        expectedTotal: 0,
        currency: 'KES',
        disabled: false,
    }
);

const emit = defineEmits<{
    (e: 'update:modelValue', val: Record<string | number, number>): void;
    (e: 'change', totals: { physicalTotal: number; difference: number; status: string }): void;
}>();

const getCount = (id: string | number): number => {
    return props.modelValue[id] || 0;
};

const updateCount = (id: string | number, count: number) => {
    if (props.disabled) return;
    const safeCount = Math.max(0, count);
    const updated = { ...props.modelValue, [id]: safeCount };
    emit('update:modelValue', updated);
};

const physicalTotal = computed(() => {
    let sum = 0;
    for (const d of props.denominations) {
        const qty = getCount(d.id);
        sum += qty * d.value;
    }
    return Math.round(sum * 100) / 100;
});

const difference = computed(() => {
    return Math.round((physicalTotal.value - props.expectedTotal) * 100) / 100;
});

const varianceStatus = computed(() => {
    if (Math.abs(difference.value) < 0.01) return 'balanced';
    return difference.value > 0 ? 'overage' : 'shortage';
});
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-3 flex items-center justify-between border-b border-slate-100 pb-2 dark:border-slate-800">
            <div class="flex items-center gap-2">
                <Calculator class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                <span class="text-xs font-bold uppercase tracking-wider text-slate-800 dark:text-slate-100">
                    Vault Cash Denominations
                </span>
            </div>
            <div class="flex items-center gap-4 text-xs font-semibold">
                <span class="text-slate-500">Total Counted: <strong class="text-slate-900 dark:text-white">{{ currency }} {{ physicalTotal.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</strong></span>
                <span v-if="expectedTotal > 0" :class="{
                    'text-emerald-600 dark:text-emerald-400': varianceStatus === 'balanced',
                    'text-amber-600 dark:text-amber-400': varianceStatus === 'overage',
                    'text-rose-600 dark:text-rose-400': varianceStatus === 'shortage'
                }">
                    Diff: {{ difference > 0 ? '+' : '' }}{{ difference.toLocaleString(undefined, { minimumFractionDigits: 2 }) }} ({{ varianceStatus }})
                </span>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-5">
            <div
                v-for="d in denominations"
                :key="d.id"
                :class="[
                    'flex flex-col gap-1.5 rounded-lg border p-2.5 transition-all',
                    getCount(d.id) > 0
                        ? 'border-emerald-300 bg-emerald-50/50 dark:border-emerald-700 dark:bg-emerald-950/20'
                        : 'border-slate-200 bg-slate-50/50 hover:bg-slate-100/50 dark:border-slate-800 dark:bg-slate-800/40'
                ]"
            >
                <div class="flex items-center justify-between text-xs font-bold">
                    <span class="text-slate-700 dark:text-slate-300">{{ currency }} {{ d.value.toLocaleString() }}</span>
                    <span class="font-mono text-emerald-700 dark:text-emerald-400">
                        {{ currency }} {{ (getCount(d.id) * d.value).toLocaleString() }}
                    </span>
                </div>

                <div class="flex items-center gap-1.5">
                    <button
                        type="button"
                        class="flex h-6 w-6 items-center justify-center rounded border border-slate-300 bg-white shadow-sm hover:bg-slate-100 active:scale-95 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800"
                        :disabled="disabled || getCount(d.id) <= 0"
                        @click="updateCount(d.id, getCount(d.id) - 1)"
                    >
                        <Minus class="h-3 w-3 text-slate-700 dark:text-slate-200" />
                    </button>

                    <input
                        type="number"
                        min="0"
                        :value="getCount(d.id)"
                        :disabled="disabled"
                        class="h-6 w-full rounded border border-slate-300 bg-white px-1 text-center font-mono text-xs font-bold focus:border-emerald-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900"
                        @input="updateCount(d.id, parseInt(($event.target as HTMLInputElement).value) || 0)"
                    />

                    <button
                        type="button"
                        class="flex h-6 w-6 items-center justify-center rounded border border-slate-300 bg-white shadow-sm hover:bg-slate-100 active:scale-95 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800"
                        :disabled="disabled"
                        @click="updateCount(d.id, getCount(d.id) + 1)"
                    >
                        <Plus class="h-3 w-3 text-slate-700 dark:text-slate-200" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
