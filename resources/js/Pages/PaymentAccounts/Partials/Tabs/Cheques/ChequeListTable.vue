<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    ArrowDownLeft,
    ArrowUpRight,
    Calendar,
    CheckCircle,
    ClipboardCheck,
    Clock,
    Eye,
    Hash,
    Loader2,
    Printer,
    Smartphone,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { QzPrinterService } from '@/services/qzPrinter';

defineProps<{
    cheques: any;
    account: any;
    formatCurrency: (amount: string | number, currency: string) => string;
    formatDate: (date: string) => string;
}>();

defineEmits(['collect', 'clear', 'cancel', 'view-details']);

const page = usePage();
const userPermissions = computed(
    () => (page.props.auth as any).permissions || [],
);

const canCollect = computed(() =>
    userPermissions.value.includes('Finance: Cheques: Collect'),
);
const canClear = computed(() =>
    userPermissions.value.includes('Finance: Cheques: Clear'),
);
const canCancel = computed(() =>
    userPermissions.value.includes('Finance: Cheques: Cancel'),
);

const printerService = QzPrinterService.getInstance();
const isPrintingCheque = ref<number | null>(null);

const printChequeReceipt = async (chequeId: number) => {
    isPrintingCheque.value = chequeId;
    const url = `/organization/finance/cheques/${chequeId}/print`;
    try {
        await printerService.print(url, 'thermal', 'print-cheque-iframe');
    } catch (err) {
        console.error(err);
    } finally {
        isPrintingCheque.value = null;
    }
};

const getChequeRowClass = (cheque: any) => {
    switch (cheque.status) {
        case 'Collected':
            return 'bg-amber-100/70 dark:bg-amber-950/40 hover:bg-amber-200/80 dark:hover:bg-amber-950/50 border-l-4 border-l-amber-500';
        case 'Pending':
            return 'bg-blue-100/70 dark:bg-blue-950/40 hover:bg-blue-200/80 dark:hover:bg-blue-950/50 border-l-4 border-l-blue-500';
        case 'Cancelled':
            return 'bg-rose-100/70 dark:bg-rose-950/40 hover:bg-rose-200/80 dark:hover:bg-rose-950/50 border-l-4 border-l-rose-500';
        case 'Cleared':
        default:
            return 'hover:bg-accent/5 transition-colors';
    }
};
</script>

