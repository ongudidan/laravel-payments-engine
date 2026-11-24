<script setup lang="ts">
import { AlertTriangle, Loader2, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    cheque: any;
    processing: boolean;
}>();

const emit = defineEmits(['close', 'confirm']);
const cancelReason = ref('');

watch(
    () => props.cheque,
    () => {
        cancelReason.value = '';
    },
);

const handleClose = () => {
    cancelReason.value = '';
    emit('close');
};

const handleConfirm = () => {
    emit('confirm', cancelReason.value);
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
            <div class="mb-4 flex items-center gap-4 pr-8">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-xl border border-rose-500/20 bg-rose-500/10 text-rose-600"
                >
                    <AlertTriangle class="h-6 w-6" />
                </div>
                <div>
                    <h3
                        class="text-sm font-black tracking-widest text-rose-600 uppercase"
                    >
                        Void Cheque Payment
                    </h3>
                    <p
                        class="mt-0.5 text-[10px] font-bold tracking-tight text-muted-foreground uppercase"
                    >
                        Irreversible Action
                    </p>
                </div>
            </div>

            <div class="mb-6 space-y-1.5">
                <label
                    class="block px-1 text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                    >Reason for Cancellation</label
                >
                <textarea
                    v-model="cancelReason"
                    rows="3"
                    placeholder="Explain why this payment is being voided..."
                    class="w-full resize-none rounded-xl border border-border bg-accent/5 p-3 text-xs font-bold transition-all outline-none placeholder:text-muted-foreground/40 focus:border-rose-500 focus:ring-rose-500/20"
                ></textarea>
            </div>

            <div class="flex justify-end gap-3">
                <button
                    @click="handleClose"
                    class="px-4 py-2 text-[9px] font-black tracking-widest text-muted-foreground uppercase transition-all hover:text-foreground"
                >
                    Abort
                </button>
                <button
                    @click="handleConfirm"
                    :disabled="!cancelReason || processing"
                    class="flex items-center gap-2 rounded-xl bg-rose-600 px-5 py-2.5 text-[9px] font-black tracking-[0.1em] text-white uppercase shadow-lg shadow-rose-500/20 transition-all hover:bg-rose-500 active:scale-95 disabled:cursor-not-allowed disabled:opacity-30"
                >
                    <Loader2
                        v-if="processing"
                        class="h-3.5 w-3.5 animate-spin"
                    />
                    <AlertTriangle v-else class="h-3.5 w-3.5" />
                    <span>{{
                        processing ? 'Voiding...' : 'Void Cheque Now'
                    }}</span>
                </button>
            </div>
        </div>
    </div>
</template>
