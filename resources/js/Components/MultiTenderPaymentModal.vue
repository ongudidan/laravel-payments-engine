<script setup lang="ts">
import { ref, computed } from 'vue';
import { Banknote, CreditCard, Wallet, X, ArrowRight } from 'lucide-vue-next';
import SearchableSelect from './SearchableSelect.vue';

interface PaymentAccountOption {
    id: number;
    label: string;
    type: string;
    balance: number;
}

const props = withDefaults(
    defineProps<{
        open: boolean;
        totalAmountDue: number;
        paymentAccounts?: PaymentAccountOption[];
        customerWalletBalance?: number;
        currency?: string;
    }>(),
    {
        paymentAccounts: () => [],
        customerWalletBalance: 0,
        currency: 'KES',
    }
);

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'submit', payload: {
        cashAmount: number;
        mpesaAmount: number;
        walletAmount: number;
        cashAccountId?: number;
        mpesaAccountId?: number;
    }): void;
}>();

const cashAmount = ref<number>(0);
const mpesaAmount = ref<number>(0);
const walletAmount = ref<number>(0);
const cashAccountId = ref<number | null>(null);
const mpesaAccountId = ref<number | null>(null);

const totalTendered = computed(() => {
    return round(cashAmount.value + mpesaAmount.value + walletAmount.value);
});

const remainingBalance = computed(() => {
    return round(props.totalAmountDue - totalTendered.value);
});

const changeDue = computed(() => {
    return totalTendered.value > props.totalAmountDue
        ? round(totalTendered.value - props.totalAmountDue)
        : 0;
});

const isComplete = computed(() => {
    return totalTendered.value >= props.totalAmountDue;
});

function round(val: number): number {
    return Math.round(val * 100) / 100;
}

const handleFullWallet = () => {
    const available = props.customerWalletBalance;
    const needed = props.totalAmountDue;
    walletAmount.value = Math.min(available, needed);
};

const handleFullMpesa = () => {
    mpesaAmount.value = Math.max(0, props.totalAmountDue - walletAmount.value - cashAmount.value);
};

const handleFullCash = () => {
    cashAmount.value = Math.max(0, props.totalAmountDue - walletAmount.value - mpesaAmount.value);
};

const handleSubmit = () => {
    if (!isComplete.value) return;

    emit('submit', {
        cashAmount: cashAmount.value,
        mpesaAmount: mpesaAmount.value,
        walletAmount: walletAmount.value,
        cashAccountId: cashAccountId.value ?? undefined,
        mpesaAccountId: mpesaAccountId.value ?? undefined,
    });
};
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
        <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Multi-Tender Split Payment</h3>
                    <p class="text-xs text-slate-500">Split payment across Cash, M-Pesa, and Customer Wallet.</p>
                </div>
                <button type="button" @click="emit('close')" class="text-slate-400 hover:text-slate-600">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Summary header -->
            <div class="grid grid-cols-3 gap-2 rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60 text-center mb-4">
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400">Total Due</span>
                    <div class="text-base font-black text-slate-900 dark:text-white">
                        {{ currency }} {{ totalAmountDue.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                    </div>
                </div>
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400">Tendered</span>
                    <div class="text-base font-black text-emerald-600 dark:text-emerald-400">
                        {{ currency }} {{ totalTendered.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                    </div>
                </div>
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400">{{ changeDue > 0 ? 'Change' : 'Remaining' }}</span>
                    <div :class="['text-base font-black', changeDue > 0 ? 'text-blue-600 dark:text-blue-400' : (remainingBalance > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-600')]">
                        {{ currency }} {{ (changeDue > 0 ? changeDue : remainingBalance).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <!-- Tender 1: Customer Wallet -->
                <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-800">
                    <div class="flex items-center justify-between mb-1.5">
                        <div class="flex items-center gap-1.5 text-xs font-bold text-slate-800 dark:text-slate-200">
                            <Wallet class="h-4 w-4 text-purple-600" />
                            <span>Customer Wallet (Escrow 2015)</span>
                        </div>
                        <button type="button" @click="handleFullWallet" class="text-[10px] font-bold text-purple-600 hover:underline">
                            Max Available ({{ currency }} {{ customerWalletBalance.toLocaleString() }})
                        </button>
                    </div>
                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        v-model.number="walletAmount"
                        class="w-full rounded-lg border border-slate-300 px-3 py-1.5 font-mono text-sm font-bold focus:border-purple-500 focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    />
                </div>

                <!-- Tender 2: Cash -->
                <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-800">
                    <div class="flex items-center justify-between mb-1.5">
                        <div class="flex items-center gap-1.5 text-xs font-bold text-slate-800 dark:text-slate-200">
                            <Banknote class="h-4 w-4 text-emerald-600" />
                            <span>Cash Tender</span>
                        </div>
                        <button type="button" @click="handleFullCash" class="text-[10px] font-bold text-emerald-600 hover:underline">
                            Fill Remaining
                        </button>
                    </div>
                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        v-model.number="cashAmount"
                        class="w-full rounded-lg border border-slate-300 px-3 py-1.5 font-mono text-sm font-bold focus:border-emerald-500 focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    />
                </div>

                <!-- Tender 3: M-Pesa -->
                <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-800">
                    <div class="flex items-center justify-between mb-1.5">
                        <div class="flex items-center gap-1.5 text-xs font-bold text-slate-800 dark:text-slate-200">
                            <CreditCard class="h-4 w-4 text-teal-600" />
                            <span>M-Pesa Express / Till</span>
                        </div>
                        <button type="button" @click="handleFullMpesa" class="text-[10px] font-bold text-teal-600 hover:underline">
                            Fill Remaining
                        </button>
                    </div>
                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        v-model.number="mpesaAmount"
                        class="w-full rounded-lg border border-slate-300 px-3 py-1.5 font-mono text-sm font-bold focus:border-teal-500 focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    />
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
                        :disabled="!isComplete"
                        @click="handleSubmit"
                        class="flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-50"
                    >
                        <span>Complete Payment</span>
                        <ArrowRight class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
