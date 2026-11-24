<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatInput } from '@/lib/utils';
import { router } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlertTriangle,
    ArrowRight,
    Building2,
    Check,
    CheckCircle2,
    Copy,
    Landmark,
    Loader2,
    RotateCcw,
    Send,
    ShieldCheck,
    Smartphone,
    XCircle,
} from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    account: any;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

// Transfer Lifecycle States: 'form' | 'confirm' | 'waiting' | 'success' | 'failed' | 'timeout'
const transferState = ref<'form' | 'confirm' | 'waiting' | 'success' | 'failed' | 'timeout'>('form');
const countdown = ref(45);
const activeTransferId = ref<number | null>(null);
const resultData = ref<any>(null);
const errorMessage = ref<string>('');
const isSubmitting = ref(false);
const copiedReceipt = ref(false);

let pollTimer: any = null;
let countdownTimer: any = null;

// Form State
const form = ref({
    amount: '',
    remarks: '',
    errors: {} as Record<string, string>,
});

// Format Currency Utility
const formatCurrency = (amount: number | string) => {
    return Number(amount || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

// Source Account & Balance
const sourceBalance = computed(() => {
    const val = Number(props.account?.balance || 0);
    return isNaN(val) ? 0 : val;
});

const sourceShortcode = computed(() => {
    return (
        props.account?.mpesa_config?.shortcode ||
        props.account?.mpesaConfig?.shortcode ||
        props.account?.account_number ||
        'N/A'
    );
});

// Destination Bank Account
const linkedBankAcc = computed(() => {
    return (
        props.account?.linked_bank_account ||
        props.account?.linkedBankAccount ||
        null
    );
});

const destinationBank = computed(() => {
    return linkedBankAcc.value?.bank || null;
});

const destinationPaybill = computed(() => {
    return destinationBank.value?.paybill_number || null;
});

const destinationAccountNumber = computed(() => {
    return linkedBankAcc.value?.account_number || null;
});

// Configuration Readiness
const isConfigured = computed(() => {
    return Boolean(
        linkedBankAcc.value &&
            destinationPaybill.value &&
            destinationAccountNumber.value,
    );
});

// Amount Parsing & Validation
const numericAmount = computed(() => {
    if (!form.value.amount) return 0;
    const cleaned = String(form.value.amount).replace(/,/g, '').trim();
    const val = Number(cleaned);
    return isNaN(val) ? 0 : val;
});

const isExceedingBalance = computed(() => {
    return numericAmount.value > sourceBalance.value;
});

const amountError = computed(() => {
    if (!form.value.amount) return '';
    if (numericAmount.value <= 0) {
        return 'Please enter a valid transfer amount greater than 0.';
    }
    if (numericAmount.value > sourceBalance.value) {
        return `Transfer amount (${props.account?.currency || 'KES'} ${formatCurrency(numericAmount.value)}) exceeds available balance (${props.account?.currency || 'KES'} ${formatCurrency(sourceBalance.value)}).`;
    }
    return '';
});

const canProceed = computed(() => {
    if (isSubmitting.value) return false;
    if (!isConfigured.value) return false;
    if (numericAmount.value <= 0) return false;
    if (isExceedingBalance.value) return false;
    return true;
});

// Process Input for Currency Formatting
const processAmountInput = (text: string) => {
    if (!text) {
        form.value.amount = '';
        return;
    }

    let inputStr = String(text);
    const currencyMatch = inputStr.match(
        /(?:KES|KSH|KES\.|KSH\.|USD|EUR|GBP|Ksh|Kes|KSh|\$|€|£)\s*([0-9.,]+)/i,
    );
    if (currencyMatch && currencyMatch[1]) {
        inputStr = currencyMatch[1];
    }

    let cleaned = inputStr.replace(/[^0-9.]/g, '');
    const parts = cleaned.split('.');
    if (parts.length > 2) {
        cleaned = parts[0] + '.' + parts.slice(1).join('');
    }

    if (cleaned.startsWith('0') && cleaned.length > 1 && cleaned[1] !== '.') {
        cleaned = cleaned.replace(/^0+/, '');
    }

    form.value.amount = cleaned;
    delete form.value.errors.amount;
};

const formattedAmount = computed({
    get() {
        return formatInput(form.value.amount);
    },
    set(val: string) {
        processAmountInput(val);
    },
});

const handlePaste = (e: ClipboardEvent) => {
    const text = e.clipboardData?.getData('text');
    if (text) {
        e.preventDefault();
        processAmountInput(text);
    }
};

const handleKeyPress = (e: KeyboardEvent) => {
    const key = e.key;
    if (key.length > 1 || e.ctrlKey || e.metaKey) return;

    if (key === '.') {
        if (String(form.value.amount).includes('.')) {
            e.preventDefault();
        }
        return;
    }
    if (!/^[0-9]$/.test(key)) {
        e.preventDefault();
    }
};

// Preset Percentage Buttons
const setPercentage = (pct: number) => {
    if (sourceBalance.value <= 0) return;
    const calculated = (sourceBalance.value * pct) / 100;
    form.value.amount = calculated.toFixed(2);
    delete form.value.errors.amount;
};

// Polling and Timer Cleanup
const clearTimers = () => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
    if (countdownTimer) {
        clearInterval(countdownTimer);
        countdownTimer = null;
    }
};

onUnmounted(() => {
    clearTimers();
});

// Modal Lifecycle
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            resetFormState();
        } else {
            clearTimers();
        }
    },
);

