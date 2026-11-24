<script setup lang="ts">
import { Building2, Landmark, MapPin, Wallet } from 'lucide-vue-next';

const props = defineProps<{
    account: any;
    pendingAmount?: number | string;
}>();

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
    <div class="grid shrink-0 grid-cols-12 gap-4">
        <!-- Financial Context -->
        <div
            class="col-span-12 flex items-start gap-3 rounded-lg border border-border bg-card/40 p-3 text-card-foreground shadow-sm backdrop-blur-xl lg:col-span-3"
        >
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-emerald-500/20 bg-emerald-500/10 text-emerald-600"
            >
                <Wallet class="h-5 w-5" />
            </div>
            <div class="min-w-0 flex-1">
                <span :class="labelClass">Financial Context</span>
                <p
                    class="truncate text-xs font-black tracking-tight text-foreground uppercase"
                >
                    Current {{ account.currency }} Balance
                </p>
                <div class="mt-0.5 flex flex-col gap-1">
                    <div class="flex items-center gap-1.5">
                        <span
                            class="text-[11px] font-black tracking-tight text-emerald-600 uppercase tabular-nums"
                        >
                            {{ formatCurrency(account.balance) }}
                        </span>
                        <span
                            class="text-[8px] leading-none font-bold text-muted-foreground uppercase opacity-60"
                            >Actual</span
                        >
                    </div>
                    <div
                        v-if="Number(pendingAmount) > 0"
                        class="flex items-center gap-1.5 leading-none"
                    >
                        <span
                            class="text-[10px] font-black tracking-tight text-amber-500 uppercase tabular-nums"
                        >
                            + {{ formatCurrency(pendingAmount) }}
                        </span>
                        <span
                            class="text-[7px] leading-none font-black tracking-widest text-amber-600/70 uppercase"
                            >Pending Inflow</span
                        >
                    </div>
                    <div
                        v-if="Number(account.pending_cheque_balance) > 0"
                        class="mt-0.5 flex items-center gap-1.5 leading-none"
                    >
                        <span
                            class="text-[10px] font-black tracking-tight text-orange-600 uppercase tabular-nums"
                        >
                            -
                            {{ formatCurrency(account.pending_cheque_balance) }}
                        </span>
                        <span
                            class="text-[7px] leading-none font-black tracking-widest text-orange-700/70 uppercase"
                            >Committed</span
                        >
                    </div>
                </div>
            </div>
        </div>

        <!-- Banking Institution -->
        <div
            class="col-span-12 flex items-start gap-3 rounded-lg border border-border bg-card/40 p-3 text-card-foreground shadow-sm backdrop-blur-xl lg:col-span-3"
        >
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-blue-500/20 bg-blue-500/10 text-blue-600"
            >
                <Landmark class="h-5 w-5" />
            </div>
            <div class="min-w-0 flex-1">
                <span :class="labelClass">Banking Entity</span>
                <p
                    class="truncate text-xs font-black tracking-tight text-foreground uppercase"
                >
                    {{ account.bank?.name || account.bank_name || 'N/A' }}
                </p>
                <p
                    class="mt-0.5 text-[9px] font-bold text-muted-foreground uppercase opacity-60"
                >
                    Account: {{ account.account_number || 'N/A' }}
                </p>
            </div>
        </div>

        <!-- Ownership Entity -->
        <div
            class="col-span-12 flex items-start gap-3 rounded-lg border border-border bg-card/40 p-3 text-card-foreground shadow-sm backdrop-blur-xl lg:col-span-3"
        >
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-purple-500/20 bg-purple-500/10 text-purple-600"
            >
                <Building2 class="h-5 w-5" />
            </div>
            <div class="min-w-0 flex-1">
                <span :class="labelClass">Account Owner</span>
                <p
                    class="truncate text-xs font-black tracking-tight text-foreground uppercase"
                >
                    {{ account.company?.name }}
                </p>
                <p
                    class="mt-0.5 truncate text-[9px] font-bold text-muted-foreground uppercase opacity-60"
                >
                    Tax ID: {{ account.company?.kra_pin || 'N/A' }}
                </p>
            </div>
        </div>

        <!-- Operational Branch -->
        <div
            class="col-span-12 flex items-start gap-3 rounded-lg border border-border bg-card/40 p-3 text-card-foreground shadow-sm backdrop-blur-xl lg:col-span-3"
        >
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-orange-500/20 bg-orange-500/10 text-orange-600"
            >
                <MapPin class="h-5 w-5" />
            </div>
            <div class="min-w-0 flex-1">
                <span :class="labelClass">Assigned Location</span>
                <p
                    class="truncate text-xs font-black tracking-tight text-foreground uppercase"
                >
                    {{ account.branch?.name }}
                </p>
                <p
                    class="mt-0.5 truncate text-[9px] font-bold text-muted-foreground uppercase opacity-60"
                >
                    {{ account.branch?.physical_address || 'Default Branch' }}
                </p>
            </div>
        </div>
    </div>
</template>
