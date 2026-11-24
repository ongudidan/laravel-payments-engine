<script setup lang="ts">
import { ref } from 'vue';
import { X, CheckCircle, ArrowRight, Wallet, Receipt } from 'lucide-vue-next';
import SearchableSelect from './SearchableSelect.vue';

interface Transaction {
    id: number;
    trans_id: string;
    amount: number | string;
    phone_number: string;
    bill_ref_number?: string;
}

const props = withDefaults(
    defineProps<{
        open: boolean;
        transaction: Transaction | null;
        customerOptions?: Array<{ id: number; label: string }>;
        invoiceOptions?: Array<{ id: number; label: string }>;
        currency?: string;
    }>(),
    {
        customerOptions: () => [],
        invoiceOptions: () => [],
        currency: 'KES',
    }
);

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'submit', payload: { mode: 'invoice' | 'wallet'; invoiceId?: number; customerId?: number }): void;
}>();

const mode = ref<'invoice' | 'wallet'>('invoice');
const selectedInvoiceId = ref<number | null>(null);
const selectedCustomerId = ref<number | null>(null);
const submitting = ref(false);

const handleAllocate = () => {
    if (mode.value === 'invoice' && !selectedInvoiceId.value) return;
    if (mode.value === 'wallet' && !selectedCustomerId.value) return;

    submitting.value = true;
    emit('submit', {
        mode: mode.value,
        invoiceId: selectedInvoiceId.value ?? undefined,
        customerId: selectedCustomerId.value ?? undefined,
    });
};
</script>

<template>
    <div v-if="open && transaction" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
        <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Allocate Suspense Payment</h3>
                    <p class="text-xs text-slate-500">M-Pesa Receipt: <strong class="text-slate-800 dark:text-slate-200">{{ transaction.trans_id }}</strong></p>
                </div>
                <button type="button" @click="emit('close')" class="text-slate-400 hover:text-slate-600">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <div class="space-y-4">
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Amount Received</span>
                        <div class="text-lg font-black text-emerald-600 dark:text-emerald-400">
                            {{ currency }} {{ Number(transaction.amount).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Sender</span>
                        <div class="text-xs font-mono font-bold text-slate-800 dark:text-slate-200">{{ transaction.phone_number }}</div>
                    </div>
                </div>

                <!-- Allocation Target Tabs -->
                <div class="grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        :class="[
                            'flex items-center justify-center gap-2 rounded-lg border p-2.5 text-xs font-bold transition-all',
                            mode === 'invoice'
                                ? 'border-emerald-600 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'
                                : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'
                        ]"
                        @click="mode = 'invoice'"
                    >
                        <Receipt class="h-4 w-4" />
                        <span>Settle To Invoice</span>
                    </button>

                    <button
                        type="button"
                        :class="[
                            'flex items-center justify-center gap-2 rounded-lg border p-2.5 text-xs font-bold transition-all',
                            mode === 'wallet'
                                ? 'border-emerald-600 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'
                                : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'
                        ]"
                        @click="mode = 'wallet'"
                    >
                        <Wallet class="h-4 w-4" />
                        <span>Credit Customer Wallet</span>
                    </button>
                </div>

                <!-- Form based on target -->
                <div v-if="mode === 'invoice'" class="space-y-2">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Select Target Invoice</label>
                    <SearchableSelect
                        v-model="selectedInvoiceId"
                        :options="invoiceOptions"
                        placeholder="Search invoice number or customer name..."
                    />
                </div>

                <div v-else class="space-y-2">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Select Customer Account</label>
                    <SearchableSelect
                        v-model="selectedCustomerId"
                        :options="customerOptions"
                        placeholder="Search customer by name or phone..."
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
                        :disabled="submitting || (mode === 'invoice' && !selectedInvoiceId) || (mode === 'wallet' && !selectedCustomerId)"
                        @click="handleAllocate"
                        class="flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-50"
                    >
                        <span>Confirm Allocation</span>
                        <ArrowRight class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
