<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { router } from '@inertiajs/vue3';
import {
    Banknote,
    Check,
    Clock,
    Coins,
    CreditCard,
    Eye,
    History,
    ShieldCheck,
    X,
} from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    account: any;
    payouts: any;
}>();

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

const processing = ref<number | null>(null);

const approvePayout = (payout: any) => {
    openDetails(payout, true);
};

const isApprovalMode = ref(false);
const selectedPayout = ref<any>(null);
const showDetailsModal = ref(false);

const openDetails = (payout: any, approval = false) => {
    selectedPayout.value = payout;
    isApprovalMode.value = approval;
    showDetailsModal.value = true;
};

const confirmApproval = () => {
    if (!selectedPayout.value) return;

    processing.value = selectedPayout.value.id;
    router.post(
        `/organization/financial-profiles/payouts/${selectedPayout.value.id}/approve`,
        {},
        {
            onSuccess: () => {
                showDetailsModal.value = false;
            },
            onFinish: () => (processing.value = null),
        },
    );
};

const cancelPayout = (id?: number) => {
    const payoutId = id || selectedPayout.value?.id;
    if (!payoutId) return;
    if (!confirm('Are you sure you want to cancel this payout request?'))
        return;

    processing.value = payoutId;
    router.post(
        `/organization/financial-profiles/payouts/${payoutId}/cancel`,
        {},
        {
            onSuccess: () => {
                showDetailsModal.value = false;
            },
            onFinish: () => (processing.value = null),
        },
    );
};
</script>

