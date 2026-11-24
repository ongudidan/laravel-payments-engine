<script setup lang="ts">
import Pagination from '../../../../Components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { router } from '@inertiajs/vue3';
import {
    Check,
    Copy,
    History,
    RefreshCw,
} from 'lucide-vue-next';
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<{
    paymentAccount: any;
    requests: {
        data: any[];
        links: any[];
        from: number;
        to: number;
        total: number;
    };
    stats: {
        has_pending_query: boolean;
        total_queries: number;
    };
}>();

// Polling interval if pending query is active
let pollTimer: any = null;
const isPolling = ref(false);

const startPolling = () => {
    if (pollTimer) return;
    isPolling.value = true;
    pollTimer = setInterval(() => {
        router.reload({
            only: ['requests', 'stats'],
            preserveScroll: true,
        });
    }, 5000);
};

const stopPolling = () => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
        isPolling.value = false;
    }
};

onMounted(() => {
    if (props.stats.has_pending_query) {
        startPolling();
    }
});

onUnmounted(() => {
    stopPolling();
});

const manualRefresh = () => {
    router.reload({
        only: ['requests', 'stats'],
        preserveScroll: true,
    });
};

const formatDateTime = (val: string) => {
    if (!val) return '—';
    try {
        const d = new Date(val);
        return d.toLocaleString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
    } catch {
        return val;
    }
};

