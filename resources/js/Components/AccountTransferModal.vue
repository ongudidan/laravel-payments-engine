<script setup lang="ts">
import { ref, computed } from 'vue';
import { ArrowRightLeft, X, ArrowRight, ShieldCheck } from 'lucide-vue-next';
import SearchableSelect from './SearchableSelect.vue';
import DenominationCounter from './DenominationCounter.vue';

interface AccountOption {
    id: number;
    label: string;
    balance: number;
    type?: string;
    has_denominations?: boolean;
}

const props = withDefaults(
    defineProps<{
        open: boolean;
        accounts: AccountOption[];
        currency?: string;
        requireMakerCheckerThreshold?: number;
    }>(),
    {
        currency: 'KES',
        requireMakerCheckerThreshold: 10000,
    }
);

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'submit', payload: {
        fromAccountId: number;
        toAccountId: number;
        amount: number;
        charges: number;
        notes?: string;
        denominations?: Record<string | number, number>;
    }): void;
}>();

const fromAccountId = ref<number | null>(null);
const toAccountId = ref<number | null>(null);
const amount = ref<number>(0);
const charges = ref<number>(0);
const notes = ref<string>('');
const denominationsData = ref<Record<string | number, number>>({});
const showDenominations = ref(false);

const fromAccount = computed(() => {
    return props.accounts.find((a) => a.id === fromAccountId.value);
});

const toAccountOptions = computed(() => {
    return props.accounts.filter((a) => a.id !== fromAccountId.value);
});

const isMakerCheckerRequired = computed(() => {
    return amount.value > props.requireMakerCheckerThreshold;
});

const handleSubmit = () => {
    if (!fromAccountId.value || !toAccountId.value || amount.value <= 0) return;

    emit('submit', {
        fromAccountId: fromAccountId.value,
        toAccountId: toAccountId.value,
        amount: amount.value,
        charges: charges.value || 0,
        notes: notes.value,
        denominations: showDenominations.value ? denominationsData.value : undefined,
    });
};
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
        <div class="w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800">
                <div class="flex items-center gap-2">
                    <ArrowRightLeft class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Inter-Account Fund Transfer</h3>
                </div>
                <button type="button" @click="emit('close')" class="text-slate-400 hover:text-slate-600">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">From Account (Source)</label>
                        <SearchableSelect
                            v-model="fromAccountId"
                            :options="accounts"
                            placeholder="Select source account..."
                        />
                        <div v-if="fromAccount" class="mt-1 text-[11px] font-semibold text-slate-500">
                            Available: {{ currency }} {{ Number(fromAccount.balance).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">To Account (Destination)</label>
                        <SearchableSelect
                            v-model="toAccountId"
                            :options="toAccountOptions"
                            placeholder="Select destination account..."
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">Transfer Amount</label>
                        <input
                            type="number"
                            min="0.01"
                            step="0.01"
                            v-model.number="amount"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm font-bold focus:border-emerald-500 focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">Transfer Charges / Fees</label>
                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            v-model.number="charges"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm font-bold focus:border-emerald-500 focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        />
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">Reference / Notes</label>
                    <input
                        type="text"
                        v-model="notes"
                        placeholder="e.g. Daily Till sweep to Bank"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:border-emerald-500 focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    />
                </div>

                <!-- Denomination Counter Toggle for Cash Sweep -->
                <div v-if="fromAccount?.has_denominations" class="pt-1">
                    <button
                        type="button"
                        class="text-xs font-bold text-emerald-600 hover:underline"
                        @click="showDenominations = !showDenominations"
                    >
                        {{ showDenominations ? 'Hide Physical Denomination Counter' : '+ Attach Physical Denomination Breakdown' }}
                    </button>
                    <div v-if="showDenominations" class="mt-2">
                        <DenominationCounter
                            v-model="denominationsData"
                            :expected-total="amount"
                            :currency="currency"
                        />
                    </div>
                </div>

                <!-- Maker-Checker Notice -->
                <div v-if="isMakerCheckerRequired" class="flex items-center gap-2 rounded-lg bg-amber-50 p-2.5 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                    <ShieldCheck class="h-4 w-4 shrink-0 text-amber-600" />
                    <span>Transfers exceeding {{ currency }} {{ requireMakerCheckerThreshold.toLocaleString() }} will require Checker Authorization before funds post.</span>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        :disabled="!fromAccountId || !toAccountId || amount <= 0"
                        @click="handleSubmit"
                        class="flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-50"
                    >
                        <span>Initiate Transfer</span>
                        <ArrowRight class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
