<script setup lang="ts">
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowDownRight,
    ArrowUp,
    ArrowUpDown,
    ArrowUpRight,
    ChevronLeft,
    ChevronRight,
    Edit,
    Eye,
    Trash2,
    Wallet,
} from 'lucide-vue-next';

interface PaymentAccount {
    id: number;
    company_id: number;
    branch_id: number;
    name: string;
    payment_method_id: number;
    payment_method?: { id: number; name: string };
    account_number: string | null;
    bank_id: number | null;
    bank?: { id: number; name: string; code?: string | null; paybill_number?: string | null };
    bank_name: string | null;
    currency: string;
    balance: string;
    pending_cheque_balance: string;
    pending_in_count: number;
    pending_out_count: number;
    pending_in_amount: string | number;
    pending_out_amount: string | number;
    is_active: boolean;
    is_vault: boolean;
    can_make_payments: boolean;
    can_receive_payments: boolean;
    requires_reconciliation: boolean;
    company?: { id: number; name: string };
    branch?: { id: number; name: string };
    user?: { id: number; name: string };
    account?: { id: number; code: string };
}

interface Props {
    paymentAccounts: {
        data: PaymentAccount[];
        links: any[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    sortBy?: string;
    sortDirection?: string;
    columnVisibility?: Record<string, boolean>;
}

const props = withDefaults(defineProps<Props>(), {
    sortBy: 'balance',
    sortDirection: 'desc',
    columnVisibility: () => ({
        ref: true,
        name: true,
        ownership: true,
        method: true,
        details: true,
        balance: true,
        status: true,
    }),
});

const visibleColumnCount = computed(() => {
    return Object.values(props.columnVisibility).filter(Boolean).length + 1;
});

defineEmits<{
    (e: 'edit', item: PaymentAccount): void;
    (e: 'delete', item: PaymentAccount): void;
    (e: 'sort', column: string): void;
}>();

const goToPage = (url: string | null) => {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
};

const formatCurrency = (amount: string | number, currency: string) => {
    return new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: currency,
    }).format(Number(amount));
};
</script>

