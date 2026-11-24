<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import 'flatpickr/dist/flatpickr.css';
import {
    Activity,
    Calendar,
    CreditCard,
    Target,
    TrendingDown,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import FlatPickr from 'vue-flatpickr-component';

const props = defineProps<{
    account: any;
    futureLiabilities: any[];
    filters: {
        cheque_direction?: string;
        settlement_date?: string;
    };
    formatCurrency: (amount: string | number, currency: string) => string;
}>();

const handleDirectionFilter = (dir: string) => {
    router.get(
        window.location.pathname,
        {
            tab: 'cheques',
            cheque_direction: dir,
            settlement_date: settlementDate.value,
        },
        { preserveState: true, preserveScroll: true },
    );
};

const getLatestChequeDate = (liabilities: any[]) => {
    if (liabilities && liabilities.length > 0) {
        return liabilities[liabilities.length - 1].cheque_date;
    }
    return new Date().toISOString().split('T')[0];
};

const settlementDate = ref(
    props.filters.settlement_date || getLatestChequeDate(props.futureLiabilities),
);

watch(
    () => props.futureLiabilities,
    (newVal) => {
        if (!settlementDate.value && !props.filters.settlement_date) {
            settlementDate.value = getLatestChequeDate(newVal);
        }
    },
);

const handleSettlementDateChange = (selectedDates: any, dateStr: string) => {
    settlementDate.value = dateStr;
    router.get(
        window.location.pathname,
        {
            tab: 'cheques',
            cheque_direction: props.filters.cheque_direction || '',
            settlement_date: dateStr,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const clearSettlementDate = () => {
    settlementDate.value = '';
    router.get(
        window.location.pathname,
        {
            tab: 'cheques',
            cheque_direction: props.filters.cheque_direction || '',
            settlement_date: '',
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const projectedRequirement = computed(() => {
    if (!settlementDate.value) return 0;
    const target = new Date(settlementDate.value);
    target.setHours(23, 59, 59, 999);

    return (props.futureLiabilities || []).reduce((sum, item) => {
        const itemDate = new Date(item.cheque_date);
        if (itemDate <= target) {
            return sum + (parseFloat(item.total_amount) || 0);
        }
        return sum;
    }, 0);
});

const netPosition = computed(() => {
    return (
        (parseFloat(props.account.balance) || 0) - projectedRequirement.value
    );
});

const flatpickrConfig = {
    altInput: true,
    altFormat: 'F j, Y',
    dateFormat: 'Y-m-d',
};
</script>

<template>
    <div class="flex shrink-0 flex-col border-b border-border/50 bg-accent/5">
        <!-- Top row: Title and Filters -->
        <div class="flex items-center justify-between p-6">
            <div>
                <h3
                    class="flex items-center gap-2 text-sm font-black tracking-widest uppercase"
                >
                    <CreditCard class="h-4 w-4 text-orange-500" />
                    Cheques Management
                </h3>
                <p
                    class="mt-1 text-[10px] font-bold text-muted-foreground uppercase"
                >
                    Manage uncleared payments and track future liabilities
                </p>
            </div>

            <div class="flex items-center gap-4">
                <!-- Current Balance (Context) -->
                <div
                    class="flex min-w-[120px] flex-col items-end rounded-lg border border-border bg-background px-3 py-1.5 shadow-sm"
                >
                    <span
                        class="text-[9px] font-black tracking-tighter text-muted-foreground uppercase opacity-60"
                        >Account Balance</span
                    >
                    <span
                        class="text-xs font-black tracking-tighter text-blue-600 tabular-nums"
                    >
                        {{ formatCurrency(account.balance, account.currency) }}
                    </span>
                </div>

                <div
                    class="flex gap-1 rounded-lg border border-border bg-background p-1 shadow-sm"
                >
                    <button
                        @click="handleDirectionFilter('')"
                        :class="
                            !filters.cheque_direction
                                ? 'bg-primary text-primary-foreground shadow-sm'
                                : 'text-muted-foreground hover:bg-accent'
                        "
                        class="rounded-md px-3 py-1 text-[9px] font-black tracking-widest uppercase transition-all"
                    >
                        All
                    </button>
                    <button
                        @click="handleDirectionFilter('Outward')"
                        :class="
                            filters.cheque_direction === 'Outward'
                                ? 'bg-primary text-primary-foreground shadow-sm'
                                : 'text-muted-foreground hover:bg-accent'
                        "
                        class="rounded-md px-3 py-1 text-[9px] font-black tracking-widest uppercase transition-all"
                    >
                        Issued
                    </button>
                    <button
                        @click="handleDirectionFilter('Inward')"
                        :class="
                            filters.cheque_direction === 'Inward'
                                ? 'bg-primary text-primary-foreground shadow-sm'
                                : 'text-muted-foreground hover:bg-accent'
                        "
                        class="rounded-md px-3 py-1 text-[9px] font-black tracking-widest uppercase transition-all"
                    >
                        Received
                    </button>
                </div>

                <div
                    class="flex min-w-[120px] flex-col items-end rounded-lg border border-border bg-background px-3 py-1.5 shadow-sm"
                >
                    <span
                        class="text-[9px] font-black tracking-tighter text-muted-foreground uppercase opacity-60"
                        >Uncleared (Total)</span
                    >
                    <span
                        class="text-xs font-black tracking-tighter text-orange-600 tabular-nums"
                    >
                        {{
                            formatCurrency(
                                account.pending_cheque_balance,
                                account.currency,
                            )
                        }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Bottom row: Future Liability Analysis (Only for non-Inward cheques) -->
        <div
            v-if="
                futureLiabilities?.length &&
                filters.cheque_direction !== 'Inward'
            "
            class="flex items-center justify-between gap-6 px-6 pt-0 pb-6"
        >
            <div class="flex shrink-0 flex-col gap-2">
                <div class="flex items-center gap-2">
                    <TrendingDown class="h-3.5 w-3.5 text-rose-500" />
                    <span
                        class="text-[9px] font-black tracking-[0.2em] text-rose-600/70 uppercase"
                        >Projected Settlement Requirement</span
                    >
                </div>

                <div class="flex items-center gap-3">
                    <div class="group relative flex items-center">
                        <Calendar
                            class="pointer-events-none absolute top-1/2 left-2.5 z-10 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
                        />
                        <FlatPickr
                            v-model="settlementDate"
                            :config="flatpickrConfig"
                            @on-change="handleSettlementDateChange"
                            class="h-9 w-[180px] cursor-pointer rounded-lg border border-border bg-white pr-7 pl-9 text-[10px] font-black tracking-wider uppercase transition-all outline-none focus:ring-1 focus:ring-primary/20"
                            placeholder="SELECT DATE..."
                        />
                        <button
                            v-if="settlementDate"
                            @click="clearSettlementDate"
                            class="absolute right-2 z-20 cursor-pointer rounded-full p-0.5 text-muted-foreground/60 transition-all hover:bg-muted-foreground/10 hover:text-foreground"
                            title="Clear Settlement Date"
                        >
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <div
                        class="flex h-9 items-center gap-3 rounded-lg border border-rose-100 bg-rose-50 px-4"
                    >
                        <Target class="h-3.5 w-3.5 text-rose-400" />
                        <span
                            class="text-[9px] font-black text-rose-600/60 uppercase"
                            >Cumulative Due</span
                        >
                        <span
                            class="text-xs font-black text-rose-600 tabular-nums"
                        >
                            {{
                                formatCurrency(
                                    projectedRequirement,
                                    account.currency,
                                )
                            }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Net Position Gap -->
            <div
                class="flex min-w-[300px] items-center gap-4 rounded-xl border border-border bg-background p-3 shadow-sm"
            >
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                    :class="
                        netPosition >= 0
                            ? 'bg-emerald-50 text-emerald-600'
                            : 'bg-rose-50 text-rose-600'
                    "
                >
                    <TrendingDown v-if="netPosition < 0" class="h-5 w-5" />
                    <Activity v-else class="h-5 w-5" />
                </div>
                <div class="flex flex-1 flex-col">
                    <div class="flex items-center justify-between">
                        <span
                            class="text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                            >Net Liquidity Position</span
                        >
                        <span
                            class="rounded px-1.5 py-0.5 text-[8px] font-black uppercase"
                            :class="
                                netPosition >= 0
                                    ? 'bg-emerald-100 text-emerald-700'
                                    : 'bg-rose-100 text-rose-700'
                            "
                        >
                            {{ netPosition >= 0 ? 'Surplus' : 'Deficit' }}
                        </span>
                    </div>
                    <div
                        class="mt-0.5 text-sm font-black tracking-tight tabular-nums"
                        :class="
                            netPosition >= 0
                                ? 'text-emerald-600'
                                : 'text-rose-600'
                        "
                    >
                        {{
                            formatCurrency(
                                Math.abs(netPosition),
                                account.currency,
                            )
                        }}
                        <span class="ml-1 text-[9px] font-bold opacity-60">{{
                            netPosition >= 0 ? 'Available' : 'Required'
                        }}</span>
                    </div>
                </div>
            </div>

            <div
                class="flex hidden flex-1 flex-col gap-1.5 border-l border-border/50 pl-6 xl:flex"
            >
                <span
                    class="text-[8px] font-bold tracking-widest text-muted-foreground uppercase"
                    >Financial Context</span
                >
                <p
                    class="max-w-sm text-[9px] leading-relaxed text-muted-foreground italic"
                >
                    By
                    <span class="font-black text-foreground">{{
                        settlementDate
                            ? new Date(settlementDate).toLocaleDateString(
                                  'en-GB',
                                  {
                                      day: '2-digit',
                                      month: 'short',
                                  },
                              )
                            : 'selected date'
                    }}</span
                    >, the account will have a
                    <span
                        :class="
                            netPosition >= 0
                                ? 'text-emerald-600'
                                : 'text-rose-600'
                        "
                        class="font-black"
                    >
                        {{ netPosition >= 0 ? 'surplus' : 'deficit' }}
                    </span>
                    of
                    {{
                        formatCurrency(Math.abs(netPosition), account.currency)
                    }}
                    relative to pending settlement obligations.
                </p>
            </div>
        </div>
    </div>
</template>
