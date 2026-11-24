<script setup lang="ts">
import { ref } from 'vue';
import {
    Activity,
    Banknote,
    History,
    Info,
    Landmark,
    ShieldCheck,
} from 'lucide-vue-next';
import DenominationHistoryModal from '../DenominationHistoryModal.vue';

defineProps<{
    account: any;
}>();

const isHistoryModalOpen = ref(false);
const selectedDenomination = ref<any>(null);

const openDenominationHistory = (denom: any) => {
    selectedDenomination.value = denom;
    isHistoryModalOpen.value = true;
};

const formatCurrency = (amount: number | string) => {
    return Number(amount || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

const labelClass =
    'text-[9px] font-black text-muted-foreground uppercase tracking-widest mb-0.5 block';
</script>

<template>
    <div class="grid grid-cols-12 gap-4 pb-4">
        <div class="col-span-12 space-y-4 lg:col-span-8">
            <!-- Detailed Account Info Card -->
            <div
                class="overflow-hidden rounded-lg border border-border bg-card/40 shadow-sm backdrop-blur-xl"
            >
                <div
                    class="flex items-center gap-2 border-b border-border bg-emerald-500/5 px-4 py-2.5"
                >
                    <Info class="h-3.5 w-3.5 text-emerald-500" />
                    <span
                        class="text-[10px] font-black tracking-[0.2em] text-muted-foreground uppercase"
                        >Account Technical Specifications</span
                    >
                </div>
                <div
                    class="grid grid-cols-1 gap-8 p-6 md:grid-cols-2 lg:grid-cols-3"
                >
                    <div class="space-y-4">
                        <div>
                            <span :class="labelClass">Account Formal Name</span>
                            <p
                                class="text-xs font-black tracking-tight uppercase"
                            >
                                {{ account.name }}
                            </p>
                            <p
                                class="mt-0.5 text-[9px] font-bold text-muted-foreground uppercase opacity-60"
                            >
                                Formal Ledger Title
                            </p>
                        </div>
                        <div>
                            <span :class="labelClass"
                                >Account Number / IBAN</span
                            >
                            <p
                                class="font-mono text-xs font-black tracking-tight tracking-widest uppercase"
                            >
                                {{
                                    account.account_number ||
                                    'INTERNAL_CASH_VAULT'
                                }}
                            </p>
                            <p
                                class="mt-0.5 text-[9px] font-bold text-muted-foreground uppercase opacity-60"
                            >
                                Reference ID
                            </p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <span :class="labelClass"
                                >Institution Information</span
                            >
                            <p
                                class="text-xs font-black tracking-tight uppercase"
                            >
                                {{
                                    account.bank?.name ||
                                    account.bank_name ||
                                    'Direct / Cash'
                                }}
                            </p>
                            <div
                                v-if="account.bank?.paybill_number"
                                class="mt-1 flex items-center gap-1.5"
                            >
                                <span
                                    class="text-[9px] font-bold text-muted-foreground uppercase"
                                    >B2B Paybill:</span
                                >
                                <span
                                    class="rounded border border-indigo-500/20 bg-indigo-500/10 px-1.5 py-0.2 font-mono text-[9px] font-black text-indigo-600"
                                >
                                    {{ account.bank.paybill_number }}
                                </span>
                            </div>
                            <div
                                v-else-if="account.linked_bank_account?.bank?.paybill_number || account.linkedBankAccount?.bank?.paybill_number"
                                class="mt-1 flex items-center gap-1.5"
                            >
                                <span
                                    class="text-[9px] font-bold text-muted-foreground uppercase"
                                    >Settlement Paybill:</span
                                >
                                <span
                                    class="rounded border border-indigo-500/20 bg-indigo-500/10 px-1.5 py-0.2 font-mono text-[9px] font-black text-indigo-600"
                                >
                                    {{ (account.linked_bank_account || account.linkedBankAccount).bank.paybill_number }}
                                </span>
                            </div>
                            <p
                                v-else
                                class="mt-0.5 text-[9px] font-bold text-muted-foreground uppercase opacity-60"
                            >
                                Banking Regulatory Class
                            </p>
                        </div>
                        <div>
                            <span :class="labelClass">Settlement Currency</span>
                            <p
                                class="text-xs font-black tracking-tight uppercase"
                            >
                                {{ account.currency }}
                            </p>
                            <p
                                class="mt-0.5 text-[9px] font-bold text-muted-foreground uppercase opacity-60"
                            >
                                Functional Currency
                            </p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <span :class="labelClass">Ledger Reference</span>
                            <p
                                class="text-xs font-black tracking-tight uppercase"
                            >
                                {{
                                    account.ledger_reference ||
                                    'GL_ACC_' + account.id
                                }}
                            </p>
                            <p
                                class="mt-0.5 text-[9px] font-bold text-muted-foreground uppercase opacity-60"
                            >
                                COA Mapping ID
                            </p>
                        </div>
                        <div>
                            <span :class="labelClass">Ownership Group</span>
                            <p
                                class="truncate text-xs font-black tracking-tight uppercase"
                            >
                                {{ account.company?.name }}
                            </p>
                            <p
                                class="mt-0.5 text-[9px] font-bold text-muted-foreground uppercase opacity-60"
                            >
                                Legal Owner
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cash Breakdown Card (Audit View) -->
            <div
                v-if="
                    account.is_vault ||
                    account.payment_method?.is_mpesa_agent ||
                    account.payment_method?.code === 'mpesa_agent'
                "
                class="overflow-hidden rounded-lg border border-border bg-card/40 shadow-sm backdrop-blur-xl"
            >
                <div
                    class="flex items-center justify-between border-b border-border bg-slate-500/5 px-4 py-2.5"
                >
                    <div class="flex items-center gap-2">
                        <Banknote class="h-3.5 w-3.5 text-slate-500" />
                        <span
                            class="text-[10px] font-black tracking-[0.2em] text-muted-foreground uppercase"
                            >Certified Cash Breakdown</span
                        >
                    </div>
                    <span
                        class="rounded bg-emerald-500/10 px-2 py-0.5 text-[9px] font-black text-emerald-600 uppercase"
                        >Live Audit</span
                    >
                </div>
                <div class="p-6">
                    <div
                        v-if="account.current_denominations?.length"
                        class="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-5"
                    >
                        <div
                            v-for="item in account.current_denominations"
                            :key="item.id"
                            @click="openDenominationHistory(item)"
                            :class="[
                                'group relative flex flex-col gap-1 overflow-hidden rounded border p-3 shadow-xs transition-all cursor-pointer select-none',
                                item.quantity > 0
                                    ? 'border-border bg-card hover:border-emerald-400 hover:shadow-md hover:scale-[1.02] active:scale-95'
                                    : 'border-dashed border-slate-200 bg-slate-50 opacity-50 grayscale hover:opacity-80 hover:border-slate-300',
                            ]"
                            :title="'Click to view note flow history for ' + account.currency + ' ' + Math.round(item.denomination?.value).toLocaleString() + ' notes'"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    :class="
                                        item.quantity > 0
                                            ? 'text-emerald-600'
                                            : 'text-slate-400'
                                    "
                                    class="text-[10px] leading-none font-black tracking-tighter uppercase"
                                >
                                    {{ account.currency }}
                                    {{
                                        Math.round(
                                            item.denomination?.value,
                                        ).toLocaleString()
                                    }}
                                </span>
                                <span class="inline-flex items-center gap-0.5 text-[9px] font-bold text-muted-foreground/60 group-hover:text-emerald-600 transition-colors">
                                    <History class="h-2.5 w-2.5" />
                                    <span class="text-[8px] uppercase tracking-wider">Audit</span>
                                </span>
                            </div>

                            <div class="flex items-baseline justify-between">
                                <span
                                    :class="
                                        item.quantity > 0
                                            ? 'text-slate-900 opacity-100'
                                            : 'text-slate-400'
                                    "
                                    class="text-[11px] leading-none font-black uppercase"
                                    >x{{ item.quantity }}</span
                                >
                                <span
                                    class="text-[11px] leading-none font-black text-slate-900 tabular-nums"
                                >
                                    {{
                                        formatCurrency(
                                            item.quantity *
                                                item.denomination?.value,
                                        )
                                    }}
                                </span>
                            </div>

                            <!-- Indicator bar -->
                            <div
                                class="mt-1.5 h-1 w-full overflow-hidden rounded-full bg-slate-100"
                            >
                                <div
                                    class="h-full bg-emerald-500 transition-all duration-500"
                                    :style="{
                                        width:
                                            item.quantity > 0 ? '100%' : '0%',
                                    }"
                                ></div>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="rounded border border-dashed border-slate-200 bg-slate-50 py-4 text-center"
                    >
                        <p
                            class="text-[10px] leading-relaxed font-black tracking-widest text-slate-400 uppercase"
                        >
                            No verified denominational breakdown available for
                            this account. <br />
                            Please perform a transfer or reconciliation to
                            initialize audit.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Permission Matrix -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div
                    class="flex items-center justify-between rounded-lg border border-border bg-card/40 p-4 shadow-sm backdrop-blur-xl"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-lg border border-emerald-500/20 bg-emerald-500/10 text-emerald-600"
                        >
                            <ShieldCheck class="h-4 w-4" />
                        </div>
                        <div>
                            <span :class="labelClass">Payments</span>
                            <p
                                class="text-[10px] font-black text-foreground uppercase"
                            >
                                Outbound Flow
                            </p>
                        </div>
                    </div>
                    <div
                        :class="
                            account.can_make_payments
                                ? 'border border-emerald-500/20 bg-emerald-500/10 text-emerald-600'
                                : 'border border-border bg-muted text-muted-foreground'
                        "
                        class="rounded px-2 py-0.5 text-[9px] font-black tracking-wider uppercase"
                    >
                        {{ account.can_make_payments ? 'ENABLED' : 'DISABLED' }}
                    </div>
                </div>

                <div
                    class="flex items-center justify-between rounded-lg border border-border bg-card/40 p-4 shadow-sm backdrop-blur-xl"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-lg border border-blue-500/20 bg-blue-500/10 text-blue-600"
                        >
                            <ShieldCheck class="h-4 w-4" />
                        </div>
                        <div>
                            <span :class="labelClass">Receipts</span>
                            <p
                                class="text-[10px] font-black text-foreground uppercase"
                            >
                                Inbound Flow
                            </p>
                        </div>
                    </div>
                    <div
                        :class="
                            account.can_receive_payments
                                ? 'border border-emerald-500/20 bg-emerald-500/10 text-emerald-600'
                                : 'border border-border bg-muted text-muted-foreground'
                        "
                        class="rounded px-2 py-0.5 text-[9px] font-black tracking-wider uppercase"
                    >
                        {{
                            account.can_receive_payments
                                ? 'ENABLED'
                                : 'DISABLED'
                        }}
                    </div>
                </div>

                <div
                    class="flex items-center justify-between rounded-lg border border-border bg-card/40 p-4 shadow-sm backdrop-blur-xl"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-lg border border-emerald-500/20 bg-emerald-500/10 text-emerald-600"
                        >
                            <Activity class="h-4 w-4" />
                        </div>
                        <div>
                            <span :class="labelClass">Availability</span>
                            <p
                                class="text-[10px] font-black text-foreground uppercase"
                            >
                                Account Status
                            </p>
                        </div>
                    </div>
                    <div
                        :class="
                            account.is_active
                                ? 'border border-emerald-500/20 bg-emerald-500/10 text-emerald-600'
                                : 'border-rose-500/20 bg-rose-500/10 text-rose-600'
                        "
                        class="rounded px-2 py-0.5 text-[9px] font-black tracking-wider uppercase"
                    >
                        {{ account.is_active ? 'ACTIVE' : 'INACTIVE' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-span-12 flex flex-col gap-4 lg:col-span-4">
            <!-- Account Position Card -->
            <div
                class="relative flex-1 overflow-hidden rounded-lg border border-border bg-card/40 p-5 shadow-sm backdrop-blur-xl"
            >
                <div
                    class="pointer-events-none absolute -top-12 -right-12 h-32 w-32 rounded-full bg-emerald-600/5 blur-3xl"
                ></div>
                <div class="flex h-full flex-col justify-between">
                    <div>
                        <span
                            class="mb-4 block text-[10px] font-black tracking-widest text-emerald-600 uppercase"
                            >Asset Liquidity</span
                        >
                        <div class="space-y-6">
                            <div>
                                <span :class="labelClass">{{
                                    account.payment_method?.code ===
                                        'mpesa_agent' ||
                                    account.payment_method?.is_mpesa_agent
                                        ? 'Real-Time Cash Balance'
                                        : 'Real-Time Available Balance'
                                }}</span>
                                <div class="flex items-baseline gap-1.5">
                                    <span
                                        class="text-xs font-black text-muted-foreground/30 italic"
                                        >{{ account.currency }}</span
                                    >
                                    <span
                                        class="text-2xl leading-none font-black tracking-tighter text-foreground tabular-nums"
                                        >{{
                                            formatCurrency(account.balance)
                                        }}</span
                                    >
                                </div>
                            </div>
                            <div
                                v-if="
                                    account.payment_method?.code ===
                                        'mpesa_agent' ||
                                    account.payment_method?.is_mpesa_agent
                                "
                                class="border-t border-border/50 pt-4"
                            >
                                <span :class="labelClass"
                                    >Real-Time M-Pesa Float Balance</span
                                >
                                <div class="flex items-baseline gap-1.5">
                                    <span
                                        class="text-xs font-black text-muted-foreground/30 italic"
                                        >{{ account.currency }}</span
                                    >
                                    <span
                                        class="text-2xl leading-none font-black tracking-tighter text-emerald-600 tabular-nums"
                                        >{{
                                            formatCurrency(
                                                account.float_balance || 0,
                                            )
                                        }}</span
                                    >
                                </div>
                            </div>
                            <div class="border-t border-border/50 pt-4">
                                <span :class="labelClass"
                                    >Primary Banking Partner</span
                                >
                                <div class="mt-1 flex items-center gap-3">
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-border bg-accent/5"
                                    >
                                        <Landmark
                                            class="h-4 w-4 text-muted-foreground"
                                        />
                                    </div>
                                    <p
                                        class="text-[10px] leading-tight font-black tracking-tight uppercase"
                                    >
                                        {{
                                            account.bank?.name ||
                                            account.bank_name ||
                                            'Cash Reserve'
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-8">
                        <p
                            class="text-[9px] leading-relaxed font-bold text-muted-foreground uppercase"
                        >
                            Balance is updated dynamically as transactions are
                            cleared through the ERP's financial integration
                            modules.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Denomination Movement Flow History Modal -->
        <DenominationHistoryModal
            v-model:open="isHistoryModalOpen"
            :payment-account="account"
            :denomination="selectedDenomination"
        />
    </div>
</template>