const resetFormState = () => {
    clearTimers();
    form.value.amount = '';
    form.value.remarks = '';
    form.value.errors = {};
    transferState.value = 'form';
    activeTransferId.value = null;
    resultData.value = null;
    errorMessage.value = '';
    isSubmitting.value = false;
    countdown.value = 45;
};

const closeModal = () => {
    if (transferState.value === 'waiting') return; // Do not dismiss while polling
    clearTimers();
    emit('update:open', false);
    setTimeout(() => {
        resetFormState();
    }, 300);
};

const closeAndRefresh = () => {
    clearTimers();
    emit('update:open', false);
    setTimeout(() => {
        resetFormState();
        router.reload({ preserveScroll: true });
    }, 200);
};

const requestConfirmation = () => {
    form.value.errors = {};
    if (amountError.value) {
        form.value.errors.amount = amountError.value;
        return;
    }
    if (!canProceed.value) return;
    transferState.value = 'confirm';
};

// Submit Transfer & Begin Polling
const submitTransfer = async () => {
    if (isSubmitting.value) return;

    form.value.errors = {};
    isSubmitting.value = true;
    transferState.value = 'waiting';
    countdown.value = 45;

    // Get CSRF Token
    const csrfToken =
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
        (document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] ||
        '';

    try {
        const response = await fetch(
            `/organization/financial-profiles/payment-accounts/${props.account.id}/b2b-transfer`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    amount: numericAmount.value,
                    remarks: form.value.remarks,
                }),
            },
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            clearTimers();
            isSubmitting.value = false;
            transferState.value = 'failed';
            errorMessage.value = data.message || 'Failed to initiate M-Pesa B2B transfer.';
            return;
        }

        // Initiation success: capture transfer ID and begin polling status
        activeTransferId.value = data.transfer_id;

        // Start Countdown Timer (45 seconds)
        countdownTimer = setInterval(() => {
            if (countdown.value > 0) {
                countdown.value--;
            } else {
                clearTimers();
                if (transferState.value === 'waiting') {
                    transferState.value = 'timeout';
                }
            }
        }, 1000);

        // Start Status Polling every 2 seconds
        pollTimer = setInterval(async () => {
            if (!activeTransferId.value) return;

            try {
                const statusRes = await fetch(
                    `/organization/financial-profiles/payment-accounts/${props.account.id}/b2b-status/${activeTransferId.value}`,
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    },
                );

                if (statusRes.ok) {
                    const statusData = await statusRes.json();

                    if (statusData.status === 'approved') {
                        clearTimers();
                        isSubmitting.value = false;
                        transferState.value = 'success';
                        resultData.value = statusData;
                    } else if (statusData.status === 'cancelled') {
                        clearTimers();
                        isSubmitting.value = false;
                        transferState.value = 'failed';
                        resultData.value = statusData;
                        errorMessage.value =
                            statusData.result_desc ||
                            statusData.notes ||
                            'The B2B transfer request was rejected or cancelled by Safaricom.';
                    }
                }
            } catch (err) {
                console.error('Polling error', err);
            }
        }, 2000);
    } catch (err: any) {
        clearTimers();
        isSubmitting.value = false;
        transferState.value = 'failed';
        errorMessage.value = err?.message || 'Network error communicating with the server.';
    }
};

