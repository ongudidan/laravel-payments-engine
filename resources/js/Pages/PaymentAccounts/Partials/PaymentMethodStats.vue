<script setup lang="ts">
import {
    Banknote,
    CreditCard,
    Landmark,
    QrCode,
    Smartphone,
    Store,
    Wallet,
} from 'lucide-vue-next';

interface MethodSummary {
    id: number;
    name: string;
    code: string;
    total_balance: number;
    total_pending_cheque: number;
    total_pending_in: number;
    total_accounts: number;
    active_accounts: number;
}

interface OverallSummary {
    total_balance: number;
    total_pending_cheque: number;
    total_pending_in: number;
    total_accounts: number;
    active_accounts: number;
}

const props = defineProps<{
    summaries: {
        overall: OverallSummary;
        methods: MethodSummary[];
    };
    selectedMethodId?: string | number | null;
}>();

const emit = defineEmits<{
    (e: 'select-method', methodId: string | number): void;
}>();

const formatCurrency = (amount: number | string) => {
    return Number(amount || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

const getMethodConfig = (code?: string, name?: string) => {
    const c = (code || '').toLowerCase();
    const n = (name || '').toLowerCase();

    if (c === 'cash' || n.includes('cash')) {
        return {
            icon: Banknote,
            borderClass: 'border-emerald-500/20',
            bgClass: 'bg-emerald-500/10',
            textClass: 'text-emerald-600 dark:text-emerald-400',
            amountColor: 'text-emerald-600 dark:text-emerald-400',
        };
    }
    if (c === 'bank' || n.includes('bank')) {
        return {
            icon: Landmark,
            borderClass: 'border-blue-500/20',
            bgClass: 'bg-blue-500/10',
            textClass: 'text-blue-600 dark:text-blue-400',
            amountColor: 'text-blue-600 dark:text-blue-400',
        };
    }
    if (c === 'paybill' || n.includes('paybill')) {
        return {
            icon: QrCode,
            borderClass: 'border-purple-500/20',
            bgClass: 'bg-purple-500/10',
            textClass: 'text-purple-600 dark:text-purple-400',
            amountColor: 'text-purple-600 dark:text-purple-400',
        };
    }
    if (c === 'mobile_money' || n.includes('mobile')) {
        return {
            icon: Smartphone,
            borderClass: 'border-amber-500/20',
            bgClass: 'bg-amber-500/10',
            textClass: 'text-amber-600 dark:text-amber-400',
            amountColor: 'text-amber-600 dark:text-amber-400',
        };
    }
    if (c === 'mpesa_agent' || n.includes('agent')) {
        return {
            icon: Store,
            borderClass: 'border-teal-500/20',
            bgClass: 'bg-teal-500/10',
            textClass: 'text-teal-600 dark:text-teal-400',
            amountColor: 'text-teal-600 dark:text-teal-400',
        };
    }
    if (c === 'cheque' || n.includes('cheque')) {
        return {
            icon: CreditCard,
            borderClass: 'border-indigo-500/20',
            bgClass: 'bg-indigo-500/10',
            textClass: 'text-indigo-600 dark:text-indigo-400',
            amountColor: 'text-indigo-600 dark:text-indigo-400',
        };
    }

    return {
        icon: Wallet,
        borderClass: 'border-slate-500/20',
        bgClass: 'bg-slate-500/10',
        textClass: 'text-slate-600 dark:text-slate-400',
        amountColor: 'text-foreground',
    };
};
</script>

<template>
    <div class="grid shrink-0 grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6">
        <!-- Overall System Liquidity Card -->
        <div
            @click="emit('select-method', '')"
            class="group flex cursor-pointer items-center gap-3 rounded-lg border bg-card/40 p-2.5 text-card-foreground shadow-sm backdrop-blur-xl transition-all duration-200 hover:border-emerald-500/40 hover:bg-card/70 hover:shadow-md active:scale-[0.99]"
            :class="[
                !selectedMethodId
                    ? 'border-emerald-500/50 ring-2 ring-emerald-500/20 bg-emerald-500/[0.04]'
                    : 'border-border',
            ]"
            title="Click to view all payment accounts"
        >
            <div
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 transition-transform duration-200 group-hover:scale-105"
            >
                <Wallet class="h-4 w-4" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-[11px] font-black tracking-tight text-foreground uppercase">
                    Total Liquidity
                </p>
                <p class="text-xs font-black tracking-tight text-emerald-600 dark:text-emerald-400 uppercase tabular-nums">
                    {{ formatCurrency(summaries.overall.total_balance) }}
                </p>
            </div>
        </div>

        <!-- Individual Payment Method Cards -->
        <div
            v-for="method in summaries.methods"
            :key="method.id"
            @click="emit('select-method', method.id)"
            class="group flex cursor-pointer items-center gap-3 rounded-lg border bg-card/40 p-2.5 text-card-foreground shadow-sm backdrop-blur-xl transition-all duration-200 hover:border-primary/40 hover:bg-card/70 hover:shadow-md active:scale-[0.99]"
            :class="[
                String(selectedMethodId) === String(method.id)
                    ? 'border-primary ring-2 ring-primary/20 bg-primary/[0.04]'
                    : 'border-border',
            ]"
            :title="`Click to filter accounts by ${method.name}`"
        >
            <div
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border transition-transform duration-200 group-hover:scale-105"
                :class="[
                    getMethodConfig(method.code, method.name).borderClass,
                    getMethodConfig(method.code, method.name).bgClass,
                    getMethodConfig(method.code, method.name).textClass,
                ]"
            >
                <component
                    :is="getMethodConfig(method.code, method.name).icon"
                    class="h-4 w-4"
                />
            </div>

            <div class="min-w-0 flex-1">
                <p class="truncate text-[11px] font-black tracking-tight text-foreground uppercase">
                    {{ method.name }}
                </p>
                <p
                    class="text-xs font-black tracking-tight uppercase tabular-nums"
                    :class="getMethodConfig(method.code, method.name).amountColor"
                >
                    {{ formatCurrency(method.total_balance) }}
                </p>
            </div>
        </div>
    </div>
</template>
