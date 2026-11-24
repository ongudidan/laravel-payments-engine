<script setup lang="ts">
import { ref } from 'vue';
import { AlertCircle, ArrowUpRight, CheckCircle2, Clock, Search } from 'lucide-vue-next';

interface UnallocatedPayment {
    id: number;
    trans_id: string;
    trans_time: string;
    amount: number | string;
    phone_number: string;
    first_name?: string;
    last_name?: string;
    bill_ref_number?: string;
    allocation_status: string;
    created_at: string;
}

const props = withDefaults(
    defineProps<{
        payments: UnallocatedPayment[];
        currency?: string;
        loading?: boolean;
    }>(),
    {
        currency: 'KES',
        loading: false,
    }
);

const emit = defineEmits<{
    (e: 'allocate', payment: UnallocatedPayment): void;
    (e: 'refresh'): void;
}>();

const searchQuery = ref('');

const filteredPayments = () => {
    if (!searchQuery.value) return props.payments;
    const q = searchQuery.value.toLowerCase();
    return props.payments.filter((p) => {
        return (
            (p.trans_id && p.trans_id.toLowerCase().includes(q)) ||
            (p.phone_number && p.phone_number.includes(q)) ||
            (p.bill_ref_number && p.bill_ref_number.toLowerCase().includes(q)) ||
            (p.first_name && p.first_name.toLowerCase().includes(q))
        );
    });
};
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-2 w-2 rounded-full bg-amber-500 animate-pulse"></span>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Unallocated Receipts Suspense (Account 2016)</h3>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Unmatched C2B M-Pesa payments awaiting 1-click allocation to customer invoices or wallets.
                </p>
            </div>

            <div class="relative w-full sm:w-64">
                <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-slate-400" />
                <input
                    type="text"
                    v-model="searchQuery"
                    placeholder="Search receipt, phone, ref..."
                    class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 pl-8 pr-3 text-xs focus:border-emerald-500 focus:bg-white focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                />
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="border-b border-slate-100 bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3">Receipt / Trans ID</th>
                        <th class="px-4 py-3">Date & Time</th>
                        <th class="px-4 py-3">Sender Phone / Name</th>
                        <th class="px-4 py-3">Bill Ref</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="filteredPayments().length === 0">
                        <td colspan="6" class="p-8 text-center text-slate-400">
                            No unallocated transactions currently in suspense.
                        </td>
                    </tr>
                    <tr
                        v-for="payment in filteredPayments()"
                        :key="payment.id"
                        class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40"
                    >
                        <td class="px-4 py-3 font-mono font-bold text-slate-900 dark:text-white">
                            {{ payment.trans_id }}
                        </td>
                        <td class="px-4 py-3 text-slate-500">
                            {{ payment.trans_time || payment.created_at }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-slate-800 dark:text-slate-200">{{ payment.phone_number }}</div>
                            <div v-if="payment.first_name" class="text-[10px] text-slate-400">
                                {{ payment.first_name }} {{ payment.last_name || '' }}
                            </div>
                        </td>
                        <td class="px-4 py-3 font-mono text-slate-600 dark:text-slate-300">
                            {{ payment.bill_ref_number || '—' }}
                        </td>
                        <td class="px-4 py-3 text-right font-bold text-emerald-600 dark:text-emerald-400">
                            {{ currency }} {{ Number(payment.amount).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button
                                type="button"
                                @click="emit('allocate', payment)"
                                class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 transition hover:bg-emerald-100 active:scale-95 dark:bg-emerald-950/60 dark:text-emerald-400"
                            >
                                <span>Allocate</span>
                                <ArrowUpRight class="h-3.5 w-3.5" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
