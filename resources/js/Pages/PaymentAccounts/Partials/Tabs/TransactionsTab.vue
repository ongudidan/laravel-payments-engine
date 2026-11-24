<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { router } from '@inertiajs/vue3';
import 'flatpickr/dist/flatpickr.css';
import { Calendar, Clock, Download, History, Wallet, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FlatPickr from 'vue-flatpickr-component';

const props = defineProps<{
    account: any;
    transactions: any;
    filters: any;
    balanceStats?: {
        gross_balance: number;
        pending_deductions: number;
        pending_cheques?: number;
        pending_submissions?: number;
        pending_payouts?: number;
        net_balance: number;
    };
}>();

const dateRange = ref(props.filters?.date_range || '');

const computedStats = computed(() => {
    if (props.balanceStats) {
        return props.balanceStats;
    }
    if (props.account?.balance_stats) {
        return props.account.balance_stats;
    }
    const gross = Number(props.account?.balance || 0);
    const pendingCheques = Number(props.account?.pending_cheque_balance || 0);
    return {
        gross_balance: gross,
        pending_deductions: pendingCheques,
        pending_cheques: pendingCheques,
        pending_submissions: 0,
        pending_payouts: 0,
        net_balance: gross - pendingCheques,
    };
});

const pendingTooltip = computed(() => {
    const parts: string[] = [];
    if ((computedStats.value.pending_submissions || 0) > 0) {
        parts.push(`Unapproved Submissions: ${formatCurrency(computedStats.value.pending_submissions!)}`);
    }
    if ((computedStats.value.pending_cheques || 0) > 0) {
        parts.push(`Uncleared Cheques: ${formatCurrency(computedStats.value.pending_cheques!)}`);
    }
    if ((computedStats.value.pending_payouts || 0) > 0) {
        parts.push(`Pending Payouts: ${formatCurrency(computedStats.value.pending_payouts!)}`);
    }
    return parts.length > 0 ? parts.join(' | ') : 'Pending deductions awaiting approval/clearance';
});

const flatpickrConfig = {
    mode: 'range' as const,
    altInput: true,
    altFormat: 'F j, Y',
    dateFormat: 'Y-m-d',
};

const handleDateRangeChange = (selectedDates: any, dateStr: string) => {
    // Only query when either the range is fully selected (has 2 dates selected) or it is cleared
    if (!dateStr || selectedDates.length === 2) {
        router.get(
            window.location.pathname,
            {
                tab: 'transactions',
                date_range: dateStr,
            },
            { preserveState: true, preserveScroll: true },
        );
    }
};

const clearDateRange = () => {
    dateRange.value = '';
    router.get(
        window.location.pathname,
        {
            tab: 'transactions',
            date_range: '',
        },
        { preserveState: true, preserveScroll: true },
    );
};

const formatCurrency = (amount: number | string) => {
    return Number(amount || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getActionColor = (action: string) => {
    const act = (action || '').toLowerCase();
    if (
        act.includes('received') ||
        act.includes('deposit') ||
        act.includes('inward') ||
        act.includes('sale') ||
        act.includes('increase')
    )
        return 'text-emerald-500';
    if (
        act.includes('sent') ||
        act.includes('disbursed') ||
        act.includes('outward') ||
        act.includes('cancelled') ||
        act.includes('bounced') ||
        act.includes('decrease')
    )
        return 'text-rose-500';
    if (
        act.includes('posted') ||
        act.includes('transfer') ||
        act.includes('initiated')
    )
        return 'text-blue-500';
    return 'text-orange-500';
};

const getTooltipText = (flow: any) => {
    const details = flow.detailed_description;
    if (!details) {
        return `${flow.action} | From: ${flow.source_account_name || 'N/A'} -> To: ${flow.destination_account_name || 'N/A'} | Notes: ${flow.description || 'N/A'}`;
    }
    return `[${details.activity}] ${details.type}\n` +
        `• Source Account: ${details.source}\n` +
        `• Destination Account: ${details.destination}\n` +
        `• Related Entity: ${details.counterparty}\n` +
        `• Notes: ${details.notes}\n` +
        `• Created By: ${details.created_by}\n` +
        `• Reference: ${details.reference}`;
};
</script>

<template>
    <div class="space-y-4 pb-4">
        <div
            class="overflow-hidden rounded-lg border border-border bg-card/40 shadow-sm backdrop-blur-xl"
        >
            <div
                class="flex flex-col justify-between gap-4 border-b border-border bg-emerald-500/5 px-4 py-3 lg:flex-row lg:items-center"
            >
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div class="flex items-center gap-2">
                        <History class="h-3.5 w-3.5 text-emerald-500" />
                        <span
                            class="text-[10px] font-black tracking-[0.2em] text-muted-foreground uppercase"
                            >Ledger Activity Log</span
                        >
                    </div>

                    <!-- Gross & Net Balance Stats -->
                    <div
                        class="flex items-center gap-3 border-t border-border/60 pt-2 sm:border-t-0 sm:border-l sm:pt-0 sm:pl-4"
                    >
                        <div class="flex items-center gap-1.5">
                            <span
                                class="text-[8px] font-black tracking-widest text-muted-foreground uppercase"
                                >Gross:</span
                            >
                            <span
                                class="text-xs font-black text-foreground tabular-nums"
                            >
                                {{ formatCurrency(computedStats.gross_balance) }}
                            </span>
                        </div>

                        <!-- Pending Deductions (Unapproved Submissions / Uncleared Cheques) -->
                        <template v-if="computedStats.pending_deductions > 0">
                            <div class="h-3 w-px bg-border/60"></div>
                            <div
                                class="flex items-center gap-1.5 cursor-help"
                                :title="pendingTooltip"
                            >
                                <span
                                    class="text-[8px] font-black tracking-widest text-amber-600 dark:text-amber-400 uppercase"
                                    >Pending:</span
                                >
                                <span
                                    class="text-xs font-black text-amber-600 dark:text-amber-400 tabular-nums"
                                >
                                    -{{ formatCurrency(computedStats.pending_deductions) }}
                                </span>
                            </div>
                        </template>

                        <div class="h-3 w-px bg-border/60"></div>
                        <div class="flex items-center gap-1.5">
                            <span
                                class="text-[8px] font-black tracking-widest text-muted-foreground uppercase"
                                >Net:</span
                            >
                            <span
                                class="text-xs font-black tabular-nums"
                                :class="
                                    computedStats.net_balance >= 0
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-rose-600 dark:text-rose-400'
                                "
                            >
                                {{ formatCurrency(computedStats.net_balance) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <!-- Export Button -->
                    <a
                        :href="`/organization/financial-profiles/payment-accounts/${account.id}/export?date_range=${dateRange || ''}`"
                        class="flex h-8 items-center gap-1.5 rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-3 text-[10px] font-black tracking-wider text-emerald-500 uppercase transition-all hover:bg-emerald-500/20"
                    >
                        <Download class="h-3.5 w-3.5" />
                        <span>Export Excel</span>
                    </a>

                    <!-- Date Range Filter -->
                    <div class="relative flex items-center">
                        <Calendar
                            class="pointer-events-none absolute left-2.5 z-10 h-3.5 w-3.5 text-muted-foreground/60"
                        />
                        <FlatPickr
                            v-model="dateRange"
                            :config="flatpickrConfig as any"
                            @on-change="handleDateRangeChange"
                            class="h-8 w-[220px] cursor-pointer rounded-lg border border-border bg-background pr-7 pl-8 text-[10px] font-black tracking-wider uppercase transition-all outline-none placeholder:text-muted-foreground/40 focus:ring-1 focus:ring-primary/20"
                            placeholder="FILTER BY DATE RANGE..."
                        />
                        <button
                            v-if="dateRange"
                            @click="clearDateRange"
                            class="absolute right-2.5 z-10 rounded-full p-0.5 text-muted-foreground/60 transition-all hover:bg-muted-foreground/10 hover:text-foreground"
                        >
                            <X class="h-3 w-3" />
                        </button>
                    </div>

                    <span
                        class="text-[9px] font-black tracking-widest text-muted-foreground/60 uppercase"
                    >
                        Showing {{ transactions.from || 1 }}-{{
                            transactions.to ||
                            (transactions.data || transactions).length
                        }}
                        of
                        {{
                            transactions.total ||
                            (transactions.data || transactions).length
                        }}
                        Entries
                    </span>
                </div>
            </div>

            <div class="custom-scrollbar overflow-x-auto">
                <table class="w-full border-separate border-spacing-0">
                    <thead>
                        <tr class="bg-muted/30">
                            <th
                                class="border-b border-border px-4 py-2 text-left text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                            >
                                #
                            </th>
                            <th
                                class="border-b border-border px-4 py-2 text-left text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                            >
                                Timestamp
                            </th>
                            <th
                                class="border-b border-border px-3 py-2 text-left text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                            >
                                Reference
                            </th>
                            <th
                                class="border-b border-border px-3 py-2 text-left text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                            >
                                Activity Type
                            </th>
                            <th
                                class="border-b border-border px-3 py-2 text-left text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                            >
                                Accounts & Description
                            </th>
                            <th
                                class="border-b border-border px-3 py-2 text-left text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                            >
                                Created By
                            </th>
                            <th
                                class="border-b border-border px-3 py-2 text-right text-[9px] font-black tracking-widest text-emerald-500 text-muted-foreground uppercase"
                            >
                                Debit (DR)
                            </th>
                            <th
                                class="border-b border-border px-3 py-2 text-right text-[9px] font-black tracking-widest text-muted-foreground text-rose-500 uppercase"
                            >
                                Credit (CR)
                            </th>
                            <th
                                class="border-b border-border px-4 py-2 text-right text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                            >
                                Balance
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/50">
                        <tr
                            v-for="(flow, index) in transactions.data ||
                            transactions"
                            :key="flow.id"
                            class="group transition-colors hover:bg-emerald-500/10 dark:hover:bg-emerald-500/20"
                        >
                            <td class="px-4 py-1.5 whitespace-nowrap">
                                <span
                                    class="text-[9px] font-black text-muted-foreground/60 tabular-nums"
                                    >{{
                                        (transactions.from || 1) + index
                                    }}</span
                                >
                            </td>
                            <td class="px-4 py-1.5 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <Clock
                                        class="h-3 w-3 text-muted-foreground/40"
                                    />
                                    <span
                                        class="text-[10px] font-bold text-foreground"
                                        >{{ formatDate(flow.created_at) }}</span
                                    >
                                </div>
                            </td>
                            <td class="px-3 py-1.5">
                                <div class="flex flex-col">
                                    <span
                                        class="text-[9px] font-black tracking-tighter text-indigo-600 uppercase"
                                        >{{
                                            flow.transaction?.reference ||
                                            flow.id.toString().toUpperCase()
                                        }}</span
                                    >
                                    <span
                                        v-if="flow.transaction?.transaction_number && flow.transaction?.transaction_number !== flow.transaction?.reference"
                                        class="text-[8px] font-semibold text-muted-foreground/80 tracking-tight font-mono"
                                    >
                                        {{ flow.transaction.transaction_number }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-3 py-1.5">
                                <div class="flex items-center gap-2">
                                    <span
                                        :class="getActionColor(flow.action)"
                                        class="rounded-full border border-current/10 bg-current/5 px-1.5 py-0.5 text-[9px] font-black tracking-widest uppercase"
                                    >
                                        {{ flow.action.replace('_', ' ') }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-3 py-1.5">
                                <div
                                    class="flex flex-col cursor-help"
                                    :title="getTooltipText(flow)"
                                >
                                    <!-- Source -> Destination path -->
                                    <div class="flex flex-wrap items-center gap-1 text-[9px] font-black uppercase tracking-tight">
                                        <span class="text-emerald-600 dark:text-emerald-400">From: {{ flow.source_account_name || flow.counterparty || 'External' }}</span>
                                        <span class="text-muted-foreground/40">→</span>
                                        <span class="text-blue-600 dark:text-blue-400">To: {{ flow.destination_account_name || flow.counterparty || 'External' }}</span>
                                    </div>
                                    <!-- Notes / Particulars -->
                                    <span
                                        v-if="flow.description"
                                        class="mt-0.5 max-w-[260px] truncate text-[8.5px] font-semibold text-muted-foreground italic opacity-80"
                                    >
                                        {{ flow.description }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-3 py-1.5 whitespace-nowrap">
                                <span class="text-[10px] font-bold text-foreground">
                                    {{ flow.created_by_name || 'System' }}
                                </span>
                            </td>
                            <td class="px-3 py-1.5 text-right">
                                <span
                                    v-if="flow.debit > 0"
                                    class="text-xs font-black tracking-tighter text-emerald-500 tabular-nums"
                                >
                                    {{ formatCurrency(flow.debit) }}
                                </span>
                                <span
                                    v-else
                                    class="text-[9px] text-muted-foreground/30"
                                    >—</span
                                >
                            </td>
                            <td class="px-3 py-1.5 text-right">
                                <span
                                    v-if="flow.credit > 0"
                                    class="text-xs font-black tracking-tighter text-rose-500 tabular-nums"
                                >
                                    {{ formatCurrency(flow.credit) }}
                                </span>
                                <span
                                    v-else
                                    class="text-[9px] text-muted-foreground/30"
                                    >—</span
                                >
                            </td>
                            <td
                                class="border-l border-border/10 bg-muted/5 px-4 py-1.5 text-right group-hover:bg-transparent"
                            >
                                <span
                                    class="text-xs font-black tracking-tighter text-foreground tabular-nums"
                                >
                                    {{ formatCurrency(flow.running_balance) }}
                                </span>
                            </td>
                        </tr>
                        <tr
                            v-if="
                                (!transactions.data &&
                                    transactions.length === 0) ||
                                (transactions.data &&
                                    transactions.data.length === 0)
                            "
                        >
                            <td colspan="9" class="p-12 text-center">
                                <div
                                    class="flex flex-col items-center gap-3 opacity-20"
                                >
                                    <Wallet class="h-10 w-10" />
                                    <div
                                        class="text-[10px] font-black tracking-[0.3em] uppercase"
                                    >
                                        No Transactional Flow Recorded
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer Pagination -->
            <div
                v-if="transactions.links && transactions.links.length > 3"
                class="shrink-0 border-t border-border bg-card/60 px-4 py-3"
            >
                <Pagination
                    :links="transactions.links"
                    :from="transactions.from"
                    :to="transactions.to"
                    :total="transactions.total"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    height: 4px;
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: hsl(var(--border) / 0.5);
    border-radius: 10px;
}
</style>