<template>
    <div class="custom-scrollbar flex-1 overflow-auto p-0">
        <table
            v-if="
                (cheques.data && cheques.data.length > 0) ||
                (!cheques.data && cheques.length > 0)
            "
            class="w-full border-separate border-spacing-0"
        >
            <thead
                class="sticky top-0 z-10 bg-card text-[10px] font-black tracking-widest text-muted-foreground uppercase"
            >
                <tr>
                    <th
                        class="border-b border-border bg-accent/5 px-6 py-3 text-left uppercase"
                    >
                        Type
                    </th>
                    <th
                        class="border-b border-border bg-accent/5 px-6 py-3 text-left uppercase"
                    >
                        Cheque Info
                    </th>
                    <th
                        class="border-b border-border bg-accent/5 px-6 py-3 text-left uppercase"
                    >
                        Collector
                    </th>
                    <th
                        class="border-b border-border bg-accent/5 px-6 py-3 text-left uppercase"
                    >
                        Allocation
                    </th>
                    <th
                        class="border-b border-border bg-accent/5 px-6 py-3 text-right uppercase"
                    >
                        Amount
                    </th>
                    <th
                        class="border-b border-border bg-accent/5 px-6 py-3 text-center uppercase"
                    >
                        Status
                    </th>
                    <th
                        class="shadow-l sticky right-0 w-[110px] border-b border-l border-border bg-accent/5 px-4 py-3 text-right uppercase"
                    >
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                <tr
                    v-for="cheque in cheques.data || cheques"
                    :key="cheque.id"
                    :class="getChequeRowClass(cheque)"
                    class="group transition-colors"
                >
                    <td class="px-6 py-1.5">
                        <div class="flex items-center gap-1.5">
                            <div
                                :class="
                                    cheque.direction === 'Outward'
                                        ? 'border-orange-200/50 bg-orange-500/10 text-orange-600'
                                        : 'border-emerald-200/50 bg-emerald-500/10 text-emerald-600'
                                "
                                class="flex items-center gap-1 rounded-md border px-2 py-1 text-[8px] font-black tracking-widest whitespace-nowrap uppercase shadow-sm"
                            >
                                <ArrowUpRight
                                    v-if="cheque.direction === 'Outward'"
                                    class="h-2.5 w-2.5"
                                />
                                <ArrowDownLeft v-else class="h-2.5 w-2.5" />
                                {{
                                    cheque.direction === 'Outward'
                                        ? 'Issued'
                                        : 'Received'
                                }}
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-1.5">
                        <div class="flex flex-col gap-1">
                            <span
                                class="flex items-center gap-1.5 text-[10px] font-black tracking-tight text-foreground uppercase"
                            >
                                <Hash
                                    class="h-2.5 w-2.5 text-muted-foreground"
                                />
                                No: {{ cheque.cheque_number || 'N/A' }}
                            </span>
                            <span
                                class="flex items-center gap-1.5 text-[9px] font-bold text-muted-foreground uppercase opacity-60"
                            >
                                <Calendar class="h-2.5 w-2.5" />
                                Due:
                                {{
                                    cheque.cheque_date
                                        ? formatDate(cheque.cheque_date)
                                        : 'N/A'
                                }}
                            </span>
                        </div>
                    </td>
                    <td class="px-6 py-1.5">
                        <div
                            v-if="cheque.collected_by_name"
                            class="flex flex-col gap-1"
                        >
                            <span
                                class="max-w-[140px] truncate text-[9px] font-black tracking-tight text-foreground uppercase"
                            >
                                {{ cheque.collected_by_name }}
                            </span>
                            <span
                                class="flex items-center gap-1 text-[8px] font-bold text-muted-foreground uppercase opacity-60"
                            >
                                <Smartphone class="h-2.5 w-2.5" />
                                {{ cheque.collected_by_mobile || 'No Mobile' }}
                            </span>
                        </div>
                        <div
                            v-else
                            class="text-[9px] font-bold text-slate-300 uppercase italic"
                        >
                            Not Collected
                        </div>
                    </td>
                    <td class="px-6 py-1.5">
                        <div v-if="cheque.direction === 'Outward'">
                            <!-- Recipient Name -->
                            <div class="mb-2 flex flex-col">
                                <span
                                    class="text-[10px] leading-none font-black tracking-tight text-indigo-700 uppercase"
                                >
                                    {{
                                        cheque.chequeable?.supplier
                                            ?.company_name ||
                                        cheque.chequeable?.supplier?.name ||
                                        cheque.chequeable?.employee
                                            ?.official_names ||
                                        (cheque.chequeable_type.includes(
                                            'WithholdingBatch',
                                        )
                                            ? 'Kenya Revenue Authority (KRA)'
                                            : '') ||
                                        cheque.chequeable?.lender?.name ||
                                        cheque.chequeable?.borrower?.name ||
                                        'Local Recipient'
                                    }}
                                </span>
                                <span
                                    v-if="cheque.notes"
                                    class="mt-0.5 text-[8px] font-bold text-muted-foreground uppercase opacity-60"
                                >
                                    {{ cheque.notes }}
                                </span>
                            </div>

                            <!-- Document/Context Allocations -->
                            <div class="flex flex-wrap gap-1">
                                <!-- Supplier Allocations -->
                                <template
                                    v-if="
                                        cheque.chequeable_type.includes(
                                            'SupplierPayment',
                                        )
                                    "
                                >
                                    <div
                                        v-for="alloc in cheque.chequeable
                                            ?.allocations"
                                        :key="alloc.id"
                                        class="inline-flex items-center rounded border border-border/40 bg-slate-100 px-1.5 py-0.5 dark:bg-slate-800"
                                    >
                                        <span
                                            v-if="alloc.grn"
                                            class="text-[8px] font-black whitespace-nowrap text-foreground uppercase"
                                            :title="
                                                alloc.grn.grn_number
                                                    ? `GRN: ${alloc.grn.grn_number}`
                                                    : undefined
                                            "
                                        >
                                            Purchase Invoice:
                                            {{
                                                alloc.grn.system_invoice_no ||
                                                alloc.grn.unfiscalized_system_invoice_no ||
                                                alloc.grn.trader_system_invoice_no ||
                                                alloc.grn.unfiscalized_manual_invoice_no ||
                                                alloc.grn.grn_number
                                            }}
                                        </span>
                                        <span
                                            v-else-if="alloc.invoice"
                                            class="text-[8px] font-black whitespace-nowrap text-foreground uppercase"
                                        >
                                            Doc:
                                            {{
                                                alloc.invoice.invoice_number ||
                                                alloc.invoice.id
                                            }}
                                        </span>
                                    </div>
                                    <div
                                        v-if="
                                            !cheque.chequeable?.allocations
                                                ?.length
                                        "
                                        class="text-[8px] font-bold text-slate-300 uppercase italic"
                                    >
                                        Unallocated Funds
                                    </div>
                                </template>

                                <!-- Withholding Batch -->
                                <template
                                    v-else-if="
                                        cheque.chequeable_type.includes(
                                            'WithholdingBatch',
                                        )
                                    "
                                >
                                    <div
                                        class="inline-flex items-center rounded border border-amber-200/50 bg-amber-50 px-1.5 py-0.5 dark:border-amber-800/30 dark:bg-amber-900/20"
                                    >
                                        <span
                                            class="text-[8px] font-black whitespace-nowrap text-amber-700 uppercase dark:text-amber-400"
                                        >
                                            WHT Batch:
                                            {{
                                                cheque.chequeable?.batch_number
                                            }}
                                        </span>
                                    </div>
                                </template>

                                <!-- Payroll -->
                                <template
                                    v-else-if="
                                        cheque.chequeable_type.includes(
                                            'Payroll',
                                        )
                                    "
                                >
                                    <div
                                        class="inline-flex items-center rounded border border-emerald-200/50 bg-emerald-50 px-1.5 py-0.5 dark:border-emerald-800/30 dark:bg-emerald-900/20"
                                    >
                                        <span
                                            class="text-[8px] font-black whitespace-nowrap text-emerald-700 uppercase dark:text-emerald-400"
                                        >
                                            Net Salary:
                                            {{ cheque.chequeable?.month }}/{{
                                                cheque.chequeable?.year
                                            }}
                                        </span>
                                    </div>
                                </template>

                                <!-- Salary Advance -->
                                <template
                                    v-else-if="
                                        cheque.chequeable_type.includes(
                                            'SalaryAdvance',
                                        )
                                    "
                                >
                                    <div
                                        class="inline-flex items-center rounded border border-blue-200/50 bg-blue-50 px-1.5 py-0.5 dark:border-blue-800/30 dark:bg-blue-900/20"
                                    >
                                        <span
                                            class="text-[8px] font-black whitespace-nowrap text-blue-700 uppercase dark:text-blue-400"
                                        >
                                            Salary Advance: #{{
                                                cheque.chequeable?.id
                                            }}
                                        </span>
                                    </div>
                                </template>

                                <!-- Loans -->
                                <template
                                    v-else-if="
                                        cheque.chequeable_type.includes(
                                            'LenderTransaction',
                                        ) ||
                                        cheque.chequeable_type.includes(
                                            'BorrowerTransaction',
                                        )
                                    "
                                >
                                    <div
                                        class="inline-flex items-center rounded border border-indigo-200/50 bg-indigo-50 px-1.5 py-0.5 dark:border-indigo-800/30 dark:bg-indigo-900/20"
                                    >
                                        <span
                                            class="text-[8px] font-black whitespace-nowrap text-indigo-700 uppercase dark:text-indigo-400"
                                        >
                                            Loan Activity
                                        </span>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div v-else class="flex flex-col">
                            <!-- Customer Name -->
                            <div class="mb-2 flex flex-col">
                                <span
                                    class="text-[10px] leading-none font-black tracking-tight text-blue-700 uppercase"
                                >
                                    {{
                                        cheque.chequeable?.customer
                                            ?.company_name ||
                                        cheque.chequeable?.customer?.name ||
                                        cheque.chequeable?.allocations?.[0]
                                            ?.sale?.customer?.company_name ||
                                        cheque.chequeable?.allocations?.[0]
                                            ?.sale?.customer?.name ||
                                        'Customer Cheque'
                                    }}
                                </span>
                                <span
                                    v-if="cheque.notes"
                                    class="mt-0.5 text-[8px] font-bold text-muted-foreground uppercase opacity-60"
                                >
                                    {{ cheque.notes }}
                                </span>
                            </div>

                            <!-- Sale Allocations -->
                            <div class="flex flex-wrap gap-1">
                                <div
                                    v-for="alloc in cheque.chequeable
                                        ?.allocations"
                                    :key="alloc.id"
                                    class="inline-flex items-center rounded border border-blue-100/50 bg-blue-50 px-1.5 py-0.5 dark:border-blue-800/30 dark:bg-blue-900/20"
                                >
                                    <span
                                        v-if="alloc.sale"
                                        class="text-[8px] font-black whitespace-nowrap text-blue-600 uppercase dark:text-blue-400"
                                    >
                                        Sale: {{ alloc.sale.sale_number }}
                                    </span>
                                </div>
                                <div
                                    v-if="
                                        !cheque.chequeable?.allocations?.length
                                    "
                                    class="text-[8px] font-bold text-slate-300 uppercase italic"
                                >
                                    Advance Payment
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-1.5 text-right">
                        <span
                            class="text-xs font-black tracking-tighter text-foreground tabular-nums"
                        >
                            {{
                                formatCurrency(cheque.amount, account.currency)
                            }}
                        </span>
                    </td>
                    <td class="px-6 py-1.5">
                        <div class="flex flex-col items-center gap-1">
                            <span
                                :class="{
                                    'border-blue-500/20 bg-blue-500/10 text-blue-500':
                                        cheque.status === 'Collected',
                                    'border-orange-500/20 bg-orange-500/10 text-orange-500':
                                        cheque.status === 'Pending',
                                    'border-emerald-500/20 bg-emerald-500/10 text-emerald-500':
                                        cheque.status === 'Cleared',
                                    'border-rose-500/20 bg-rose-500/10 text-rose-500':
                                        cheque.status === 'Cancelled',
                                }"
                                class="inline-flex items-center rounded-full border px-2 py-0.5 text-[8px] font-black tracking-wider uppercase"
                            >
                                {{ cheque.status }}
                            </span>
                        </div>
                    </td>
                    <td
                        class="sticky right-0 w-[110px] border-l bg-card/90 px-4 py-1.5 text-right shadow-sm backdrop-blur-sm transition-colors group-hover:bg-accent/10"
                    >
                        <div class="flex items-center justify-end gap-1.5 px-1">
                            <template
                                v-if="
                                    cheque.status === 'Pending' ||
                                    cheque.status === 'Collected'
                                "
                            >
                                <button
                                    v-if="
                                        cheque.status === 'Pending' &&
                                        cheque.direction === 'Outward' &&
                                        canCollect
                                    "
                                    @click="$emit('collect', cheque)"
                                    title="Collect Cheque"
                                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 shadow-sm transition-all hover:bg-blue-50 hover:text-white"
                                >
                                    <ClipboardCheck class="h-3.5 w-3.5" />
                                </button>
                                <button
                                    v-if="canClear"
                                    @click="$emit('clear', cheque)"
                                    :disabled="
                                        cheque.direction === 'Outward' &&
                                        !cheque.collected_by_name
                                    "
                                    :title="
                                        cheque.direction === 'Outward' &&
                                        !cheque.collected_by_name
                                            ? 'Collector name required first'
                                            : 'Clear Cheque'
                                    "
                                    :class="[
                                        'flex h-7 w-7 items-center justify-center rounded-lg shadow-sm transition-all',
                                        cheque.direction === 'Outward' &&
                                        !cheque.collected_by_name
                                            ? 'cursor-not-allowed bg-slate-100 text-slate-400 opacity-50'
                                            : 'bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500 hover:text-white',
                                    ]"
                                >
                                    <CheckCircle class="h-3.5 w-3.5" />
                                </button>
                                <button
                                    v-if="canCancel"
                                    @click="$emit('cancel', cheque)"
                                    :disabled="
                                        cheque.direction === 'Outward' &&
                                        !cheque.collected_by_name
                                    "
                                    :title="
                                        cheque.direction === 'Outward' &&
                                        !cheque.collected_by_name
                                            ? 'Collector name required first'
                                            : 'Cancel/Void Cheque'
                                    "
                                    :class="[
                                        'flex h-7 w-7 items-center justify-center rounded-lg shadow-sm transition-all',
                                        cheque.direction === 'Outward' &&
                                        !cheque.collected_by_name
                                            ? 'cursor-not-allowed bg-slate-100 text-slate-400 opacity-50'
                                            : 'bg-rose-500/10 text-rose-600 hover:bg-rose-500 hover:text-white',
                                    ]"
                                >
                                    <XCircle class="h-3.5 w-3.5" />
                                </button>
                            </template>

                            <!-- Print Cover Receipt action -->
                            <button
                                v-if="
                                    (cheque.direction === 'Outward' &&
                                        (cheque.status === 'Collected' ||
                                            cheque.status === 'Cleared')) ||
                                    (cheque.direction === 'Inward' &&
                                        cheque.status !== 'Cancelled')
                                "
                                @click="printChequeReceipt(cheque.id)"
                                :disabled="isPrintingCheque === cheque.id"
                                :title="
                                    isPrintingCheque === cheque.id
                                        ? 'Preparing print...'
                                        : (cheque.direction === 'Outward'
                                           ? 'Print GRN Cover Receipt'
                                           : 'Print Cheque Receipt')
                                "
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded border border-border/50 text-orange-600 shadow-sm transition-all hover:bg-accent/10 hover:text-orange-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <Loader2 v-if="isPrintingCheque === cheque.id" class="h-3.5 w-3.5 animate-spin" />
                                <Printer v-else class="h-3.5 w-3.5" />
                            </button>

                            <button
                                @click="$emit('view-details', cheque)"
                                title="View Details"
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded border border-border/50 text-muted-foreground shadow-sm transition-all hover:bg-accent/10 hover:text-foreground"
                            >
                                <Eye class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <div
            v-else
            class="flex flex-col items-center justify-center gap-4 py-24 font-black tracking-widest uppercase opacity-20"
        >
            <Clock class="h-12 w-12" />
            <div class="text-[10px]">No Uncleared Cheques Found</div>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: hsla(var(--muted-foreground), 0.1);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: hsla(var(--muted-foreground), 0.2);
}
</style>
