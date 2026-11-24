<script setup lang="ts">
import { ref, onUnmounted } from 'vue';
import { Loader2, Smartphone, CheckCircle, AlertCircle, X } from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        open: boolean;
        amount: number;
        initialPhone?: string;
        reference?: string;
        currency?: string;
        stkEndpoint?: string;
    }>(),
    {
        initialPhone: '',
        reference: '',
        currency: 'KES',
        stkEndpoint: '/api/v1/payments-engine/mpesa/stk-push',
    }
);

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'success', data: any): void;
    (e: 'error', error: string): void;
}>();

const phone = ref(props.initialPhone || '');
const loading = ref(false);
const polling = ref(false);
const countdown = ref(45);
const statusMessage = ref('');
const errorMessage = ref('');
const completed = ref(false);
let timerInterval: any = null;

const formatPhoneNumber = (val: string) => {
    let clean = val.replace(/[^0-9]/g, '');
    if (clean.startsWith('0')) clean = '254' + clean.slice(1);
    else if (clean.startsWith('+254')) clean = clean.slice(1);
    return clean;
};

const initiateStk = async () => {
    errorMessage.value = '';
    const formattedPhone = formatPhoneNumber(phone.value);

    if (formattedPhone.length < 12) {
        errorMessage.value = 'Please enter a valid Kenyan phone number (e.g. 07XXXXXXXX or 2547XXXXXXXX).';
        return;
    }

    loading.value = true;
    statusMessage.value = 'Initiating STK Push prompt...';

    try {
        const response = await fetch(props.stkEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                phone_number: formattedPhone,
                amount: props.amount,
                reference: props.reference,
            }),
        });

        const data = await response.json();

        if (response.ok && data.success) {
            loading.value = false;
            polling.value = true;
            statusMessage.value = 'STK prompt sent to phone. Enter PIN on your handset...';
            startCountdown();
        } else {
            throw new Error(data.message || 'Failed to initiate STK push.');
        }
    } catch (err: any) {
        loading.value = false;
        errorMessage.value = err.message || 'Error communicating with payment gateway.';
        emit('error', errorMessage.value);
    }
};

const startCountdown = () => {
    countdown.value = 45;
    clearInterval(timerInterval);
    timerInterval = setInterval(() => {
        countdown.value--;
        if (countdown.value <= 0) {
            clearInterval(timerInterval);
            polling.value = false;
            statusMessage.value = 'Timeout awaiting customer response.';
        }
    }, 1000);
};

const close = () => {
    clearInterval(timerInterval);
    emit('close');
};

onUnmounted(() => {
    clearInterval(timerInterval);
});
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
        <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800">
                <div class="flex items-center gap-2">
                    <Smartphone class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">M-Pesa STK Express Checkout</h3>
                </div>
                <button type="button" @click="close" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <div class="space-y-4">
                <div class="rounded-xl bg-slate-50 p-3 text-center dark:bg-slate-800/60">
                    <span class="text-xs text-slate-500">Payable Amount</span>
                    <div class="text-2xl font-black text-slate-900 dark:text-white">
                        {{ currency }} {{ amount.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">Customer Phone Number</label>
                    <input
                        type="text"
                        v-model="phone"
                        placeholder="0712345678 or 254712345678"
                        :disabled="loading || polling"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-900 placeholder-slate-400 focus:border-emerald-500 focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    />
                </div>

                <div v-if="errorMessage" class="flex items-center gap-2 rounded-lg bg-rose-50 p-2.5 text-xs text-rose-700 dark:bg-rose-950/40 dark:text-rose-400">
                    <AlertCircle class="h-4 w-4 shrink-0" />
                    <span>{{ errorMessage }}</span>
                </div>

                <div v-if="statusMessage" class="flex items-center gap-2 rounded-lg bg-emerald-50 p-2.5 text-xs text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                    <Loader2 v-if="polling" class="h-4 w-4 animate-spin shrink-0" />
                    <CheckCircle v-else class="h-4 w-4 shrink-0" />
                    <span>{{ statusMessage }} {{ polling ? `(${countdown}s)` : '' }}</span>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="close"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        :disabled="loading || polling"
                        @click="initiateStk"
                        class="flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-50"
                    >
                        <Loader2 v-if="loading" class="h-3.5 w-3.5 animate-spin" />
                        <span>{{ loading ? 'Sending...' : 'Send STK Prompt' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
