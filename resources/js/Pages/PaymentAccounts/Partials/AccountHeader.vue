<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
import {
    Building2,
    ChevronLeft,
    Database,
    DownloadCloud,
    Hash,
    Landmark,
    Pencil,
    Smartphone,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    account: any;
}>();

const emit = defineEmits<{
    (e: 'b2b-transfer'): void;
    (e: 'edit'): void;
}>();

const formatCurrency = (amount: number | string) => {
    return Number(amount || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

const isPaybillAccount = computed(() => {
    if (!props.account) return false;
    const methodCode = (
        props.account.payment_method?.code ||
        props.account.paymentMethod?.code ||
        ''
    ).toLowerCase();
    const methodName = (
        props.account.payment_method?.name ||
        props.account.paymentMethod?.name ||
        ''
    ).toLowerCase();
    return (
        methodCode === 'paybill' ||
        methodName.includes('paybill') ||
        Boolean(props.account.linked_bank_account_id) ||
        Boolean(props.account.mpesa_config || props.account.mpesaConfig)
    );
});

const linkedBank = computed(() => {
    return (
        props.account.linked_bank_account ||
        props.account.linkedBankAccount ||
        null
    );
});
</script>

<template>
    <div
        class="flex shrink-0 items-center justify-between rounded-lg border border-border/50 bg-card/20 px-3 py-1.5"
    >
        <div class="flex items-center gap-2.5 min-w-0">
            <Link
                href="/organization/financial-profiles/payment-accounts"
                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-border bg-background text-muted-foreground shadow-xs transition-all hover:border-emerald-500/50 hover:text-emerald-600"
            >
                <ChevronLeft class="h-3.5 w-3.5" />
            </Link>
            <div class="flex flex-col min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <div
                        v-if="account.account"
                        class="flex items-center gap-1 rounded bg-emerald-500 px-1.5 py-0.2 text-[9px] font-black text-white uppercase shadow-xs shadow-emerald-500/20"
                    >
                        <Hash class="h-2.5 w-2.5" />
                        {{ account.account.code }}
                    </div>
                    <h1
                        class="text-sm sm:text-base font-black tracking-tight text-foreground uppercase truncate"
                    >
                        {{ account.name }}
                    </h1>
                    <span
                        :class="[
                            'rounded border px-1.5 py-0.2 text-[8px] font-black tracking-wider uppercase shadow-xs',
                            account.is_active
                                ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 shadow-emerald-500/5'
                                : 'border-rose-500/20 bg-rose-500/10 text-rose-600 shadow-rose-500/5',
                        ]"
                    >
                        {{ account.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                    <div
                        v-if="account.account?.parent"
                        class="flex items-center gap-1 rounded border border-amber-500/20 bg-amber-500/10 px-1.5 py-0.2 text-[8.5px] font-bold text-amber-600 uppercase shadow-xs"
                    >
                        <Database class="h-2.5 w-2.5" />
                        {{ account.account.parent.name }}
                    </div>
                    <div
                        class="flex items-center gap-1 rounded border border-border/50 bg-accent/5 px-1.5 py-0.2 text-[8.5px] font-bold text-muted-foreground uppercase"
                    >
                        <Wallet class="h-2.5 w-2.5 text-emerald-500" />
                        {{ account.payment_method?.name || 'Manual Account' }}
                    </div>
                    <div
                        v-if="account.bank"
                        class="flex items-center gap-1 rounded border border-blue-500/20 bg-blue-500/5 px-1.5 py-0.2 text-[8.5px] font-bold text-blue-600 uppercase shadow-xs shadow-blue-500/5"
                    >
                        <Landmark class="h-2.5 w-2.5" />
                        <span>{{ account.bank.name }}</span>
                        <span
                            v-if="account.bank.paybill_number"
                            class="ml-1 rounded bg-indigo-500/10 px-1 font-mono text-[8px] font-black text-indigo-600"
                        >
                            PAYBILL: {{ account.bank.paybill_number }}
                        </span>
                    </div>
                    <div
                        v-if="linkedBank"
                        class="flex items-center gap-1 rounded border border-indigo-500/20 bg-indigo-500/5 px-1.5 py-0.2 text-[8.5px] font-bold text-indigo-600 uppercase shadow-xs shadow-indigo-500/5"
                    >
                        <Landmark class="h-2.5 w-2.5" />
                        <span
                            >Settlement:
                            {{
                                linkedBank.bank?.name || linkedBank.name
                            }}</span
                        >
                        <span
                            v-if="linkedBank.bank?.paybill_number"
                            class="ml-1 rounded bg-indigo-500/10 px-1 font-mono text-[8px] font-black text-indigo-600"
                        >
                            PAYBILL: {{ linkedBank.bank.paybill_number }}
                        </span>
                    </div>
                    <div
                        v-if="account.nominated_number || account.mpesa_config?.nominated_number || account.mpesaConfig?.nominated_number"
                        class="flex items-center gap-1 rounded border border-emerald-500/20 bg-emerald-500/5 px-1.5 py-0.2 text-[8.5px] font-bold text-emerald-600 uppercase shadow-xs shadow-emerald-500/5"
                    >
                        <Smartphone class="h-2.5 w-2.5" />
                        <span>Nominated: {{ account.nominated_number || account.mpesa_config?.nominated_number || account.mpesaConfig?.nominated_number }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-3">
            <div class="mr-1 flex flex-col items-end">
                <span
                    class="text-[8px] leading-none font-black tracking-wider text-muted-foreground uppercase"
                    >Running Balance</span
                >
                <div class="mt-0.5 flex items-baseline gap-1">
                    <span
                        class="text-sm sm:text-base font-black tracking-tight text-foreground tabular-nums"
                    >
                        <span
                            class="mr-0.5 text-[9px] font-black text-muted-foreground/60 uppercase"
                            >{{ account.currency }}</span
                        >
                        {{ formatCurrency(account.balance) }}
                    </span>
                </div>
            </div>

            <div class="flex items-center gap-1 sm:gap-1.5">
                <!-- Edit Account Button -->
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="emit('edit')"
                    class="h-6 gap-1 rounded border-border bg-background px-2 text-[8.5px] font-black tracking-wider text-foreground uppercase shadow-xs transition-all hover:bg-accent hover:text-accent-foreground"
                >
                    <Pencil class="h-2.5 w-2.5 text-muted-foreground" />
                    <span>Edit</span>
                </Button>

                <!-- Pull Transactions Button for Paybill Accounts -->
                <Link
                    v-if="isPaybillAccount"
                    :href="`/organization/financial-profiles/payment-accounts/${account.id}/pull-transactions`"
                    class="flex h-6 items-center gap-1 rounded border border-indigo-500/30 bg-indigo-500/10 px-2 text-[8.5px] font-black tracking-wider text-indigo-600 uppercase shadow-xs shadow-indigo-500/10 transition-all hover:bg-indigo-500/20 dark:text-indigo-400 whitespace-nowrap"
                >
                    <DownloadCloud class="h-2.5 w-2.5" />
                    <span>Pull Transactions (API)</span>
                </Link>

                <!-- B2B Transfer Button for Paybill Accounts -->
                <Button
                    v-if="isPaybillAccount"
                    type="button"
                    size="sm"
                    @click="emit('b2b-transfer')"
                    class="h-6 gap-1 rounded bg-emerald-600 px-2 text-[8.5px] font-black tracking-wider text-white uppercase shadow-xs shadow-emerald-500/20 transition-all hover:bg-emerald-700 whitespace-nowrap"
                >
                    <Building2 class="h-2.5 w-2.5" />
                    <span>Transfer to Bank (B2B)</span>
                </Button>
            </div>
        </div>
    </div>
</template>
