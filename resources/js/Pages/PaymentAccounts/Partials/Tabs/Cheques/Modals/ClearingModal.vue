<script setup lang="ts">
import { AlertCircle, CheckCircle, Loader2, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    cheque: any;
    account: any;
    processing: boolean;
    formatCurrency: (amount: string | number, currency: string) => string;
}>();

const emit = defineEmits(['close', 'confirm']);
const transactionNumber = ref('');

watch(
    () => props.cheque,
    () => {
        transactionNumber.value = '';
    },
);

const handleClose = () => {
    transactionNumber.value = '';
    emit('close');
};

const hasSufficientBalance = computed(() => {
    if (!props.cheque || props.cheque.direction !== 'Outward') return true;
    return parseFloat(props.account.balance) >= parseFloat(props.cheque.amount);
});

const handleConfirm = () => {
    if (!hasSufficientBalance.value) return;
    emit('confirm', transactionNumber.value);
};
</script>

<template>
    <div
        v-if="cheque"
        class="fixed inset-0 z-[100] flex animate-in items-center justify-center bg-black/60 p-4 duration-200 fade-in"
    >
        <div
            class="relative w-full max-w-md animate-in rounded-2xl border border-border bg-card/95 p-6 shadow-2xl backdrop-blur-2xl duration-200 zoom-in-95"
            @click.stop
        >
            <!-- X Close Button -->
            <button
                @click="handleClose"
                class="absolute top-4 right-4 rounded-lg p-2 text-muted-foreground transition-all hover:bg-accent/10 hover:text-foreground"
                title="Close"
            >
                <X class="h-4 w-4" />
            </button>
            <div class="mb-4 flex items-center gap-4">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-600"
                    :class="
                        !hasSufficientBalance
                            ? 'border-rose-500/20 bg-rose-500/10 text-rose-600'
                            : ''
                    "
                >
                    <CheckCircle class="h-6 w-6" />
                </div>
                <div>
                    <h3
                        class="text-sm font-black tracking-widest text-foreground uppercase"
                    >
                        Clear Cheque Funds
                    </h3>
                    <p
                        class="mt-0.5 text-[10px] font-bold tracking-tight text-muted-foreground uppercase"
                    >
                        Payment Authorization Required
                    </p>
                </div>
            </div>

            <!-- Insufficient Balance Alert -->
            <div
                v-if="!hasSufficientBalance"
                class="mb-4 flex items-start gap-3 rounded-xl border border-rose-500/20 bg-rose-500/5 p-4"
            >
                <AlertCircle class="mt-0.5 h-4 w-4 shrink-0 text-rose-600" />
                <div class="flex-1">
                    <h4
                        class="text-[10px] font-black tracking-wider text-rose-600 uppercase"
                    >
                        Insufficient Funds
                    </h4>
                    <p
                        class="mt-1 text-[9px] leading-relaxed font-bold text-rose-600/80 uppercase"
                    >
                        The account balance ({{
                            formatCurrency(account.balance, account.currency)
                        }}) is less than the cheque amount ({{
                            formatCurrency(cheque.amount, account.currency)
                        }}). You cannot clear this outward cheque.
                    </p>
                </div>
            </div>

            <div
                class="mb-6 space-y-3 rounded-xl border border-border/50 bg-accent/5 p-4"
            >
                <div
                    class="flex items-center justify-between text-[10px] font-bold uppercase"
                >
                    <span class="text-muted-foreground">Cheque Number</span>
                    <span class="font-black text-foreground"
                        >#{{ cheque.cheque_number }}</span
                    >
                </div>
                <div
                    class="flex items-center justify-between text-[10px] font-bold uppercase"
                >
                    <span class="text-muted-foreground">Cheque Direction</span>
                    <span
                        class="rounded px-2 py-0.5 text-[8px] font-black"
                        :class="
                            cheque.direction === 'Outward'
                                ? 'bg-rose-500/10 text-rose-600'
                                : 'bg-emerald-500/10 text-emerald-600'
                        "
                    >
                        {{ cheque.direction }}
                    </span>
                </div>
                <div
                    class="flex items-center justify-between text-[10px] font-bold uppercase"
                >
                    <span class="text-muted-foreground"
                        >Available Account Balance</span
                    >
                    <span
                        class="font-black text-foreground"
                        :class="
                            parseFloat(account.balance) < 0
                                ? 'text-rose-500'
                                : 'text-emerald-500'
                        "
                    >
                        {{ formatCurrency(account.balance, account.currency) }}
                    </span>
                </div>
                <div
                    class="flex items-center justify-between border-t border-dashed border-border/60 pt-3 text-[10px] font-bold uppercase"
                >
                    <span class="text-muted-foreground"
                        >Amount to
                        {{
                            cheque.direction === 'Outward' ? 'Debit' : 'Credit'
                        }}</span
                    >
                    <span
                        class="font-black"
                        :class="
                            cheque.direction === 'Outward'
                                ? 'text-rose-600'
                                : 'text-emerald-600'
                        "
                    >
                        {{ formatCurrency(cheque.amount, account.currency) }}
                    </span>
                </div>
            </div>

            <div
                v-if="account.bank_id || account.bank_name"
                class="mb-6 space-y-1.5"
            >
                <label
                    class="block px-1 text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                    >Bank Transaction Number</label
                >
                <input
                    v-model="transactionNumber"
                    type="text"
                    placeholder="Enter reference number (e.g. TRN12345)"
                    class="w-full rounded-xl border border-border bg-accent/5 px-3 py-2 text-xs font-bold transition-all outline-none focus:border-emerald-500 focus:ring-emerald-500/20"
                    :disabled="!hasSufficientBalance"
                />
            </div>

            <p
                class="mb-6 text-[10px] leading-relaxed font-medium tracking-tight text-muted-foreground uppercase"
            >
                By proceeding, you confirm that this cheque has cleared the
                bank. This will debit the actual balance and finalize the
                accounting entry.
            </p>

            <div class="flex justify-end gap-3">
                <button
                    @click="handleClose"
                    class="px-4 py-2 text-[9px] font-black tracking-widest text-muted-foreground uppercase transition-all hover:text-foreground"
                >
                    Cancel
                </button>
                <button
                    @click="handleConfirm"
                    :disabled="processing || !hasSufficientBalance"
                    class="flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-[9px] font-black tracking-[0.1em] text-white uppercase shadow-lg shadow-emerald-500/20 transition-all hover:bg-emerald-500 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <Loader2
                        v-if="processing"
                        class="h-3.5 w-3.5 animate-spin"
                    />
                    <CheckCircle v-else class="h-3.5 w-3.5" />
                    <span>{{
                        processing ? 'Clearing...' : 'Confirm Clearing'
                    }}</span>
                </button>
            </div>
        </div>
    </div>
</template>