const copyReceipt = (text?: string) => {
    if (!text) return;
    navigator.clipboard.writeText(text);
    copiedReceipt.value = true;
    setTimeout(() => {
        copiedReceipt.value = false;
    }, 2000);
};
</script>

<template>
    <Dialog
        :open="open"
        @update:open="
            (val) => {
                if (!val && transferState !== 'waiting') closeModal();
            }
        "
    >
        <DialogContent
            @pointer-down-outside="(e) => e.preventDefault()"
            @interact-outside="(e) => e.preventDefault()"
            @escape-key-down="(e) => e.preventDefault()"
            class="flex max-h-[90vh] flex-col overflow-hidden rounded-xl border border-border bg-card p-0 shadow-2xl sm:max-w-lg"
        >
            <!-- Modal Header (Compact) -->
            <DialogHeader
                class="relative flex shrink-0 items-center justify-between border-b border-border/50 bg-emerald-500/5 px-4 py-2.5 pr-10"
            >
                <div class="flex items-center gap-2.5 text-left">
                    <div
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-emerald-500/20 bg-emerald-500/10 text-emerald-600 shadow-sm"
                    >
                        <Building2 class="h-3.5 w-3.5" />
                    </div>
                    <div>
                        <DialogTitle
                            class="text-xs font-black tracking-tight text-foreground uppercase"
                        >
                            M-Pesa B2B Paybill Settlement
                        </DialogTitle>
                        <DialogDescription
                            class="text-[10px] font-medium leading-none text-muted-foreground"
                        >
                            Transfer funds from Paybill to linked settlement bank.
                        </DialogDescription>
                    </div>
                </div>
                <div
                    class="rounded bg-emerald-500/10 px-1.5 py-0.5 text-[9px] font-black text-emerald-600 uppercase"
                >
                    Direct B2B
                </div>
            </DialogHeader>

            <!-- Modal Body (Compact) -->
            <div class="custom-scrollbar flex-1 overflow-y-auto p-3.5">
                <!-- 1. WAITING FOR SAFARICOM CALLBACK OVERLAY -->
                <div v-if="transferState === 'waiting'" class="space-y-4 py-6 text-center">
                    <div class="relative mx-auto flex h-16 w-16 items-center justify-center">
                        <div class="absolute inset-0 animate-ping rounded-full bg-emerald-500/20" />
                        <div class="relative flex h-14 w-14 items-center justify-center rounded-full border-2 border-emerald-500/40 bg-emerald-500/10 shadow-inner">
                            <Loader2 class="h-7 w-7 animate-spin text-emerald-600" />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <div class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-[9px] font-black tracking-widest text-emerald-600 uppercase">
                            Awaiting Callback &bull; {{ countdown }}s
                        </div>
                        <h3 class="text-sm font-black text-foreground uppercase tracking-tight">
                            Processing B2B Settlement...
                        </h3>
                        <p class="text-[11px] text-muted-foreground">
                            Safaricom Daraja is processing the transfer to <span class="font-bold text-foreground">{{ destinationBank?.name || 'Bank' }}</span>.
                        </p>
                    </div>

                    <div class="mx-auto max-w-xs rounded-lg border border-border/60 bg-muted/20 p-2.5 text-[10px] text-left space-y-1.5">
                        <div class="flex justify-between">
                            <span class="font-bold text-muted-foreground uppercase">Settlement Amount:</span>
                            <span class="font-black text-emerald-600 tabular-nums">
                                {{ account?.currency || 'KES' }} {{ formatCurrency(numericAmount) }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-bold text-muted-foreground uppercase">Destination Acc:</span>
                            <span class="font-mono font-bold">{{ destinationAccountNumber }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-bold text-muted-foreground uppercase">Target Paybill:</span>
                            <span class="font-mono font-bold text-indigo-600">{{ destinationPaybill }}</span>
                        </div>
                    </div>
                </div>

                <!-- 2. SUCCESS STATE -->
                <div v-else-if="transferState === 'success'" class="space-y-3.5 py-4 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full border border-emerald-500/30 bg-emerald-500/10 text-emerald-600 shadow-sm">
                        <CheckCircle2 class="h-7 w-7" />
                    </div>

                    <div class="space-y-0.5">
                        <h3 class="text-sm font-black text-emerald-700 dark:text-emerald-400 uppercase tracking-tight">
                            Transfer Settled Successfully!
                        </h3>
                        <p class="text-[10px] text-muted-foreground">
                            Safaricom confirmed the transaction and ledgers have been updated.
                        </p>
                    </div>

                    <!-- Receipt Details Box -->
                    <div class="mx-auto max-w-sm rounded-lg border border-emerald-500/20 bg-emerald-500/5 p-3 text-left space-y-2 text-[10px]">
                        <div class="flex items-center justify-between border-b border-emerald-500/10 pb-1.5">
                            <span class="font-bold text-slate-500 uppercase text-[9px]">M-Pesa Receipt (Tx ID)</span>
                            <div class="flex items-center gap-1">
                                <span class="font-mono font-black text-slate-900 dark:text-slate-100">
                                    {{ resultData?.mpesa_receipt || resultData?.transaction_number || 'Confirmed' }}
                                </span>
                                <button
                                    v-if="resultData?.mpesa_receipt || resultData?.transaction_number"
                                    type="button"
                                    @click="copyReceipt(resultData?.mpesa_receipt || resultData?.transaction_number)"
                                    class="text-emerald-600 hover:text-emerald-800 transition-colors p-0.5"
                                    title="Copy Receipt"
                                >
                                    <Check v-if="copiedReceipt" class="h-3 w-3 text-emerald-600" />
                                    <Copy v-else class="h-3 w-3" />
                                </button>
                            </div>
                        </div>

                        <div class="flex justify-between">
                            <span class="font-bold text-slate-500 uppercase text-[9px]">Settled Amount:</span>
                            <span class="font-black text-emerald-600 tabular-nums">
                                {{ account?.currency || 'KES' }} {{ formatCurrency(resultData?.amount || numericAmount) }}
                            </span>
                        </div>

                        <div class="flex justify-between">
                            <span class="font-bold text-slate-500 uppercase text-[9px]">Destination Bank:</span>
                            <span class="font-black text-slate-800 dark:text-slate-200 uppercase">
                                {{ destinationBank?.name }} (Acc: {{ destinationAccountNumber }})
                            </span>
                        </div>

                        <div v-if="resultData?.result_desc" class="pt-0.5 border-t border-emerald-500/10 text-[9px] text-slate-600 dark:text-slate-400 italic">
                            {{ resultData.result_desc }}
                        </div>
                    </div>
                </div>

                <!-- 3. FAILED STATE -->
                <div v-else-if="transferState === 'failed'" class="space-y-3.5 py-4 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full border border-rose-500/30 bg-rose-500/10 text-rose-600 shadow-sm">
                        <XCircle class="h-7 w-7" />
                    </div>

                    <div class="space-y-0.5">
                        <h3 class="text-sm font-black text-rose-700 dark:text-rose-400 uppercase tracking-tight">
                            Settlement Request Failed
                        </h3>
                        <p class="text-[10px] text-muted-foreground">
                            The transfer could not be completed by Safaricom M-Pesa.
                        </p>
                    </div>

                    <div class="mx-auto max-w-sm rounded-lg border border-rose-500/20 bg-rose-500/5 p-3 text-left space-y-1.5 text-[10px]">
                        <span class="font-bold text-rose-700 dark:text-rose-400 uppercase text-[9px]">Failure Reason</span>
                        <p class="font-medium text-rose-900 dark:text-rose-200">
                            {{ errorMessage || 'Safaricom M-Pesa rejected the request.' }}
                        </p>
                        <p class="text-[9px] text-muted-foreground pt-1 border-t border-rose-500/10">
                            Your account balance remains untouched.
                        </p>
                    </div>
                </div>

                <!-- 4. TIMEOUT STATE -->
                <div v-else-if="transferState === 'timeout'" class="space-y-3.5 py-4 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full border border-amber-500/30 bg-amber-500/10 text-amber-600 shadow-sm">
                        <AlertTriangle class="h-7 w-7" />
                    </div>

                    <div class="space-y-0.5">
                        <h3 class="text-sm font-black text-amber-700 dark:text-amber-400 uppercase tracking-tight">
                            Callback Taking Longer Than Usual
                        </h3>
                        <p class="text-[10px] text-muted-foreground">
                            The request was transmitted to Safaricom, but the callback response has not arrived yet.
                        </p>
                    </div>

                    <div class="mx-auto max-w-sm rounded-lg border border-amber-500/20 bg-amber-500/5 p-3 text-left space-y-1 text-[10px] text-amber-900 dark:text-amber-200">
                        <p class="font-medium">
                            The transfer is listed as <strong>PENDING</strong> in your Inter-Account Fund Movements table. Once Safaricom finishes processing, the status will update automatically.
                        </p>
                    </div>
                </div>

                <!-- 5. CONFIRMATION STEP VIEW -->
                <div v-else-if="transferState === 'confirm'" class="space-y-3">
                    <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-2.5">
                        <div class="flex items-start gap-2">
                            <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                            <div class="space-y-0.5">
                                <h4 class="text-[10px] font-black tracking-wider text-amber-700 uppercase dark:text-amber-400">
                                    Confirm Live Settlement Transaction
                                </h4>
                                <p class="text-[10px] font-medium leading-snug text-amber-800 dark:text-amber-300">
                                    This action initiates a real-time, irreversible fund transfer via Safaricom Daraja M-Pesa B2B API to your bank.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Transfer Summary Review -->
                    <div class="overflow-hidden rounded-lg border border-border bg-card/60 shadow-sm">
                        <div class="border-b border-border/50 bg-muted/20 px-3 py-1.5 text-[9px] font-black tracking-widest text-muted-foreground uppercase">
                            Transfer Execution Details
                        </div>
                        <div class="divide-y divide-border/40 text-[11px]">
                            <div class="flex justify-between px-3 py-1.5">
                                <span class="font-bold text-muted-foreground uppercase text-[10px]">Amount to Settle:</span>
                                <span class="text-xs font-black text-emerald-600 tabular-nums">
                                    {{ account?.currency || 'KES' }} {{ formatCurrency(numericAmount) }}
                                </span>
                            </div>
                            <div class="flex justify-between px-3 py-1.5">
                                <span class="font-bold text-muted-foreground uppercase text-[10px]">Source Paybill:</span>
                                <span class="font-black text-foreground uppercase text-[10px]">
                                    {{ account.name }} ({{ sourceShortcode }})
                                </span>
                            </div>
                            <div class="flex justify-between px-3 py-1.5">
                                <span class="font-bold text-muted-foreground uppercase text-[10px]">Destination Bank:</span>
                                <span class="font-black text-foreground uppercase text-[10px]">
                                    {{ destinationBank?.name || 'Bank' }}
                                </span>
                            </div>
                            <div class="flex justify-between px-3 py-1.5">
                                <span class="font-bold text-muted-foreground uppercase text-[10px]">Destination Account No:</span>
                                <span class="font-mono font-black text-blue-600 text-[10px]">
                                    {{ destinationAccountNumber }}
                                </span>
                            </div>
                            <div class="flex justify-between px-3 py-1.5">
                                <span class="font-bold text-muted-foreground uppercase text-[10px]">Bank B2B Paybill (Party B):</span>
                                <span class="font-mono font-black text-indigo-600 text-[10px]">
                                    {{ destinationPaybill }}
                                </span>
                            </div>
                            <div v-if="form.remarks" class="flex justify-between px-3 py-1.5">
                                <span class="font-bold text-muted-foreground uppercase text-[10px]">Remarks:</span>
                                <span class="max-w-[60%] truncate text-right font-medium text-foreground text-[10px]">
                                    {{ form.remarks }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 6. INPUT FORM VIEW -->
                <div v-else class="space-y-3">
                    <!-- Accounts Flow Grid (Compact) -->
                    <div class="grid grid-cols-1 items-center gap-2 sm:grid-cols-9">
                        <!-- Source Card (4 cols) -->
                        <div class="sm:col-span-4 rounded-lg border border-emerald-500/20 bg-emerald-500/5 p-2.5">
                            <div class="flex items-center gap-1.5">
                                <Smartphone class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                                <span class="text-[9px] font-black tracking-widest text-emerald-600 uppercase dark:text-emerald-400">
                                    From Paybill
                                </span>
                            </div>
                            <p class="mt-0.5 truncate text-[11px] font-black text-foreground uppercase" :title="account.name">
                                {{ account.name }}
                            </p>
                            <div class="mt-1.5 flex flex-col gap-0.5 text-[10px]">
                                <div class="flex items-center justify-between">
                                    <span class="text-[9px] font-bold text-muted-foreground uppercase">Shortcode:</span>
                                    <span class="font-mono font-black">{{ sourceShortcode }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-[9px] font-bold text-muted-foreground uppercase">Available:</span>
                                    <span class="font-black text-emerald-600 tabular-nums">
                                        {{ account.currency || 'KES' }} {{ formatCurrency(sourceBalance) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Arrow Indicator (1 col) -->
                        <div class="sm:col-span-1 flex flex-col items-center justify-center text-muted-foreground">
                            <div class="flex h-6 w-6 items-center justify-center rounded-full border border-border bg-muted/40 shadow-inner">
                                <ArrowRight class="h-3 w-3 text-emerald-600" />
                            </div>
                        </div>

                        <!-- Destination Card (4 cols) -->
                        <div
                            :class="[
                                'sm:col-span-4 rounded-lg border p-2.5',
                                isConfigured ? 'border-blue-500/20 bg-blue-500/5' : 'border-rose-500/30 bg-rose-500/5',
                            ]"
                        >
                            <div class="flex items-center gap-1.5">
                                <Landmark
                                    :class="[
                                        'h-3.5 w-3.5',
                                        isConfigured ? 'text-blue-600 dark:text-blue-400' : 'text-rose-500',
                                    ]"
                                />
                                <span
                                    :class="[
                                        'text-[9px] font-black tracking-widest uppercase',
                                        isConfigured ? 'text-blue-600 dark:text-blue-400' : 'text-rose-600',
                                    ]"
                                >
                                    To Settlement Bank
                                </span>
                            </div>

                            <template v-if="linkedBankAcc">
                                <p class="mt-0.5 truncate text-[11px] font-black text-foreground uppercase" :title="linkedBankAcc.name">
                                    {{ linkedBankAcc.name }}
                                </p>
                                <div class="mt-1.5 flex flex-col gap-0.5 text-[10px]">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[9px] font-bold text-muted-foreground uppercase">Bank:</span>
                                        <span class="font-black text-foreground truncate max-w-[120px]">
                                            {{ destinationBank?.name || 'Bank' }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-[9px] font-bold text-muted-foreground uppercase">Acc No:</span>
                                        <span class="font-mono font-bold text-blue-600">{{ destinationAccountNumber }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-[9px] font-bold text-muted-foreground uppercase">B2B Paybill:</span>
                                        <span
                                            :class="[
                                                'font-mono font-black',
                                                destinationPaybill ? 'text-indigo-600' : 'text-rose-600',
                                            ]"
                                        >
                                            {{ destinationPaybill || 'Missing' }}
                                        </span>
                                    </div>
                                </div>
                            </template>
                            <template v-else>
                                <p class="mt-1 text-[10px] font-bold text-rose-600">
                                    No linked bank account configured.
                                </p>
                            </template>
                        </div>
                    </div>

                    <!-- Missing Config Warning -->
                    <div v-if="!isConfigured" class="flex items-start gap-2 rounded-lg border border-rose-500/30 bg-rose-500/10 p-2.5 text-[10px] text-rose-800 dark:text-rose-300">
                        <AlertCircle class="mt-0.5 h-3.5 w-3.5 shrink-0 text-rose-600" />
                        <div>
                            <span class="font-black uppercase">Configuration Required:</span>
                            This Paybill must have a linked settlement bank account with a valid Paybill Number configured on its bank profile before B2B transfers can be initiated.
                        </div>
                    </div>

                    <!-- Amount Section (Compact) -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <Label for="amount" class="text-[9px] font-black tracking-widest text-muted-foreground uppercase">
                                Transfer Amount <span class="text-rose-500">*</span>
                            </Label>
                            <div class="flex items-center gap-1">
                                <button
                                    type="button"
                                    @click="setPercentage(25)"
                                    :disabled="sourceBalance <= 0"
                                    class="rounded border border-border/60 bg-muted/40 px-1.5 py-0.5 text-[8.5px] font-black text-muted-foreground transition hover:border-emerald-500/40 hover:bg-emerald-500/10 hover:text-emerald-600 disabled:opacity-40"
                                >
                                    25%
                                </button>
                                <button
                                    type="button"
                                    @click="setPercentage(50)"
                                    :disabled="sourceBalance <= 0"
                                    class="rounded border border-border/60 bg-muted/40 px-1.5 py-0.5 text-[8.5px] font-black text-muted-foreground transition hover:border-emerald-500/40 hover:bg-emerald-500/10 hover:text-emerald-600 disabled:opacity-40"
                                >
                                    50%
                                </button>
                                <button
                                    type="button"
                                    @click="setPercentage(75)"
                                    :disabled="sourceBalance <= 0"
                                    class="rounded border border-border/60 bg-muted/40 px-1.5 py-0.5 text-[8.5px] font-black text-muted-foreground transition hover:border-emerald-500/40 hover:bg-emerald-500/10 hover:text-emerald-600 disabled:opacity-40"
                                >
                                    75%
                                </button>
                                <button
                                    type="button"
                                    @click="setPercentage(100)"
                                    :disabled="sourceBalance <= 0"
                                    class="rounded border border-emerald-500/30 bg-emerald-500/10 px-1.5 py-0.5 text-[8.5px] font-black text-emerald-600 transition hover:bg-emerald-500/20 disabled:opacity-40"
                                >
                                    100% Full
                                </button>
                            </div>
                        </div>

                        <div class="relative">
                            <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-[11px] font-black text-muted-foreground">
                                {{ account?.currency || 'KES' }}
                            </span>
                            <Input
                                id="amount"
                                type="text"
                                inputmode="decimal"
                                v-model="formattedAmount"
                                @keypress="handleKeyPress"
                                @paste="handlePaste"
                                placeholder="0.00"
                                class="h-8.5 pl-11 pr-3 text-xs font-black tracking-tight tabular-nums"
                                :class="{ 'border-rose-500 focus-visible:ring-rose-500': amountError || form.errors.amount }"
                            />
                        </div>

                        <p v-if="amountError || form.errors.amount" class="text-[9px] font-bold text-rose-500">
                            {{ amountError || form.errors.amount }}
                        </p>
                    </div>

                    <!-- Remarks Section (Compact) -->
                    <div class="space-y-1">
                        <Label for="remarks" class="text-[9px] font-black tracking-widest text-muted-foreground uppercase">
                            Transfer Remarks / Notes
                            <span class="text-[8px] font-normal text-muted-foreground lowercase">(optional)</span>
                        </Label>
                        <Textarea
                            id="remarks"
                            v-model="form.remarks"
                            rows="2"
                            placeholder="e.g. End of day Paybill settlement to bank account"
                            class="min-h-[44px] px-2.5 py-1.5 text-[10px]"
                        />
                    </div>
                </div>
            </div>

            <!-- Modal Footer (Compact) -->
            <DialogFooter class="flex shrink-0 items-center justify-between border-t border-border/50 bg-muted/10 px-4 py-2">
                <div class="flex items-center gap-1 text-muted-foreground">
                    <ShieldCheck class="h-3.5 w-3.5 text-emerald-600" />
                    <span class="text-[9px] font-black tracking-wider uppercase">
                        Encrypted Daraja B2B
                    </span>
                </div>

                <div class="flex items-center gap-1.5">
                    <!-- Form State Buttons -->
                    <template v-if="transferState === 'form'">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="closeModal"
                            class="h-7.5 px-3 text-[10px] font-bold uppercase"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            @click="requestConfirmation"
                            :disabled="!canProceed"
                            class="h-7.5 gap-1.5 bg-emerald-600 px-3.5 text-[10px] font-black tracking-wider text-white uppercase shadow-sm shadow-emerald-500/20 hover:bg-emerald-700"
                        >
                            <Send class="h-3 w-3" />
                            Initiate Transfer
                        </Button>
                    </template>

                    <!-- Confirm State Buttons -->
                    <template v-else-if="transferState === 'confirm'">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="transferState = 'form'"
                            class="h-7.5 px-3 text-[10px] font-bold uppercase"
                        >
                            Back
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            @click="submitTransfer"
                            :disabled="isSubmitting"
                            class="h-7.5 gap-1.5 bg-emerald-600 px-4 text-[10px] font-black tracking-wider text-white uppercase shadow-sm shadow-emerald-500/20 hover:bg-emerald-700"
                        >
                            <CheckCircle2 class="h-3.5 w-3.5" />
                            Confirm & Send
                        </Button>
                    </template>

                    <!-- Waiting State (Active Polling) -->
                    <template v-else-if="transferState === 'waiting'">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled
                            class="h-7.5 px-3 text-[10px] font-bold uppercase opacity-60"
                        >
                            Please Wait...
                        </Button>
                    </template>

                    <!-- Success State Button -->
                    <template v-else-if="transferState === 'success'">
                        <Button
                            type="button"
                            size="sm"
                            @click="closeAndRefresh"
                            class="h-7.5 gap-1.5 bg-emerald-600 px-4 text-[10px] font-black tracking-wider text-white uppercase shadow-sm shadow-emerald-500/20 hover:bg-emerald-700"
                        >
                            <Check class="h-3.5 w-3.5" />
                            Done & Refresh
                        </Button>
                    </template>

                    <!-- Failed State Buttons -->
                    <template v-else-if="transferState === 'failed'">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="transferState = 'form'"
                            class="h-7.5 gap-1 px-3 text-[10px] font-bold uppercase"
                        >
                            <RotateCcw class="h-3 w-3" />
                            Try Again
                        </Button>
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            @click="closeAndRefresh"
                            class="h-7.5 px-3 text-[10px] font-bold uppercase"
                        >
                            Close
                        </Button>
                    </template>

                    <!-- Timeout State Buttons -->
                    <template v-else-if="transferState === 'timeout'">
                        <Button
                            type="button"
                            size="sm"
                            @click="closeAndRefresh"
                            class="h-7.5 gap-1 bg-amber-600 px-3.5 text-[10px] font-black tracking-wider text-white uppercase shadow-sm hover:bg-amber-700"
                        >
                            View Fund Movements
                        </Button>
                    </template>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
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