<template>
    <div class="custom-scrollbar flex-1 overflow-auto">
        <table class="w-full border-separate border-spacing-0">
            <thead
                class="sticky top-0 z-10 bg-card/95 font-black tracking-widest text-muted-foreground uppercase backdrop-blur-md"
            >
                <tr class="select-none">
                    <th
                        v-if="columnVisibility.ref !== false"
                        @click="$emit('sort', 'id')"
                        class="w-12 cursor-pointer border-b border-border bg-accent/5 px-4 py-2.5 text-left text-[10px] hover:bg-accent/10 transition-colors"
                    >
                        <div class="flex items-center gap-1">
                            <span>REF</span>
                            <component
                                :is="sortBy === 'id' ? (sortDirection === 'asc' ? ArrowUp : ArrowDown) : ArrowUpDown"
                                class="h-3 w-3 opacity-60"
                            />
                        </div>
                    </th>
                    <th
                        v-if="columnVisibility.name !== false"
                        @click="$emit('sort', 'name')"
                        class="cursor-pointer border-b border-border bg-accent/5 px-4 py-2.5 text-left text-[10px] hover:bg-accent/10 transition-colors"
                    >
                        <div class="flex items-center gap-1">
                            <span>ACCOUNT NAME</span>
                            <component
                                :is="sortBy === 'name' ? (sortDirection === 'asc' ? ArrowUp : ArrowDown) : ArrowUpDown"
                                class="h-3 w-3 opacity-60"
                            />
                        </div>
                    </th>
                    <th
                        v-if="columnVisibility.ownership !== false"
                        class="border-b border-border bg-accent/5 px-4 py-2.5 text-left text-[10px]"
                    >
                        OWNERSHIP
                    </th>
                    <th
                        v-if="columnVisibility.method !== false"
                        class="border-b border-border bg-accent/5 px-4 py-2.5 text-left text-[10px]"
                    >
                        METHOD
                    </th>
                    <th
                        v-if="columnVisibility.details !== false"
                        @click="$emit('sort', 'account_number')"
                        class="cursor-pointer border-b border-border bg-accent/5 px-4 py-2.5 text-left text-[10px] hover:bg-accent/10 transition-colors"
                    >
                        <div class="flex items-center gap-1">
                            <span>ACCOUNT DETAILS</span>
                            <component
                                :is="sortBy === 'account_number' ? (sortDirection === 'asc' ? ArrowUp : ArrowDown) : ArrowUpDown"
                                class="h-3 w-3 opacity-60"
                            />
                        </div>
                    </th>
                    <th
                        v-if="columnVisibility.balance !== false"
                        @click="$emit('sort', 'balance')"
                        class="cursor-pointer border-b border-border bg-accent/5 px-4 py-2.5 text-right text-[10px] hover:bg-accent/10 transition-colors"
                    >
                        <div class="flex items-center justify-end gap-1">
                            <span>BALANCE</span>
                            <component
                                :is="sortBy === 'balance' ? (sortDirection === 'asc' ? ArrowUp : ArrowDown) : ArrowUpDown"
                                class="h-3 w-3"
                                :class="sortBy === 'balance' ? 'text-primary opacity-100' : 'opacity-60'"
                            />
                        </div>
                    </th>
                    <th
                        v-if="columnVisibility.status !== false"
                        @click="$emit('sort', 'is_active')"
                        class="cursor-pointer border-b border-border bg-accent/5 px-4 py-2.5 text-center text-[10px] hover:bg-accent/10 transition-colors"
                    >
                        <div class="flex items-center justify-center gap-1">
                            <span>STATUS & SCOPE</span>
                            <component
                                :is="sortBy === 'is_active' ? (sortDirection === 'asc' ? ArrowUp : ArrowDown) : ArrowUpDown"
                                class="h-3 w-3 opacity-60"
                            />
                        </div>
                    </th>
                    <th
                        class="shadow-l sticky right-0 w-24 border-b border-l border-border bg-accent/5 px-4 py-2.5 text-right text-[10px] backdrop-blur-md"
                    >
                        ACTIONS
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                <tr
                    v-for="item in paymentAccounts.data"
                    :key="item.id"
                    class="group transition-colors hover:bg-accent/5"
                >
                    <td
                        v-if="columnVisibility.ref !== false"
                        class="w-12 px-4 py-2.5 text-[10px] font-black text-muted-foreground"
                    >
                        REF-{{ item.id }}
                    </td>
                    <td v-if="columnVisibility.name !== false" class="px-4 py-2.5">
                        <Link
                            :href="`/organization/financial-profiles/payment-accounts/${item.id}`"
                            class="group/link flex flex-col"
                        >
                            <div class="flex items-center gap-1.5">
                                <span
                                    v-if="item.account"
                                    class="py-0.2 rounded border border-border/50 bg-accent/20 px-1 font-mono text-[9px] font-black text-muted-foreground"
                                    >{{ item.account.code }}</span
                                >
                                <span
                                    class="text-xs font-bold tracking-tight text-foreground uppercase transition-colors group-hover/link:text-emerald-500"
                                    >{{ item.name }}</span
                                >
                            </div>
                            <span
                                class="mt-0.5 text-[9px] font-black tracking-tighter text-muted-foreground uppercase"
                                >{{ item.currency }}</span
                            >
                        </Link>
                    </td>
                    <td v-if="columnVisibility.ownership !== false" class="px-4 py-2.5">
                        <div class="flex flex-col">
                            <span
                                class="text-[10px] font-black tracking-tight text-foreground uppercase"
                                >{{ item.company?.name || '-' }}</span
                            >
                            <span
                                class="text-[9px] font-bold tracking-tighter text-muted-foreground uppercase"
                                >{{ item.branch?.name || '-' }}</span
                            >
                            <div
                                v-if="item.user"
                                class="mt-1 flex items-center gap-1.5 border-t border-border/40 pt-1 opacity-70 grayscale transition-all group-hover:opacity-100 group-hover:grayscale-0"
                            >
                                <div
                                    class="h-1.5 w-1.5 animate-pulse rounded-full bg-blue-500"
                                ></div>
                                <span
                                    class="max-w-[100px] truncate text-[8px] font-black tracking-widest text-blue-600 uppercase"
                                    >{{ item.user.name }}</span
                                >
                            </div>
                        </div>
                    </td>
                    <td v-if="columnVisibility.method !== false" class="px-4 py-2.5">
                        <span
                            class="inline-flex items-center rounded border border-border/50 bg-muted/50 px-1.5 py-0.5 text-[9px] font-black tracking-tighter text-muted-foreground uppercase"
                        >
                            {{ item.payment_method?.name || '-' }}
                        </span>
                    </td>
                    <td v-if="columnVisibility.details !== false" class="px-4 py-2.5">
                        <div class="flex flex-col">
                            <span
                                v-if="item.bank"
                                class="max-w-[120px] truncate text-[10px] font-bold text-foreground uppercase"
                                >{{ item.bank.name }}</span
                            >
                            <span
                                v-else-if="item.bank_name"
                                class="max-w-[120px] truncate text-[10px] font-bold text-foreground uppercase"
                                >{{ item.bank_name }}</span
                            >
                            <span
                                v-if="item.bank?.paybill_number"
                                class="text-[9px] font-bold text-indigo-600"
                            >
                                Paybill: <span class="font-mono">{{ item.bank.paybill_number }}</span>
                            </span>
                            <span
                                v-if="item.account_number"
                                class="mt-0.5 text-[9px] font-black tracking-widest text-muted-foreground tabular-nums"
                                >{{ item.account_number }}</span
                            >
                            <span
                                v-if="
                                    !item.bank &&
                                    !item.bank_name &&
                                    !item.account_number
                                "
                                class="text-[9px] text-muted-foreground/40 italic"
                                >N/A</span
                            >
                        </div>
                    </td>
                    <td v-if="columnVisibility.balance !== false" class="px-4 py-2.5 text-right">
                        <div class="flex flex-col items-end">
                            <span
                                class="text-xs font-black tracking-tighter text-foreground tabular-nums"
                            >
                                {{
                                    formatCurrency(item.balance, item.currency)
                                }}
                            </span>

                            <!-- Pending Inward -->
                            <div
                                v-if="Number(item.pending_in_amount) > 0"
                                class="mt-1 flex items-center gap-1 rounded border border-emerald-500/10 bg-emerald-500/5 px-1.5 py-0.5 text-[9px] font-black tracking-tighter text-emerald-600 uppercase shadow-sm"
                                title="Incoming Transfers Awaiting Your Approval"
                            >
                                <ArrowDownRight class="h-3 w-3" />
                                <span
                                    class="mr-1 rounded-sm bg-emerald-600 px-1 text-[8px] text-white"
                                    >{{ item.pending_in_count }}</span
                                >
                                +{{
                                    formatCurrency(
                                        item.pending_in_amount,
                                        item.currency,
                                    )
                                }}
                            </div>

                            <!-- Pending Outward (Deducted) -->
                            <div
                                v-if="Number(item.pending_out_amount) > 0"
                                class="mt-1 flex items-center gap-1 rounded border border-orange-500/10 bg-orange-500/5 px-1.5 py-0.5 text-[9px] font-black tracking-tighter text-orange-600 uppercase shadow-sm"
                                title="Outgoing Transfers Sent (Deducted, Awaiting Receipt)"
                            >
                                <ArrowUpRight class="h-3 w-3" />
                                <span
                                    class="mr-1 rounded-sm bg-orange-600 px-1 text-[8px] text-white"
                                    >{{ item.pending_out_count }}</span
                                >
                                -{{
                                    formatCurrency(
                                        item.pending_out_amount,
                                        item.currency,
                                    )
                                }}
                            </div>

                            <span
                                v-if="Number(item.pending_cheque_balance) > 0"
                                class="mt-1 text-[8px] font-black tracking-widest text-blue-600 uppercase opacity-60"
                            >
                                uncleared chqs:
                                {{
                                    formatCurrency(
                                        item.pending_cheque_balance,
                                        item.currency,
                                    )
                                }}
                            </span>
                        </div>
                    </td>
                    <td v-if="columnVisibility.status !== false" class="px-4 py-2.5">
                        <div class="flex flex-col items-center gap-1">
                            <span
                                :class="
                                    item.is_active
                                        ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-500'
                                        : 'border-rose-500/20 bg-rose-500/10 text-rose-500'
                                "
                                class="inline-flex items-center rounded-full border px-2 py-0.5 text-[9px] font-black tracking-wider uppercase"
                            >
                                {{ item.is_active ? 'Active' : 'Inactive' }}
                            </span>
                            <div class="flex flex-wrap justify-center gap-1">
                                <span
                                    v-if="item.is_vault"
                                    class="inline-flex items-center rounded-sm bg-blue-600 px-1 py-0.5 text-[8px] font-black tracking-tighter text-white uppercase"
                                >
                                    VAULT
                                </span>
                                <span
                                    v-if="item.can_make_payments"
                                    class="inline-flex items-center rounded-sm border border-blue-500/10 bg-blue-500/10 px-1 py-0.5 text-[8px] font-black tracking-tighter text-blue-500 uppercase"
                                >
                                    PAY
                                </span>
                                <span
                                    v-if="item.can_receive_payments"
                                    class="inline-flex items-center rounded-sm border border-purple-500/10 bg-purple-500/10 px-1 py-0.5 text-[8px] font-black tracking-tighter text-purple-500 uppercase"
                                >
                                    REC
                                </span>
                                <span
                                    v-if="item.requires_reconciliation"
                                    class="inline-flex items-center rounded-sm border border-orange-500/10 bg-orange-500/10 px-1 py-0.5 text-[8px] font-black tracking-tighter text-orange-600 uppercase"
                                >
                                    RECON
                                </span>
                            </div>
                        </div>
                    </td>
                    <td
                        class="sticky right-0 w-24 border-l bg-card/95 px-4 py-2.5 text-right shadow-sm backdrop-blur-md transition-colors group-hover:bg-accent/10"
                    >
                        <div class="flex items-center justify-end gap-1">
                            <Link
                                :href="`/organization/financial-profiles/payment-accounts/${item.id}`"
                                class="rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-emerald-500/10 hover:text-emerald-500"
                                title="View Profile"
                            >
                                <Eye class="h-3.5 w-3.5" />
                            </Link>
                            <button
                                @click="$emit('edit', item)"
                                class="rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-accent/10 hover:text-foreground"
                                title="Edit"
                            >
                                <Edit class="h-3.5 w-3.5" />
                            </button>
                            <button
                                @click="$emit('delete', item)"
                                class="rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-rose-500/10 hover:text-rose-500"
                                title="Delete"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </td>
                </tr>
                <tr v-if="paymentAccounts.data.length === 0">
                    <td :colspan="visibleColumnCount" class="p-16 text-center">
                        <div
                            class="flex flex-col items-center gap-2 opacity-20"
                        >
                            <Wallet class="h-8 w-8" />
                            <div
                                class="text-[10px] font-black tracking-widest uppercase"
                            >
                                No terminal payment records found.
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Compact Footer -->
        <div
            class="sticky bottom-0 z-10 flex shrink-0 items-center justify-between border-t border-border bg-accent/5 px-4 py-2"
        >
            <div
                class="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
            >
                Total {{ paymentAccounts.total }} Accounts
            </div>
            <div class="flex items-center gap-2">
                <button
                    @click="goToPage(paymentAccounts.links[0]?.url)"
                    :disabled="paymentAccounts.current_page === 1"
                    class="rounded-lg p-1 text-muted-foreground transition-colors hover:bg-accent/10 hover:text-foreground disabled:cursor-not-allowed disabled:opacity-30"
                >
                    <ChevronLeft class="h-4 w-4" />
                </button>
                <div class="flex gap-1">
                    <button
                        v-for="(link, index) in paymentAccounts.links.slice(
                            1,
                            -1,
                        )"
                        :key="index"
                        @click="goToPage(link.url)"
                        :class="[
                            'flex h-6 min-w-[1.5rem] items-center justify-center rounded-lg px-2 text-[10px] font-black transition-colors',
                            link.active
                                ? 'bg-foreground text-background shadow-md'
                                : 'text-muted-foreground hover:bg-accent/10 hover:text-foreground',
                        ]"
                        v-html="link.label"
                    ></button>
                </div>
                <button
                    @click="
                        goToPage(
                            paymentAccounts.links[
                                paymentAccounts.links.length - 1
                            ]?.url,
                        )
                    "
                    :disabled="
                        paymentAccounts.current_page ===
                        paymentAccounts.last_page
                    "
                    class="rounded-lg p-1 text-muted-foreground transition-colors hover:bg-accent/10 hover:text-foreground disabled:cursor-not-allowed disabled:opacity-30"
                >
                    <ChevronRight class="h-4 w-4" />
                </button>
            </div>
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
    background: hsla(var(--muted-foreground), 0.2);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: hsla(var(--muted-foreground), 0.3);
}
</style>