const formatCurrency = (amount: number | string) => {
    return Number(amount || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

// Payload Modal state
const payloadModalOpen = ref(false);
const selectedPayload = ref<any>(null);
const selectedPayloadTitle = ref('');
const copiedPayload = ref(false);

const viewPayload = (title: string, payload: any) => {
    selectedPayloadTitle.value = title;
    selectedPayload.value = payload;
    payloadModalOpen.value = true;
};

const copyPayloadJson = () => {
    if (!selectedPayload.value) return;
    navigator.clipboard.writeText(JSON.stringify(selectedPayload.value, null, 2));
    copiedPayload.value = true;
    setTimeout(() => {
        copiedPayload.value = false;
    }, 2000);
};
</script>

<template>
    <div class="space-y-2.5">
        <!-- Active Pending Query Banner -->
        <div
            v-if="stats.has_pending_query"
            class="flex items-center justify-between rounded-lg border border-indigo-500/30 bg-indigo-500/10 p-2.5 text-indigo-900 shadow-xs backdrop-blur-md dark:text-indigo-200"
        >
            <div class="flex items-center gap-2">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-indigo-400 opacity-75"></span>
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-indigo-500"></span>
                </span>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-wider">
                        Safaricom Query In Progress
                    </p>
                    <p class="text-[9.5px] text-muted-foreground">
                        A pull query is currently processing. Safaricom is retrieving the transaction records. Auto-refreshes every 5s.
                    </p>
                </div>
            </div>
            <Button
                size="sm"
                variant="ghost"
                @click="manualRefresh"
                class="h-6 gap-1 text-[9.5px] font-bold text-indigo-700 hover:bg-indigo-500/20 dark:text-indigo-300"
            >
                <RefreshCw class="h-2.5 w-2.5 animate-spin" />
                <span>Check Now</span>
            </Button>
        </div>

        <!-- Query Batches Table -->
        <div class="flex-1 overflow-hidden rounded-lg border border-border/50 bg-card/30 shadow-xs backdrop-blur-md">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-border/60 bg-muted/60 text-[9.5px] font-black uppercase tracking-wider text-muted-foreground">
                            <th class="px-3 py-1.5">Batch ID</th>
                            <th class="px-3 py-1.5">Period Window</th>
                            <th class="px-3 py-1.5">Offset</th>
                            <th class="px-3 py-1.5">Status</th>
                            <th class="px-3 py-1.5 text-right">Transactions</th>
                            <th class="px-3 py-1.5 text-right">Total Amount</th>
                            <th class="px-3 py-1.5">Triggered By</th>
                            <th class="px-3 py-1.5 text-right">Payload</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/30">
                        <tr
                            v-for="req in requests.data"
                            :key="req.id"
                            class="transition-colors hover:bg-muted/20"
                        >
                            <td class="px-3 py-1 font-mono font-bold text-[10.5px]">
                                #{{ req.id }}
                            </td>

                            <td class="px-3 py-1 whitespace-nowrap text-muted-foreground">
                                <div class="flex flex-col text-[10.5px] leading-tight">
                                    <span class="font-medium text-foreground">{{ formatDateTime(req.start_date) }}</span>
                                    <span class="text-[9px]">to {{ formatDateTime(req.end_date) }}</span>
                                </div>
                            </td>

                            <td class="px-3 py-1 font-mono text-[10px]">
                                {{ req.offset_value }}
                            </td>

                            <td class="px-3 py-1">
                                <Badge
                                    v-if="req.status === 'completed'"
                                    variant="outline"
                                    class="border-emerald-500/30 bg-emerald-500/10 text-[8px] font-black uppercase text-emerald-600 px-1.5 py-0.2"
                                >
                                    Completed
                                </Badge>
                                <Badge
                                    v-else-if="req.status === 'pending'"
                                    variant="outline"
                                    class="border-blue-500/30 bg-blue-500/10 text-[8px] font-black uppercase text-blue-600 animate-pulse px-1.5 py-0.2"
                                >
                                    Pending
                                </Badge>
                                <Badge
                                    v-else
                                    variant="outline"
                                    class="border-rose-500/30 bg-rose-500/10 text-[8px] font-black uppercase text-rose-600 px-1.5 py-0.2"
                                >
                                    Failed
                                </Badge>
                                <span
                                    v-if="req.response_description"
                                    class="mt-0.5 block max-w-[130px] truncate text-[8px] text-muted-foreground"
                                    :title="req.response_description"
                                >
                                    {{ req.response_description }}
                                </span>
                            </td>

                            <td class="px-3 py-1 text-right font-black tabular-nums text-foreground text-[11px]">
                                {{ req.total_transactions_count }}
                            </td>

                            <td class="px-3 py-1 text-right font-black tabular-nums text-emerald-600 text-[11px]">
                                {{ formatCurrency(req.total_amount) }}
                            </td>

                            <td class="px-3 py-1 text-muted-foreground">
                                <div class="flex flex-col text-[10px] leading-tight">
                                    <span class="font-bold text-foreground">{{ req.user?.name || 'System' }}</span>
                                    <span class="text-[9px]">{{ formatDateTime(req.created_at) }}</span>
                                </div>
                            </td>

                            <td class="px-3 py-1 text-right">
                                <Button
                                    v-if="req.raw_callback_payload"
                                    size="sm"
                                    variant="ghost"
                                    @click="viewPayload(`Batch #${req.id} Response Payload`, req.raw_callback_payload)"
                                    class="h-5.5 px-2 text-[8px] font-bold text-muted-foreground hover:text-foreground"
                                >
                                    View JSON
                                </Button>
                                <Button
                                    v-else-if="req.raw_request_payload"
                                    size="sm"
                                    variant="ghost"
                                    @click="viewPayload(`Batch #${req.id} Request Payload`, req.raw_request_payload)"
                                    class="h-5.5 px-2 text-[8px] font-bold text-muted-foreground hover:text-foreground"
                                >
                                    Request JSON
                                </Button>
                                <span v-else class="text-muted-foreground text-[9px]">—</span>
                            </td>
                        </tr>

                        <tr v-if="requests.data.length === 0">
                            <td colspan="8" class="p-8 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center gap-1.5">
                                    <History class="h-6 w-6 text-muted-foreground/40" />
                                    <p class="text-[11px] font-bold uppercase tracking-wider">
                                        No pull queries recorded
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination
                v-if="requests.links && requests.total > 0"
                :links="requests.links"
                :from="requests.from || 0"
                :to="requests.to || 0"
                :total="requests.total"
                :only="['requests', 'stats']"
            />
        </div>

        <!-- Payload Viewer Modal -->
        <Dialog :open="payloadModalOpen" @update:open="payloadModalOpen = $event">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader class="flex flex-row items-center justify-between pr-6">
                    <div>
                        <DialogTitle class="text-sm font-black uppercase tracking-wider text-foreground">
                            {{ selectedPayloadTitle }}
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground">
                            Raw JSON payload received from Safaricom Daraja API
                        </DialogDescription>
                    </div>
                    <Button
                        size="sm"
                        variant="outline"
                        @click="copyPayloadJson"
                        class="h-7 gap-1 text-[10px] font-bold"
                    >
                        <Copy v-if="!copiedPayload" class="h-3 w-3" />
                        <Check v-else class="h-3 w-3 text-emerald-500" />
                        <span>{{ copiedPayload ? 'Copied' : 'Copy JSON' }}</span>
                    </Button>
                </DialogHeader>

                <div class="max-h-[60vh] overflow-y-auto rounded bg-muted/50 p-3 font-mono text-[11px] leading-relaxed">
                    <pre class="whitespace-pre-wrap">{{ JSON.stringify(selectedPayload, null, 2) }}</pre>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