<template>
    <div class="space-y-4 pb-6">
        <div
            class="overflow-hidden rounded-lg border border-border bg-card/40 shadow-sm backdrop-blur-xl"
        >
            <div
                class="flex items-center justify-between border-b border-border bg-rose-500/5 px-4 py-2.5"
            >
                <div class="flex items-center gap-2">
                    <Banknote class="h-3.5 w-3.5 text-rose-500" />
                    <span
                        class="text-[10px] font-black tracking-[0.2em] text-muted-foreground uppercase"
                        >Pending Account Payouts</span
                    >
                </div>
                <div class="flex items-center gap-4">
                    <span
                        class="text-[9px] font-black tracking-widest text-muted-foreground/60 uppercase"
                    >
                        Showing {{ payouts.from || 1 }}-{{
                            payouts.to || (payouts.data || payouts).length
                        }}
                        of
                        {{ payouts.total || (payouts.data || payouts).length }}
                        Entries
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-separate border-spacing-0">
                    <thead>
                        <tr class="bg-muted/30">
                            <th
                                class="border-b border-border px-4 py-2 text-left text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                            >
                                Initiated
                            </th>
                            <th
                                class="border-b border-border px-4 py-2 text-left text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                            >
                                Reference / Entity
                            </th>
                            <th
                                class="border-b border-border px-4 py-2 text-left text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                            >
                                Description / Type
                            </th>
                            <th
                                class="border-b border-border px-4 py-2 text-right text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                            >
                                Amount
                            </th>
                            <th
                                class="border-b border-border px-4 py-2 text-right text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/50">
                        <tr
                            v-for="p in payouts.data || payouts"
                            :key="p.id"
                            class="group transition-colors hover:bg-emerald-500/10 dark:hover:bg-emerald-500/20"
                        >
                            <td class="px-4 py-1.5 whitespace-nowrap">
                                <div class="flex flex-col">
                                    <span
                                        class="text-[10px] font-bold text-foreground"
                                        >{{
                                            formatDate(
                                                p.initiated_at || p.created_at,
                                            )
                                        }}</span
                                    >
                                </div>
                            </td>
                            <td class="px-4 py-1.5">
                                <div class="flex flex-col">
                                    <template v-if="p.expense">
                                        <span
                                            class="text-[10px] font-black tracking-tighter text-foreground uppercase"
                                            >{{
                                                p.expense.expense_number
                                            }}</span
                                        >
                                        <span
                                            v-if="p.expense.notes"
                                            class="mt-0.5 max-w-[200px] truncate text-[8px] font-bold text-muted-foreground uppercase opacity-60"
                                            >{{ p.expense.notes }}</span
                                        >
                                    </template>
                                    <template v-else-if="p.payroll">
                                        <span
                                            class="text-[10px] font-black tracking-tighter text-foreground uppercase"
                                            >{{
                                                p.payroll.employee
                                                    ?.official_names
                                            }}</span
                                        >
                                        <span
                                            class="mt-0.5 text-[8px] font-bold text-muted-foreground uppercase opacity-60"
                                            >ID:
                                            {{
                                                p.payroll.employee
                                                    ?.employee_number
                                            }}</span
                                        >
                                    </template>
                                    <template v-else>
                                        <span
                                            class="text-[10px] font-black tracking-tighter text-foreground uppercase"
                                            >N/A</span
                                        >
                                    </template>
                                </div>
                            </td>
                            <td class="px-4 py-1.5">
                                <template v-if="p.expense">
                                    <span
                                        class="rounded border border-border/50 bg-muted px-1.5 py-0.5 text-[9px] font-black uppercase"
                                        >{{
                                            p.expense.category?.name ||
                                            'Expense'
                                        }}</span
                                    >
                                </template>
                                <template v-else-if="p.payroll">
                                    <span
                                        class="rounded border border-emerald-500/20 bg-emerald-500/10 px-1.5 py-0.5 text-[9px] font-black text-emerald-600 uppercase"
                                        >Salary:
                                        {{
                                            new Date(
                                                2000,
                                                p.payroll.month - 1,
                                            ).toLocaleString('default', {
                                                month: 'short',
                                            })
                                        }}
                                        {{ p.payroll.year }}</span
                                    >
                                </template>
                                <span
                                    v-else
                                    class="rounded border border-border/50 bg-muted px-1.5 py-0.5 text-[9px] font-black uppercase"
                                    >Other</span
                                >
                            </td>
                            <td class="px-4 py-1.5 text-right">
                                <div class="flex flex-col items-end">
                                    <span
                                        class="text-[11px] font-black tracking-tighter text-rose-500 tabular-nums"
                                    >
                                        {{ formatCurrency(p.amount) }}
                                    </span>
                                    <span
                                        v-if="p.denominations?.length"
                                        class="mt-0.5 flex items-center gap-1 text-[7px] font-black tracking-tighter text-slate-400 uppercase"
                                    >
                                        <Coins
                                            class="h-2.5 w-2.5 text-amber-500"
                                        />
                                        Denominations Attached
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-1.5 text-right">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <button
                                        v-if="p.denominations?.length"
                                        @click="openDetails(p)"
                                        title="View Breakdown"
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded border border-border/50 text-muted-foreground shadow-sm transition-all hover:bg-accent/10 hover:text-foreground"
                                    >
                                        <Eye class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        @click="approvePayout(p)"
                                        :disabled="processing === p.id"
                                        title="Approve Payout"
                                        class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 shadow-sm transition-all hover:bg-emerald-500 hover:text-white disabled:opacity-50"
                                    >
                                        <Check
                                            v-if="processing !== p.id"
                                            class="h-3.5 w-3.5"
                                        />
                                        <Clock
                                            v-else
                                            class="h-3.5 w-3.5 animate-spin"
                                        />
                                    </button>
                                    <button
                                        @click="cancelPayout(p.id)"
                                        :disabled="processing === p.id"
                                        title="Cancel Payout"
                                        class="flex h-7 w-7 items-center justify-center rounded-lg bg-rose-500/10 text-rose-600 shadow-sm transition-all hover:bg-rose-500 hover:text-white disabled:opacity-50"
                                    >
                                        <X
                                            v-if="processing !== p.id"
                                            class="h-3.5 w-3.5"
                                        />
                                        <Clock
                                            v-else
                                            class="h-3.5 w-3.5 animate-spin"
                                        />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr
                            v-if="
                                (!payouts.data && payouts.length === 0) ||
                                (payouts.data && payouts.data.length === 0)
                            "
                        >
                            <td
                                colspan="5"
                                class="p-12 text-center text-muted-foreground"
                            >
                                <div
                                    class="flex flex-col items-center gap-3 opacity-20"
                                >
                                    <History class="h-10 w-10" />
                                    <div
                                        class="text-[10px] font-black tracking-[0.3em] uppercase"
                                    >
                                        No pending payouts detected
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer Pagination -->
            <div
                v-if="payouts.links && payouts.links.length > 3"
                class="shrink-0 border-t border-border bg-card/60 px-4 py-3"
            >
                <Pagination
                    :links="payouts.links"
                    :from="payouts.from"
                    :to="payouts.to"
                    :total="payouts.total"
                />
            </div>
        </div>

        <!-- Details Modal -->
        <Dialog
            :open="showDetailsModal"
            @update:open="showDetailsModal = $event"
        >
            <DialogContent
                class="overflow-hidden rounded-2xl border-none bg-background p-0 shadow-2xl sm:max-w-[460px]"
            >
                <!-- Header -->
                <div
                    class="relative shrink-0 border-b border-border bg-white px-5 py-3.5"
                >
                    <div
                        class="absolute top-4 left-0 h-6 w-1 rounded-r-full bg-emerald-500"
                    ></div>
                    <DialogHeader>
                        <DialogTitle
                            class="flex items-center gap-2 text-base font-black tracking-tight text-slate-900 uppercase"
                        >
                            <Banknote class="h-4 w-4 text-emerald-500" />
                            {{
                                isApprovalMode
                                    ? 'Authorize Payout'
                                    : 'Payout Details'
                            }}
                        </DialogTitle>
                        <DialogDescription
                            class="mt-0.5 flex items-center gap-1.5 text-[9px] font-bold tracking-widest text-muted-foreground uppercase"
                        >
                            <ShieldCheck class="h-2.5 w-2.5 text-emerald-500" />
                            {{
                                selectedPayout?.expense?.expense_number ||
                                'PAYROLL DISBURSEMENT'
                            }}
                            — {{ formatDate(selectedPayout?.created_at || '') }}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <!-- Content Area -->
                <div class="space-y-4 bg-white p-4">
                    <!-- Context Card -->
                    <div
                        class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 p-3"
                    >
                        <div class="flex flex-col">
                            <span
                                class="mb-1 text-[8px] leading-none font-black tracking-widest text-slate-400 uppercase"
                                >Recipient Entity</span
                            >
                            <span
                                class="text-[11px] font-black tracking-tight text-slate-800 uppercase"
                            >
                                {{
                                    selectedPayout?.expense?.expense_number ||
                                    selectedPayout?.payroll?.employee
                                        ?.official_names
                                }}
                            </span>
                        </div>
                        <div class="flex flex-col items-end">
                            <span
                                class="mb-1 text-[8px] leading-none font-black tracking-widest text-slate-400 uppercase"
                                >Category</span
                            >
                            <span
                                class="rounded-lg border bg-white px-2 py-0.5 text-[8px] font-black uppercase shadow-sm"
                                :class="
                                    selectedPayout?.payroll
                                        ? 'border-emerald-100 text-emerald-600'
                                        : 'border-blue-100 text-blue-600'
                                "
                            >
                                {{
                                    selectedPayout?.payroll
                                        ? 'Payroll'
                                        : 'Expense'
                                }}
                            </span>
                        </div>
                    </div>

                    <!-- Denominations Section -->
                    <div
                        v-if="selectedPayout?.denominations?.length"
                        class="space-y-3"
                    >
                        <div class="flex items-center justify-between px-1">
                            <div class="flex items-center gap-2">
                                <Coins class="h-3 w-3 text-emerald-500" />
                                <h4
                                    class="text-[9px] font-black tracking-widest text-slate-800 uppercase"
                                >
                                    Cash Breakdown
                                </h4>
                            </div>
                            <span
                                class="text-[8px] font-bold tracking-tighter text-slate-400 uppercase"
                                >Verified</span
                            >
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div
                                v-for="td in [
                                    ...(selectedPayout?.denominations || []),
                                ].sort(
                                    (a, b) =>
                                        Number(b.denomination?.value) -
                                        Number(a.denomination?.value),
                                )"
                                :key="td.id"
                                class="group flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-2 transition-all hover:border-emerald-200 hover:bg-white"
                            >
                                <div class="flex flex-col">
                                    <span
                                        class="text-[10px] leading-none font-black text-slate-700"
                                        >{{ td.denomination?.value }}</span
                                    >
                                    <span
                                        class="mt-1 text-[7px] leading-none font-black tracking-widest text-slate-400 uppercase"
                                        >Denom</span
                                    >
                                </div>
                                <div class="flex flex-col items-end text-right">
                                    <span
                                        class="text-[11px] leading-none font-black text-emerald-600"
                                        >x {{ td.quantity }}</span
                                    >
                                    <span
                                        class="mt-1 text-[7px] leading-none font-black tracking-widest text-slate-400 uppercase"
                                        >Qty</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <Separator class="bg-slate-100/50" />

                    <!-- Summary Area -->
                    <div
                        class="rounded-2xl border border-emerald-100/60 bg-emerald-50/40 p-3.5"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex flex-col">
                                <span
                                    class="text-[9px] font-black tracking-[0.2em] text-emerald-700 uppercase"
                                    >Net Amount</span
                                >
                                <span
                                    class="mt-1 flex items-center gap-1 text-[7px] font-bold tracking-widest text-slate-400 uppercase"
                                >
                                    <CreditCard class="h-2.5 w-2.5" /> via
                                    {{ account.name }}
                                </span>
                            </div>
                            <div class="flex flex-col items-end text-right">
                                <div class="flex items-baseline gap-1">
                                    <span
                                        class="text-[9px] font-black text-emerald-500 italic"
                                        >KES</span
                                    >
                                    <span
                                        class="text-xl leading-none font-black tracking-tighter text-slate-900 tabular-nums"
                                        >{{
                                            formatCurrency(
                                                selectedPayout?.amount,
                                            )
                                                .replace('KES', '')
                                                .replace('KSh', '')
                                                .trim()
                                        }}</span
                                    >
                                </div>
                                <span
                                    class="mt-0.5 text-[7px] font-black tracking-[0.3em] text-slate-400 uppercase"
                                    >Confirmed</span
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div
                    class="flex items-center justify-between gap-4 border-t border-border bg-slate-50/50 px-5 py-3"
                >
                    <button
                        @click="showDetailsModal = false"
                        class="rounded-xl px-3 py-1.5 text-[9px] font-black tracking-widest text-slate-400 uppercase transition-all hover:text-slate-600"
                    >
                        {{ isApprovalMode ? 'Cancel' : 'Close' }}
                    </button>

                    <div class="flex items-center gap-3">
                        <button
                            v-if="isApprovalMode"
                            @click="cancelPayout()"
                            :disabled="!!processing"
                            class="rounded-xl border border-rose-100/50 bg-rose-50 px-4 py-1.5 text-[9px] font-black tracking-widest text-rose-600 uppercase transition-all hover:bg-rose-500 hover:text-white disabled:opacity-50"
                        >
                            Reject
                        </button>
                        <button
                            v-if="isApprovalMode"
                            @click="confirmApproval"
                            :disabled="!!processing"
                            class="rounded-xl bg-emerald-600 px-6 py-1.5 text-[9px] font-black tracking-widest text-white uppercase shadow-lg shadow-emerald-500/20 transition-all hover:bg-emerald-500 disabled:opacity-50"
                        >
                            <span
                                v-if="!processing"
                                class="flex items-center gap-2"
                            >
                                <Check class="h-3.5 w-3.5" /> Authorize & Pay
                            </span>
                            <span v-else class="flex items-center gap-2">
                                <Clock class="h-3.5 w-3.5 animate-spin" />
                                Finalizing...
                            </span>
                        </button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
