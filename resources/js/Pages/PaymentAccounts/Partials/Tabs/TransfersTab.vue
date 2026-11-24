<script setup lang="ts">
import Pagination from '../../../../Components/Pagination.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { router, useForm } from '@inertiajs/vue3';
import 'flatpickr/dist/flatpickr.css';
import {
    AlertCircle,
    ArrowDownLeft,
    ArrowLeftRight,
    ArrowUpRight,
    Banknote,
    Building2,
    Calendar,
    Check,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    Clock,
    Code2,
    Copy,
    Eye,
    Loader2,
    MessageSquare,
    RefreshCw,
    User,
    X,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import FlatPickr from 'vue-flatpickr-component';

const copiedMpesaJson = ref(false);
const showMpesaRawPayload = ref(false);
const checkingTransferId = ref<number | null>(null);
const pollingStatusMessage = ref<string | null>(null);

const copyMpesaJson = (data: any) => {
    if (!data) return;
    navigator.clipboard.writeText(typeof data === 'string' ? data : JSON.stringify(data, null, 2));
    copiedMpesaJson.value = true;
    setTimeout(() => {
        copiedMpesaJson.value = false;
    }, 2000);
};

const isB2bTransfer = (transfer: any) => {
    if (!transfer) return false;
    return (
        Boolean(transfer.reference && String(transfer.reference).startsWith('B2B-')) ||
        Boolean(transfer.mpesa_transaction) ||
        Boolean(transfer.to_account?.bank?.paybill_number) ||
        Boolean(transfer.notes && String(transfer.notes).includes('B2B'))
    );
};

const checkB2bStatus = async (transfer: any) => {
    if (!transfer || !props.account?.id) return;
    checkingTransferId.value = transfer.id;
    pollingStatusMessage.value = 'Polling Safaricom for gateway callback...';

    try {
        const response = await fetch(
            `/organization/financial-profiles/payment-accounts/${props.account.id}/b2b-status/${transfer.id}`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        if (response.ok) {
            const data = await response.json();
            if (data.status && data.status !== 'pending') {
                transfer.status = data.status;
                if (selectedTransfer.value && selectedTransfer.value.id === transfer.id) {
                    selectedTransfer.value.status = data.status;
                    selectedTransfer.value.transaction_number = data.transaction_number;
                    selectedTransfer.value.mpesa_transaction = data.mpesa_transaction;
                    selectedTransfer.value.notes = data.notes;
                }
                pollingStatusMessage.value =
                    data.status === 'approved'
                        ? 'Transfer confirmed by Safaricom! Updating...'
                        : 'Transfer was marked ' + data.status + ' by Safaricom.';
                setTimeout(() => {
                    router.reload({ preserveScroll: true });
                }, 800);
            } else {
                pollingStatusMessage.value =
                    data.result_desc || 'Transfer is still pending in Safaricom queue.';
                setTimeout(() => {
                    pollingStatusMessage.value = null;
                }, 4000);
            }
        } else {
            pollingStatusMessage.value = 'Could not query status at this moment.';
            setTimeout(() => {
                pollingStatusMessage.value = null;
            }, 3000);
        }
    } catch (err) {
        console.error('B2B polling error:', err);
        pollingStatusMessage.value = 'Network error while checking status.';
        setTimeout(() => {
            pollingStatusMessage.value = null;
        }, 3000);
    } finally {
        checkingTransferId.value = null;
    }
};

const props = defineProps<{
    account: any;
    transfers: any;
    filters?: any;
}>();

const emit = defineEmits<{
    (e: 'request-transfer'): void;
    (e: 'b2b-transfer'): void;
}>();

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

const urlParams = new URLSearchParams(window.location.search);
const statusFilter = ref(urlParams.get('transfer_status') || 'all');
const typeFilter = ref(urlParams.get('transfer_type') || 'all');
const dateRange = ref(urlParams.get('date_range') || props.filters?.date_range || '');

const flatpickrConfig: any = {
    mode: 'range',
    altInput: true,
    altFormat: 'F j, Y',
    dateFormat: 'Y-m-d',
};

const updateFilters = () => {
    const params = new URLSearchParams(window.location.search);
    const query: Record<string, any> = {};
    params.forEach((val, key) => {
        query[key] = val;
    });

    query.tab = 'transfers';
    query.transfer_type = typeFilter.value;
    query.transfer_status = statusFilter.value;
    query.date_range = dateRange.value;
    query.transfers_page = 1;

    router.get(window.location.pathname, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const handleDateRangeChange = (selectedDates: any, dateStr: string) => {
    if (!dateStr || selectedDates.length === 2) {
        updateFilters();
    }
};

const clearDateRange = () => {
    dateRange.value = '';
    updateFilters();
};

watch([typeFilter, statusFilter], () => {
    updateFilters();
});

const formatCurrency = (amount: number | string) => {
    return Number(amount || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Approval Logic
const showApprovalModal = ref(false);
const selectedTransfer = ref<any>(null);

const approvalForm = useForm({
    received_amount: 0,
    notes: '',
    transaction_number: '',
    denominations: {} as Record<string, number>,
});

const openApprovalModal = (transfer: any) => {
    selectedTransfer.value = transfer;
    approvalForm.received_amount = Number(transfer.amount);
    approvalForm.notes = '';
    approvalForm.transaction_number = '';

    // Initialize verification denominations
    const denoms: Record<string, number> = {};

    if (transfer.denominations?.length) {
        // If transfer has denominations, use them (verification mode)
        transfer.denominations.forEach((d: any) => {
            denoms[String(d.denomination_id)] = Number(d.quantity);
        });
    } else if (props.account.paymentMethod?.denominations?.length) {
        // If transfer has NO denominations but receiving account DOES, show empty grid
        props.account.paymentMethod.denominations.forEach((d: any) => {
            denoms[String(d.id)] = 0;
        });
    }

    approvalForm.denominations = denoms;
    showApprovalModal.value = true;
};

// Auto-calculate amount if denominations are being verified
const totalVerifiedAmount = computed(() => {
    // Check if we are using the transfer's own denoms or the account's method denoms
    const denomsList = selectedTransfer.value?.denominations?.length
        ? selectedTransfer.value.denominations.map((d: any) => ({
              id: d.denomination_id,
              value: d.denomination?.value,
          }))
        : props.account.paymentMethod?.denominations?.map((d: any) => ({
              id: d.id,
              value: d.value,
          }));

    if (!denomsList?.length) return null;

    return Object.entries(approvalForm.denominations).reduce(
        (sum, [id, qty]) => {
            const d = denomsList.find((dl: any) => String(dl.id) === id);
            return sum + Number(d?.value || 0) * Number(qty);
        },
        0,
    );
});

watch(totalVerifiedAmount, (val) => {
    if (val !== null) {
        approvalForm.received_amount = val;
    }
});

const denominationsToVerify = computed(() => {
    if (selectedTransfer.value?.denominations?.length) {
        return selectedTransfer.value.denominations
            .map((d: any) => ({
                id: d.denomination_id,
                value: d.denomination?.value,
                sent_qty: d.quantity,
            }))
            .sort((a: any, b: any) => Number(b.value) - Number(a.value));
    }
    if (props.account.paymentMethod?.denominations?.length) {
        return props.account.paymentMethod.denominations
            .map((d: any) => ({
                id: d.id,
                value: d.value,
                sent_qty: null,
            }))
            .sort((a: any, b: any) => Number(b.value) - Number(a.value));
    }
    return [];
});

const submitApproval = () => {
    approvalForm.post(
        `/organization/financial-profiles/payment-account-transfers/${selectedTransfer.value.id}/approve`,
        {
            onSuccess: () => {
                showApprovalModal.value = false;
                selectedTransfer.value = null;
            },
        },
    );
};

const showRejectionModal = ref(false);
const rejectionForm = useForm({
    notes: '',
});

const openRejectionModal = (transfer: any) => {
    selectedTransfer.value = transfer;
    rejectionForm.notes = '';
    showRejectionModal.value = true;
};

const submitRejection = () => {
    rejectionForm.post(
        `/organization/financial-profiles/payment-account-transfers/${selectedTransfer.value.id}/cancel`,
        {
            onSuccess: () => {
                showRejectionModal.value = false;
                selectedTransfer.value = null;
            },
        },
    );
};

const cancelTransfer = (transfer: any) => {
    if (
        confirm(
            'Are you sure you want to cancel this transfer and revert funds?',
        )
    ) {
        rejectionForm.post(
            `/organization/financial-profiles/payment-account-transfers/${transfer.id}/cancel`,
        );
    }
};

const showDetailsModal = ref(false);

const openDetailsModal = (transfer: any) => {
    selectedTransfer.value = transfer;
    showDetailsModal.value = true;
};

const getStatusStyles = (status: string) => {
    switch (status.toLowerCase()) {
        case 'pending':
            return 'bg-orange-500/10 text-orange-600 border-orange-500/20';
        case 'approved':
            return 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20';
        case 'cancelled':
            return 'bg-rose-500/10 text-rose-600 border-rose-500/20';
        default:
            return 'bg-muted text-muted-foreground border-border';
    }
};
</script>

<template>
    <div class="space-y-4 pb-6">
        <!-- Filters -->
        <div
            class="flex shrink-0 items-center justify-between gap-4 rounded-lg border border-border/50 bg-card/20 p-3"
        >
            <div class="flex items-center gap-6">
                <div class="flex flex-col gap-1.5">
                    <span
                        class="pl-1 text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                        >Transfer Type</span
                    >
                    <div
                        class="flex rounded-md border border-border/50 bg-background/50 p-0.5"
                    >
                        <button
                            @click="typeFilter = 'all'"
                            :class="
                                typeFilter === 'all'
                                    ? 'bg-foreground text-background shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            class="rounded-sm px-3 py-1 text-[9px] font-black tracking-wider uppercase transition-all"
                        >
                            ALL FLOWS
                        </button>
                        <button
                            @click="typeFilter = 'incoming'"
                            :class="
                                typeFilter === 'incoming'
                                    ? 'bg-emerald-500 text-white shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            class="rounded-sm px-3 py-1 text-[9px] font-black tracking-wider uppercase transition-all"
                        >
                            INCOMING
                        </button>
                        <button
                            @click="typeFilter = 'outgoing'"
                            :class="
                                typeFilter === 'outgoing'
                                    ? 'bg-rose-500 text-white shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            class="rounded-sm px-3 py-1 text-[9px] font-black tracking-wider uppercase transition-all"
                        >
                            OUTGOING
                        </button>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <span
                        class="pl-1 text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                        >Status Clearance</span
                    >
                    <select
                        v-model="statusFilter"
                        class="h-7 rounded-md border border-border/50 bg-background px-2 text-[9px] font-black tracking-wider uppercase transition-all outline-none focus:border-emerald-500/50 focus:ring-0"
                    >
                        <option value="all">ALL STATUSES</option>
                        <option value="pending">PENDING APPROVAL</option>
                        <option value="approved">FULLY SETTLED</option>
                        <option value="cancelled">CANCELLED/REVERTED</option>
                    </select>
                </div>

                <div class="flex flex-col gap-1.5">
                    <span
                        class="pl-1 text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                        >Date Range</span
                    >
                    <div class="relative flex items-center">
                        <Calendar
                            class="pointer-events-none absolute left-2.5 z-10 h-3.5 w-3.5 text-muted-foreground/60"
                        />
                        <FlatPickr
                            v-model="dateRange"
                            :config="flatpickrConfig"
                            @on-change="handleDateRangeChange"
                            class="h-7 w-[210px] cursor-pointer rounded-md border border-border/50 bg-background pr-7 pl-8 text-[9px] font-black tracking-wider uppercase transition-all outline-none placeholder:text-muted-foreground/40 focus:border-emerald-500/50 focus:ring-0"
                            placeholder="FILTER BY DATE RANGE..."
                        />
                        <button
                            v-if="dateRange"
                            @click="clearDateRange"
                            class="absolute right-2 z-10 rounded-full p-0.5 text-muted-foreground/60 transition-all hover:bg-muted-foreground/10 hover:text-foreground"
                        >
                            <X class="h-3 w-3" />
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button
                    v-if="isPaybillAccount"
                    type="button"
                    @click="emit('b2b-transfer')"
                    class="flex h-8 items-center gap-2 rounded bg-indigo-600 px-4 text-[9px] font-black tracking-widest text-white uppercase shadow-sm transition-all hover:bg-indigo-700 active:scale-95"
                >
                    <Building2 class="h-3.5 w-3.5" />
                    Settle to Bank (B2B)
                </button>
                <button
                    type="button"
                    @click="emit('request-transfer')"
                    class="flex h-8 items-center gap-2 rounded bg-emerald-600 px-4 text-[9px] font-black tracking-widest text-white uppercase shadow-sm transition-all hover:bg-emerald-700 active:scale-95"
                >
                    <ArrowLeftRight class="h-3.5 w-3.5" />
                    Initiate New Transfer
                </button>
            </div>
        </div>

        <!-- Transfer List -->
        <div
            class="overflow-hidden rounded-lg border border-border bg-card/40 shadow-sm backdrop-blur-xl"
        >
            <div
                class="flex items-center justify-between border-b border-border bg-orange-500/5 px-4 py-2.5"
            >
                <div class="flex items-center gap-2">
                    <ArrowLeftRight class="h-3.5 w-3.5 text-orange-500" />
                    <span
                        class="text-[10px] font-black tracking-[0.2em] text-muted-foreground uppercase"
                        >Inter-Account Fund Movements</span
                    >
                </div>
                <div class="flex items-center gap-4">
                    <span
                        class="text-[9px] font-black tracking-widest text-muted-foreground/60 uppercase"
                    >
                        Showing {{ transfers.from || 1 }}-{{
                            transfers.to || (transfers.data || transfers).length
                        }}
                        of
                        {{
                            transfers.total ||
                            (transfers.data || transfers).length
                        }}
                        Entries
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-separate border-spacing-0">
                    <thead>
                        <tr class="bg-muted/30">
                            <th
                                class="border-b border-border px-4 py-2 text-left text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                            >
                                Initiated
                            </th>
                            <th
                                class="border-b border-border px-4 py-2 text-left text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                            >
                                Reference
                            </th>
                            <th
                                class="border-b border-border px-4 py-2 text-left text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                            >
                                Entity Loop
                            </th>
                            <th
                                class="border-b border-border px-4 py-2 text-right text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                            >
                                Amount
                            </th>
                            <th
                                class="border-b border-border px-4 py-2 text-center text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                            >
                                Status
                            </th>
                            <th
                                class="border-b border-border px-4 py-2 text-right text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/50">
                        <tr
                            v-for="t in transfers.data || transfers"
                            :key="t.id"
                            class="group transition-colors hover:bg-emerald-500/10 dark:hover:bg-emerald-500/20"
                        >
                            <td class="px-4 py-1.5 whitespace-nowrap">
                                <div class="flex flex-col">
                                    <span
                                        class="text-[10px] font-bold text-foreground"
                                        >{{ formatDate(t.created_at) }}</span
                                    >
                                    <div
                                        class="mt-0.5 flex items-center gap-1.5 opacity-60"
                                    >
                                        <User class="h-2.5 w-2.5" />
                                        <span
                                            class="text-[8px] font-black tracking-tighter uppercase"
                                            >{{ t.performer?.name }}</span
                                        >
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-1.5">
                                <div class="flex flex-col">
                                    <span
                                        class="text-[10px] font-black tracking-tighter text-foreground uppercase"
                                        >{{ t.reference }}</span
                                    >
                                    <span
                                        v-if="t.transaction_number"
                                        class="mt-0.5 w-fit rounded-sm border border-emerald-500/10 bg-emerald-500/5 px-1 text-[8px] font-black tracking-widest text-emerald-600 uppercase italic"
                                        >#{{ t.transaction_number }}</span
                                    >
                                    <span
                                        v-if="t.notes"
                                        class="mt-0.5 max-w-[150px] truncate text-[8px] font-bold text-muted-foreground uppercase opacity-60"
                                        >{{ t.notes }}</span
                                    >

                                    <!-- Vault Forwarding Status Pills -->
                                    <span
                                        v-if="t.to_account_id === account.id && (t.forwarding_transfer_id || t.forwarding_transfer || t.vault_batch_item)"
                                        class="mt-1 w-fit inline-flex items-center gap-1 rounded-sm border border-purple-500/30 bg-purple-500/10 px-1.5 py-0.5 text-[8px] font-black tracking-wider text-purple-700 dark:text-purple-300 uppercase"
                                        :title="'Transferred out of vault to ' + (t.forwarding_transfer?.to_account?.name || t.vault_batch_item?.vault_batch_transfer?.to_account?.name || 'another account')"
                                    >
                                        <ArrowUpRight class="h-2.5 w-2.5 text-purple-600 dark:text-purple-400" />
                                        Transferred Out (To: {{ t.forwarding_transfer?.to_account?.name || t.vault_batch_item?.vault_batch_transfer?.to_account?.name || 'Target Account' }})
                                    </span>
                                    <span
                                        v-else-if="t.to_account_id === account.id && (account.is_vault || account.name?.toLowerCase().includes('vault')) && t.status === 'approved' && !t.forwarding_transfer_id && !t.forwarding_transfer && !t.vault_batch_item"
                                        class="mt-1 w-fit inline-flex items-center gap-1 rounded-sm border border-emerald-500/30 bg-emerald-500/10 px-1.5 py-0.5 text-[8px] font-black tracking-wider text-emerald-700 dark:text-emerald-300 uppercase"
                                    >
                                        <CheckCircle2 class="h-2.5 w-2.5 text-emerald-600 dark:text-emerald-400" />
                                        In Vault (Available)
                                    </span>
                                    <span
                                        v-else-if="t.from_account_id === account.id && (account.is_vault || account.name?.toLowerCase().includes('vault')) && t.forwarded_transfers?.length"
                                        class="mt-1 w-fit inline-flex items-center gap-1 rounded-sm border border-blue-500/30 bg-blue-500/10 px-1.5 py-0.5 text-[8px] font-black tracking-wider text-blue-700 dark:text-blue-300 uppercase"
                                    >
                                        <ArrowLeftRight class="h-2.5 w-2.5 text-blue-600 dark:text-blue-400" />
                                        Forwarded Vault Batch ({{ t.forwarded_transfers.length }} {{ t.forwarded_transfers.length === 1 ? 'batch' : 'batches' }})
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-1.5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex min-w-[80px] flex-col text-right"
                                    >
                                        <span
                                            class="text-[9px] font-black tracking-tight uppercase"
                                            :class="
                                                t.from_account_id === account.id
                                                    ? 'text-amber-600'
                                                    : 'text-muted-foreground'
                                            "
                                        >
                                            {{ t.from_account?.name }}
                                        </span>
                                    </div>
                                    <ArrowLeftRight
                                        class="h-3 w-3 shrink-0 text-muted-foreground/30"
                                    />
                                    <div
                                        class="flex min-w-[80px] flex-col text-left"
                                    >
                                        <span
                                            class="text-[9px] font-black tracking-tight uppercase"
                                            :class="
                                                t.to_account_id === account.id
                                                    ? 'text-amber-600'
                                                    : 'text-muted-foreground'
                                            "
                                        >
                                            {{ t.to_account?.name }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-1.5 text-right">
                                <div class="flex flex-col items-end">
                                    <span
                                        class="text-[11px] font-black tracking-tighter tabular-nums"
                                        :class="
                                            t.to_account_id === account.id
                                                ? 'text-emerald-500'
                                                : 'text-rose-500'
                                        "
                                    >
                                        {{
                                            t.to_account_id === account.id
                                                ? '+'
                                                : '-'
                                        }}
                                        {{ formatCurrency(t.amount) }}
                                    </span>
                                    <span
                                        v-if="
                                            t.received_amount &&
                                            t.received_amount !== t.amount
                                        "
                                        class="mt-0.5 border-t border-emerald-500/20 pt-0.5 text-[8px] font-bold text-emerald-600 uppercase"
                                    >
                                        Actual:
                                        {{ formatCurrency(t.received_amount) }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-1.5 text-center">
                                <span
                                    :class="getStatusStyles(t.status)"
                                    class="rounded border px-2 py-0.5 text-[8px] font-black tracking-widest uppercase shadow-sm"
                                >
                                    {{ t.status }}
                                </span>
                            </td>
                            <td class="w-[110px] px-4 py-1.5 text-right">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <template v-if="t.status === 'pending'">
                                        <!-- Actions for the RECEIVER -->
                                        <template
                                            v-if="
                                                t.to_account_id === account.id
                                            "
                                        >
                                            <button
                                                @click="openApprovalModal(t)"
                                                title="Settle Transfer"
                                                class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 shadow-sm transition-all hover:bg-emerald-500 hover:text-white"
                                            >
                                                <Check class="h-3.5 w-3.5" />
                                            </button>
                                            <button
                                                @click="openRejectionModal(t)"
                                                title="Reject Transfer"
                                                class="flex h-7 w-7 items-center justify-center rounded-lg bg-rose-500/10 text-rose-600 shadow-sm transition-all hover:bg-rose-500 hover:text-white"
                                            >
                                                <X class="h-3.5 w-3.5" />
                                            </button>
                                        </template>

                                        <!-- Actions for the SENDER -->
                                        <template
                                            v-if="
                                                t.from_account_id === account.id
                                            "
                                        >
                                            <button
                                                v-if="isB2bTransfer(t)"
                                                @click="checkB2bStatus(t)"
                                                :disabled="checkingTransferId === t.id"
                                                title="Check Safaricom Status"
                                                class="flex h-7 w-7 items-center justify-center rounded-lg border border-indigo-500/20 bg-indigo-500/10 text-indigo-600 shadow-sm transition-all hover:bg-indigo-500 hover:text-white disabled:opacity-50"
                                            >
                                                <Loader2
                                                    v-if="checkingTransferId === t.id"
                                                    class="h-3.5 w-3.5 animate-spin"
                                                />
                                                <RefreshCw
                                                    v-else
                                                    class="h-3.5 w-3.5"
                                                />
                                            </button>
                                            <button
                                                @click="cancelTransfer(t)"
                                                title="Cancel Transfer"
                                                class="flex h-7 w-7 items-center justify-center rounded-lg border border-rose-500/20 bg-rose-500/10 text-rose-600 shadow-sm transition-all hover:bg-rose-500 hover:text-white"
                                            >
                                                <X class="h-3.5 w-3.5" />
                                            </button>
                                        </template>
                                    </template>

                                    <button
                                        @click="openDetailsModal(t)"
                                        title="View Details"
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded border border-border/50 text-muted-foreground shadow-sm transition-all hover:bg-accent/10 hover:text-foreground"
                                    >
                                        <Eye class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr
                            v-if="
                                (!transfers.data && transfers.length === 0) ||
                                (transfers.data && transfers.data.length === 0)
                            "
                        >
                            <td colspan="6" class="p-12 text-center">
                                <div
                                    class="flex flex-col items-center gap-3 opacity-20"
                                >
                                    <ArrowLeftRight class="h-10 w-10" />
                                    <div
                                        class="text-[10px] font-black tracking-[0.3em] uppercase"
                                    >
                                        No movement history matches filter
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer Pagination -->
            <div
                v-if="transfers.links && transfers.links.length > 3"
                class="shrink-0 border-t border-border bg-card/60 px-4 py-3"
            >
                <Pagination
                    :links="transfers.links"
                    :from="transfers.from"
                    :to="transfers.to"
                    :total="transfers.total"
                />
            </div>
        </div>

        <!-- Approval Modal (Redesigned) -->
        <Dialog
            :open="showApprovalModal"
            @update:open="showApprovalModal = $event"
        >
            <DialogContent
                class="overflow-hidden rounded-lg border-none bg-[#FAFAFA] p-0 shadow-2xl transition-all sm:max-w-[800px]"
                overlay-class="bg-black/40"
            >
                <!-- Header -->
                <DialogHeader
                    class="border-b border-[#F1F5F9] bg-white px-5 py-3 pr-12"
                >
                    <DialogDescription class="sr-only"
                        >Confirm receipt of transferred
                        funds.</DialogDescription
                    >
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0 flex-shrink">
                            <DialogTitle
                                class="flex items-center gap-2 text-[15px] font-black tracking-tighter text-[#0F172A] uppercase"
                            >
                                <CheckCircle2
                                    class="h-4 w-4 text-emerald-500"
                                />
                                Confirm Receipt
                            </DialogTitle>

                            <div class="mt-0.5 flex items-center gap-2">
                                <span
                                    class="rounded border border-emerald-100 bg-emerald-50 px-1.5 py-px text-[9px] font-black tracking-widest text-emerald-600 uppercase"
                                >
                                    Receiving Into: {{ account.name }}
                                </span>
                                <span
                                    v-if="selectedTransfer"
                                    class="max-w-[200px] truncate text-[10px] font-bold text-[#64748B]"
                                >
                                    ← From:
                                    {{ selectedTransfer.from_account?.name }}
                                </span>
                            </div>
                        </div>
                    </div>
                </DialogHeader>

                <!-- Body -->
                <div class="flex h-[420px] flex-col md:flex-row">
                    <!-- Left Column -->
                    <div
                        class="custom-scrollbar flex-1 space-y-5 overflow-y-auto border-r border-[#F1F5F9] bg-white p-4"
                    >
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label
                                    class="mb-1 block text-[9px] font-black tracking-widest text-[#475569] uppercase"
                                    >Money to Receive</Label
                                >
                                <div
                                    class="flex h-9 items-center rounded border border-[#E2E8F0] bg-gray-50 px-3 shadow-sm"
                                >
                                    <span
                                        class="mr-2 text-[10px] font-bold text-[#94A3B8]"
                                        >{{ account.currency }}</span
                                    >
                                    <span
                                        class="text-sm font-black text-[#1E293B] tabular-nums"
                                        >{{
                                            formatCurrency(
                                                selectedTransfer?.amount,
                                            )
                                        }}</span
                                    >
                                </div>
                            </div>
                            <div class="space-y-1.5 text-right">
                                <Label
                                    class="mb-1 block text-[9px] font-black tracking-widest text-[#475569] uppercase"
                                    >Ref ID</Label
                                >
                                <div
                                    class="flex h-9 items-center justify-end rounded border border-[#E2E8F0] bg-gray-50 px-3 shadow-sm"
                                >
                                    <span
                                        class="text-[10px] font-black tracking-widest text-amber-600 uppercase"
                                        >{{ selectedTransfer?.reference }}</span
                                    >
                                </div>
                            </div>
                        </div>

                        <Separator class="bg-slate-100" />

                        <!-- Verification Breakdown (Cash Breakdown) -->
                        <div
                            v-if="denominationsToVerify.length"
                            class="space-y-3"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Banknote
                                        class="h-3 w-3 text-emerald-600"
                                    />
                                    <Label
                                        class="text-[9px] leading-none font-black tracking-widest text-emerald-700 uppercase"
                                        >Confirm Cash Details</Label
                                    >
                                </div>
                                <span
                                    class="text-[8px] font-bold tracking-tighter text-slate-400 uppercase"
                                    >Enter actual counts received</span
                                >
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div
                                    v-for="denom in denominationsToVerify"
                                    :key="denom.id"
                                    class="flex animate-in flex-col gap-1.5 rounded border border-border bg-slate-50 p-2 shadow-sm transition-all fade-in hover:border-emerald-200"
                                >
                                    <div
                                        class="flex items-center justify-between px-0.5"
                                    >
                                        <div class="flex flex-col text-left">
                                            <span
                                                class="text-[10px] leading-none font-black text-slate-700"
                                                >{{ denom.value }}</span
                                            >
                                            <span
                                                class="mt-0.5 text-[7px] font-bold tracking-tighter text-slate-400 uppercase"
                                                >Value</span
                                            >
                                        </div>
                                        <div
                                            v-if="denom.sent_qty !== null"
                                            class="text-right"
                                        >
                                            <div
                                                class="text-[9px] font-black text-emerald-600"
                                            >
                                                {{ denom.sent_qty }}
                                            </div>
                                            <span
                                                class="-mt-0.5 block text-[7px] font-bold tracking-tighter text-slate-400 uppercase"
                                                >Sent qty</span
                                            >
                                        </div>
                                    </div>
                                    <div class="relative">
                                        <Input
                                            v-model.number="
                                                approvalForm.denominations[
                                                    String(denom.id)
                                                ]
                                            "
                                            type="number"
                                            min="0"
                                            class="h-8 rounded border border-border bg-white text-center text-xs font-black tabular-nums transition-colors focus:ring-1 focus:ring-emerald-500/30"
                                            placeholder="0"
                                            @input="
                                                (e: any) => {
                                                    const val = Number(
                                                        e.target.value,
                                                    );
                                                    if (val < 0)
                                                        approvalForm.denominations[
                                                            String(denom.id)
                                                        ] = 0;
                                                }
                                            "
                                        />
                                    </div>
                                </div>
                            </div>

                            <Separator class="bg-slate-100" />
                        </div>

                        <div class="space-y-4">
                            <div class="space-y-1.5">
                                <Label
                                    for="received_amount"
                                    class="pl-1 text-[9px] font-black tracking-widest text-[#475569] uppercase"
                                    >Confirmed Amount</Label
                                >
                                <div class="relative">
                                    <span
                                        class="absolute top-1/2 left-2.5 -translate-y-1/2 text-[10px] font-bold text-[#94A3B8]"
                                        >{{ account.currency }}</span
                                    >
                                    <Input
                                        id="received_amount"
                                        v-model="approvalForm.received_amount"
                                        type="number"
                                        step="0.01"
                                        :readonly="
                                            !!selectedTransfer?.denominations
                                                ?.length
                                        "
                                        class="h-10 rounded border border-[#E2E8F0] bg-gray-50/20 pl-10 text-lg font-black text-[#1E293B] tabular-nums shadow-sm focus:ring-emerald-500/20"
                                        :class="
                                            !!selectedTransfer?.denominations
                                                ?.length
                                                ? 'cursor-not-allowed opacity-80'
                                                : ''
                                        "
                                    />
                                </div>
                                <p
                                    v-if="
                                        approvalForm.received_amount <
                                        Number(selectedTransfer?.amount)
                                    "
                                    class="mt-2 flex items-center gap-1.5 text-[9px] font-bold tracking-widest text-rose-500 uppercase"
                                >
                                    <AlertCircle class="h-3 w-3" />
                                    Partial receipt:
                                    {{
                                        formatCurrency(
                                            selectedTransfer?.amount -
                                                approvalForm.received_amount,
                                        )
                                    }}
                                    will return to sender.
                                </p>
                                <p
                                    v-else
                                    class="mt-1 text-[8px] font-bold tracking-widest text-[#94A3B8] uppercase"
                                >
                                    Enter the exact amount you have physically
                                    verified.
                                </p>
                            </div>

                            <div
                                v-if="account.bank_id || account.bank_name"
                                class="space-y-1.5"
                            >
                                <Label
                                    for="transaction_number"
                                    class="text-[9px] font-black tracking-widest text-[#475569] uppercase"
                                    >Transaction Number</Label
                                >
                                <Input
                                    id="transaction_number"
                                    v-model="approvalForm.transaction_number"
                                    placeholder="Enter bank transaction/reference number..."
                                    class="h-9 rounded border-[#E2E8F0] bg-gray-50/10 text-[10px] font-bold shadow-sm focus:ring-emerald-500/20"
                                />
                            </div>

                            <div class="space-y-1.5">
                                <Label
                                    for="notes"
                                    class="text-[9px] font-black tracking-widest text-[#475569] uppercase"
                                    >Reason / Note</Label
                                >
                                <Textarea
                                    id="notes"
                                    v-model="approvalForm.notes"
                                    placeholder="Any notes about this settlement?..."
                                    class="min-h-[100px] resize-none rounded border-[#E2E8F0] bg-gray-50/30 text-[10px] font-bold"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div
                        class="flex h-full w-full flex-col border-t border-[#F1F5F9] bg-[#FAFAFA] p-4 md:w-[240px] md:border-t-0 md:border-l"
                    >
                        <div class="space-y-6">
                            <div class="space-y-1 text-right">
                                <span
                                    class="block text-[8px] font-black tracking-[0.15em] text-emerald-600 uppercase"
                                    >Total to Deposit</span
                                >
                                <div class="flex flex-col items-end text-right">
                                    <div class="flex items-baseline gap-1">
                                        <span
                                            class="text-2xl leading-none font-black text-slate-900 tabular-nums"
                                            >{{
                                                formatCurrency(
                                                    approvalForm.received_amount,
                                                )
                                            }}</span
                                        >
                                    </div>
                                    <span
                                        class="mt-1 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                        >{{ account.currency }}</span
                                    >
                                </div>
                            </div>

                            <div
                                class="space-y-2 border-t border-[#E2E8F0] pt-2"
                            >
                                <div
                                    class="flex items-center justify-between text-[9px] font-black uppercase"
                                >
                                    <span class="text-[#94A3B8]">Status</span>
                                    <span
                                        :class="
                                            approvalForm.received_amount ==
                                            selectedTransfer?.amount
                                                ? 'text-emerald-600'
                                                : 'text-amber-600'
                                        "
                                    >
                                        {{
                                            approvalForm.received_amount ==
                                            selectedTransfer?.amount
                                                ? 'FULL RECEIPT'
                                                : 'PARTIAL'
                                        }}
                                    </span>
                                </div>
                                <div
                                    class="flex items-center justify-between text-[9px] font-black uppercase"
                                >
                                    <span class="text-[#94A3B8]">Sender</span>
                                    <span
                                        class="max-w-[100px] truncate text-[#475569]"
                                        >{{
                                            selectedTransfer?.from_account?.name
                                        }}</span
                                    >
                                </div>
                            </div>

                            <div
                                v-if="
                                    approvalForm.received_amount >
                                    Number(selectedTransfer?.amount)
                                "
                                class="rounded border border-red-100 bg-red-50 p-3 text-center"
                            >
                                <span
                                    class="block text-[8px] font-black tracking-widest text-red-600 uppercase"
                                    >Error</span
                                >
                                <span
                                    class="text-[9px] leading-tight font-bold text-red-700"
                                    >Amount cannot exceed what was sent.</span
                                >
                            </div>
                        </div>

                        <div class="mt-auto space-y-3">
                            <Button
                                @click="submitApproval"
                                :disabled="
                                    approvalForm.processing ||
                                    approvalForm.received_amount <= 0 ||
                                    approvalForm.received_amount >
                                        Number(selectedTransfer?.amount)
                                "
                                class="h-11 w-full bg-slate-900 text-[10px] font-black tracking-widest text-white uppercase shadow-lg transition-all hover:bg-black active:scale-[0.98]"
                            >
                                <CheckCircle2
                                    v-if="!approvalForm.processing"
                                    class="mr-2 h-4 w-4"
                                />
                                {{
                                    approvalForm.processing
                                        ? 'Settling...'
                                        : 'Confirm Receipt'
                                }}
                            </Button>

                            <Button
                                variant="ghost"
                                @click="showApprovalModal = false"
                                :disabled="approvalForm.processing"
                                class="h-9 w-full text-[10px] font-black tracking-widest text-[#64748B] uppercase hover:bg-red-50 hover:text-red-600"
                            >
                                Cancel
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <DialogFooter
                    class="flex h-auto min-h-[50px] items-center justify-between border-t border-gray-100 bg-gray-50/50 px-4 py-2"
                >
                    <div class="flex items-center gap-2">
                        <div class="h-2 w-2 rounded-full bg-emerald-500"></div>
                        <span
                            class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >Secure Settlement</span
                        >
                    </div>
                    <div class="text-[9px] font-bold text-slate-400 italic">
                        Verified funds are immediately added to balance.
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Rejection Modal -->
        <Dialog
            :open="showRejectionModal"
            @update:open="showRejectionModal = $event"
        >
            <DialogContent
                class="overflow-hidden rounded-lg border-none bg-white shadow-2xl sm:max-w-[425px]"
            >
                <DialogHeader class="bg-white px-6 pt-6 pr-12">
                    <DialogTitle
                        class="flex items-center gap-2 text-lg font-black tracking-tight text-slate-900 uppercase"
                    >
                        <XCircle class="h-5 w-5 text-rose-500" />
                        Reject Transfer
                    </DialogTitle>
                    <DialogDescription
                        class="pt-1 text-xs font-bold tracking-widest text-slate-500 uppercase"
                    >
                        Please provide a reason for rejecting this transfer.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 px-6 py-4">
                    <div
                        class="space-y-1 rounded border border-slate-100 bg-slate-50 p-3"
                    >
                        <div
                            class="flex items-center justify-between text-[9px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            <span>Transfer Ref</span>
                            <span class="text-slate-900">{{
                                selectedTransfer?.reference
                            }}</span>
                        </div>
                        <div
                            class="flex items-center justify-between text-[9px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            <span>Amount to Revert</span>
                            <span class="text-rose-600"
                                >{{ account.currency }}
                                {{
                                    formatCurrency(selectedTransfer?.amount)
                                }}</span
                            >
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label
                            for="reject_reason"
                            class="pl-1 text-[9px] font-black tracking-widest text-slate-600 uppercase"
                            >Reason for Rejection</Label
                        >
                        <Textarea
                            id="reject_reason"
                            v-model="rejectionForm.notes"
                            placeholder="Why are you rejecting this transfer?..."
                            class="min-h-[120px] resize-none rounded-md border-slate-200 bg-white text-[10px] font-bold focus:border-rose-500/50 focus:ring-rose-500/20"
                        />
                    </div>
                </div>

                <DialogFooter
                    class="flex flex-col gap-2 bg-slate-50 px-6 py-4 sm:flex-col sm:space-x-0"
                >
                    <Button
                        @click="submitRejection"
                        :disabled="
                            rejectionForm.processing || !rejectionForm.notes
                        "
                        class="h-11 w-full bg-rose-600 text-[10px] font-black tracking-widest text-white uppercase shadow-lg transition-all hover:bg-rose-700 active:scale-[0.98]"
                    >
                        <XCircle
                            v-if="!rejectionForm.processing"
                            class="mr-2 h-4 w-4"
                        />
                        {{
                            rejectionForm.processing
                                ? 'Processing...'
                                : 'Reject & Revert Funds'
                        }}
                    </Button>
                    <Button
                        variant="ghost"
                        @click="showRejectionModal = false"
                        :disabled="rejectionForm.processing"
                        class="h-9 w-full text-[10px] font-black tracking-widest text-slate-500 uppercase hover:text-slate-700"
                    >
                        Keep Pending
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Details Modal -->
        <Dialog
            :open="showDetailsModal"
            @update:open="showDetailsModal = $event"
        >
            <DialogContent
                class="overflow-hidden rounded-lg border-none bg-[#FAFAFA] p-0 shadow-2xl sm:max-w-[800px]"
            >
                <DialogHeader
                    class="border-b border-slate-100 bg-white px-5 py-3 pr-12"
                >
                    <DialogTitle
                        class="flex items-center gap-2 text-[13px] font-black tracking-tight text-slate-900 uppercase"
                    >
                        <ArrowLeftRight class="h-3.5 w-3.5 text-emerald-600" />
                        Transfer Details
                    </DialogTitle>
                    <DialogDescription
                        class="text-[8px] font-bold tracking-[0.15em] text-slate-400 uppercase"
                    >
                        Comprehensive Fund Movement Log
                    </DialogDescription>
                </DialogHeader>

                <div class="flex h-[380px] max-h-[60vh] flex-col md:flex-row">
                    <!-- Left Body (Details) -->
                    <div
                        class="custom-scrollbar flex-1 space-y-4 overflow-y-auto border-r border-slate-100 bg-white p-4"
                    >
                        <div class="grid grid-cols-2 gap-3">
                            <!-- Sender -->
                            <div
                                class="space-y-1.5 overflow-hidden rounded-lg border border-slate-100 bg-slate-50/50 p-2.5 text-ellipsis"
                            >
                                <div class="flex items-center gap-1.5">
                                    <ArrowUpRight
                                        class="h-2.5 w-2.5 text-amber-600"
                                    />
                                    <span
                                        class="text-[7.5px] leading-none font-black tracking-widest text-slate-400 uppercase"
                                        >Source Origin</span
                                    >
                                </div>
                                <div class="space-y-0.5">
                                    <span
                                        class="block truncate text-[9px] font-black tracking-tight text-slate-900 uppercase"
                                        >{{
                                            selectedTransfer?.from_account?.name
                                        }}</span
                                    >
                                    <div
                                        class="flex items-center gap-1 opacity-60"
                                    >
                                        <User class="h-2 w-2" />
                                        <span
                                            class="text-[7.5px] font-black tracking-tighter uppercase italic"
                                            >By
                                            {{
                                                selectedTransfer?.performer
                                                    ?.name
                                            }}</span
                                        >
                                    </div>
                                </div>
                            </div>

                            <!-- Receiver -->
                            <div
                                class="space-y-1.5 overflow-hidden rounded-lg border border-slate-100 bg-slate-50/50 p-2.5 text-ellipsis"
                            >
                                <div class="flex items-center gap-1.5">
                                    <ArrowDownLeft
                                        class="h-2.5 w-2.5 text-emerald-600"
                                    />
                                    <span
                                        class="text-[7.5px] leading-none font-black tracking-widest text-slate-400 uppercase"
                                        >Destination Target</span
                                    >
                                </div>
                                <div class="space-y-0.5">
                                    <span
                                        class="block truncate text-[9px] font-black tracking-tight text-slate-900 uppercase"
                                        >{{
                                            selectedTransfer?.to_account?.name
                                        }}</span
                                    >
                                    <div
                                        v-if="selectedTransfer?.approver"
                                        class="flex items-center gap-1 opacity-60"
                                    >
                                        <User class="h-2 w-2" />
                                        <span
                                            class="text-[7.5px] font-black tracking-tighter uppercase italic"
                                            >By
                                            {{
                                                selectedTransfer?.approver?.name
                                            }}</span
                                        >
                                    </div>
                                    <div
                                        v-else
                                        class="text-[7.5px] font-bold text-slate-300 uppercase italic"
                                    >
                                        Awaiting Settlement
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Denominations Breakdown -->
                        <div
                            v-if="selectedTransfer?.denominations?.length"
                            class="space-y-3"
                        >
                            <div class="flex items-center gap-2">
                                <Banknote class="h-3 w-3 text-emerald-600" />
                                <h4
                                    class="text-[9px] leading-none font-black tracking-widest text-slate-800 uppercase"
                                >
                                    Denomination Audit
                                </h4>
                                <span
                                    class="rounded bg-slate-100 px-1.5 py-0.5 text-[7px] font-black text-slate-500 uppercase"
                                    >Chain of Custody</span
                                >
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div
                                    v-for="td in [
                                        ...selectedTransfer.denominations,
                                    ].sort(
                                        (a, b) =>
                                            Number(b.denomination?.value) -
                                            Number(a.denomination?.value),
                                    )"
                                    :key="td.id"
                                    class="flex flex-col gap-1 rounded border border-slate-100 bg-slate-50/50 p-1 px-2"
                                >
                                    <div
                                        class="flex items-center justify-between border-b border-slate-200/50 pb-1"
                                    >
                                        <div class="flex flex-col">
                                            <span
                                                class="text-[10px] leading-none font-black text-slate-700"
                                                >{{
                                                    td.denomination?.value
                                                }}</span
                                            >
                                            <span
                                                class="mt-1 text-[7px] leading-none font-bold tracking-tighter text-slate-400 uppercase"
                                                >Value</span
                                            >
                                        </div>
                                        <div
                                            class="rounded-full border border-slate-100 bg-white px-1 py-0.5 text-[8px] font-black text-slate-400 tabular-nums"
                                        >
                                            {{
                                                td.denomination?.currency ||
                                                account.currency
                                            }}
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div
                                            class="flex flex-col items-center rounded bg-white/60 p-0.5"
                                        >
                                            <span
                                                class="text-[9px] font-black"
                                                :class="
                                                    td.quantity > 0
                                                        ? 'text-slate-700'
                                                        : 'text-slate-300'
                                                "
                                                >{{ td.quantity }}</span
                                            >
                                            <span
                                                class="text-[7px] font-black tracking-tighter text-slate-400 uppercase"
                                                >Sent</span
                                            >
                                        </div>
                                        <div
                                            class="flex flex-col items-center rounded p-0.5"
                                            :class="
                                                td.received_quantity !== null
                                                    ? 'bg-emerald-50/60'
                                                    : 'bg-orange-50/60'
                                            "
                                        >
                                            <span
                                                class="text-[9px] font-black"
                                                :class="
                                                    td.received_quantity !==
                                                    null
                                                        ? 'text-emerald-600'
                                                        : 'text-orange-500'
                                                "
                                            >
                                                {{
                                                    td.received_quantity !==
                                                    null
                                                        ? td.received_quantity
                                                        : '---'
                                                }}
                                            </span>
                                            <span
                                                class="text-[7px] font-black tracking-tighter uppercase"
                                                :class="
                                                    td.received_quantity !==
                                                    null
                                                        ? 'text-emerald-500/70'
                                                        : 'text-orange-400'
                                                "
                                                >Recv</span
                                            >
                                        </div>
                                    </div>
                                    <!-- Discrepancy indicator -->
                                    <div
                                        v-if="
                                            td.received_quantity !== null &&
                                            Number(td.received_quantity) !==
                                                Number(td.quantity)
                                        "
                                        class="mt-0.5 text-center"
                                    >
                                        <span
                                            class="rounded border border-rose-100 bg-rose-50 px-1 py-0.5 text-[7px] font-black tracking-tighter text-rose-600 uppercase"
                                        >
                                            Diff:
                                            {{
                                                Number(td.received_quantity) -
                                                    Number(td.quantity) >
                                                0
                                                    ? '+'
                                                    : ''
                                            }}{{
                                                Number(td.received_quantity) -
                                                Number(td.quantity)
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Vault Forwarding Status Block -->
                        <div v-if="selectedTransfer?.forwarding_transfer || selectedTransfer?.vault_batch_item || (selectedTransfer?.to_account_id === account.id && account.is_vault && selectedTransfer?.status === 'approved')" class="space-y-2">
                            <div class="flex items-center gap-1.5 px-0.5">
                                <ArrowLeftRight class="h-2.5 w-2.5 text-purple-600" />
                                <span class="text-[7.5px] leading-none font-black tracking-widest text-slate-400 uppercase">Vault Batch Lineage</span>
                            </div>
                            <div v-if="selectedTransfer?.forwarding_transfer || selectedTransfer?.vault_batch_item" class="rounded-lg border border-purple-200 bg-purple-50/50 p-3 space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-[8.5px] font-black text-purple-800 uppercase flex items-center gap-1">
                                        <ArrowUpRight class="h-3 w-3 text-purple-600" />
                                        Transferred Out of Vault
                                    </span>
                                    <span class="text-[8px] font-bold text-purple-600 uppercase">
                                        {{ selectedTransfer.forwarded_at || selectedTransfer.vault_batch_item?.created_at ? formatDate(selectedTransfer.forwarded_at || selectedTransfer.vault_batch_item?.created_at) : '' }}
                                    </span>
                                </div>
                                <p class="text-[9px] font-bold text-slate-700 uppercase mt-0.5">
                                    Sent to Destination: <span class="font-black text-purple-900">{{ selectedTransfer.forwarding_transfer?.to_account?.name || selectedTransfer.vault_batch_item?.vault_batch_transfer?.to_account?.name || 'Target Account' }}</span>
                                </p>
                                <p class="text-[8px] font-mono text-purple-600 uppercase">
                                    Forwarding Ref: #{{ selectedTransfer.forwarding_transfer?.reference || selectedTransfer.vault_batch_item?.vault_batch_transfer?.transfer_number }}
                                </p>
                            </div>
                            <div v-else-if="selectedTransfer?.to_account_id === account.id && account.is_vault && selectedTransfer?.status === 'approved' && !selectedTransfer?.forwarding_transfer && !selectedTransfer?.vault_batch_item" class="rounded-lg border border-emerald-200 bg-emerald-50/50 p-3 space-y-1">
                                <div class="flex items-center gap-1.5 text-emerald-800">
                                    <CheckCircle2 class="h-3.5 w-3.5 text-emerald-600" />
                                    <span class="text-[9px] font-black uppercase">Currently in Vault (Available for Forwarding)</span>
                                </div>
                                <p class="text-[8px] text-emerald-600 uppercase font-bold">
                                    This batch is unspent in the vault and available to be selected for outgoing transfers.
                                </p>
                            </div>
                        </div>

                        <!-- Forwarded Batches Breakdown (For Outward Vault Transfers) -->
                        <div v-if="selectedTransfer?.forwarded_transfers?.length" class="space-y-2">
                            <div class="flex items-center gap-1.5 px-0.5">
                                <ArrowLeftRight class="h-2.5 w-2.5 text-blue-600" />
                                <span class="text-[7.5px] leading-none font-black tracking-widest text-slate-400 uppercase">Bundled Vault Source Batches</span>
                            </div>
                            <div class="rounded-lg border border-blue-200 bg-blue-50/40 p-3 space-y-2">
                                <div v-for="ft in selectedTransfer.forwarded_transfers" :key="ft.id" class="flex items-center justify-between border-b border-blue-100/60 pb-1.5 last:border-b-0 last:pb-0 text-[8.5px]">
                                    <div class="flex flex-col">
                                        <span class="font-black text-slate-800 uppercase">From: {{ ft.from_account?.name || 'Source Account' }}</span>
                                        <span class="text-[7.5px] text-blue-600 font-mono">Ref: #{{ ft.reference }}</span>
                                    </div>
                                    <span class="font-black text-emerald-700 tabular-nums">KES {{ formatCurrency(ft.received_amount || ft.amount) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Notes/Audit Section -->
                        <div class="space-y-2">
                            <div class="flex items-center gap-1.5 px-0.5">
                                <MessageSquare
                                    class="h-2.5 w-2.5 text-slate-400"
                                />
                                <span
                                    class="text-[7.5px] leading-none font-black tracking-widest text-slate-400 uppercase"
                                    >Audit Trail & Observations</span
                                >
                            </div>
                            <div
                                class="rounded-lg border border-slate-100 bg-slate-50 p-3"
                            >
                                <div class="mb-2 flex items-center gap-2">
                                    <Clock class="h-2 w-2 text-slate-300" />
                                    <span
                                        class="text-[7.5px] font-black tracking-tighter text-slate-500 uppercase"
                                        >Timeline Entry:
                                        {{
                                            formatDate(
                                                selectedTransfer?.created_at,
                                            )
                                        }}</span
                                    >
                                </div>
                                <Separator class="mb-2 opacity-20" />
                                <div
                                    v-if="selectedTransfer?.notes"
                                    class="text-[9px] leading-[1.5] font-black whitespace-pre-line text-slate-600 uppercase italic opacity-90"
                                >
                                    {{ selectedTransfer.notes }}
                                </div>
                                <div
                                    v-else
                                    class="text-[8.5px] font-bold text-slate-400 italic"
                                >
                                    No additional notes provided for this
                                    transaction.
                                </div>
                            </div>
                        </div>

                        <!-- M-Pesa B2B Gateway Callback & Raw Response -->
                        <div
                            v-if="
                                selectedTransfer?.mpesa_transaction ||
                                (selectedTransfer?.reference &&
                                    selectedTransfer.reference.startsWith('B2B-')) ||
                                selectedTransfer?.transaction_number ||
                                isB2bTransfer(selectedTransfer)
                            "
                            class="space-y-2"
                        >
                            <div class="flex items-center justify-between px-0.5">
                                <div class="flex items-center gap-1.5">
                                    <ArrowLeftRight class="h-2.5 w-2.5 text-emerald-600" />
                                    <span
                                        class="text-[7.5px] leading-none font-black tracking-widest text-slate-500 uppercase"
                                        >M-Pesa B2B Gateway Response</span
                                    >
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <button
                                        v-if="selectedTransfer?.status === 'pending'"
                                        type="button"
                                        @click="checkB2bStatus(selectedTransfer)"
                                        :disabled="checkingTransferId === selectedTransfer?.id"
                                        class="inline-flex items-center gap-1 rounded border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[7.5px] font-black uppercase text-indigo-700 hover:bg-indigo-100 transition-colors disabled:opacity-50"
                                    >
                                        <Loader2
                                            v-if="checkingTransferId === selectedTransfer?.id"
                                            class="h-2.5 w-2.5 animate-spin"
                                        />
                                        <RefreshCw v-else class="h-2.5 w-2.5" />
                                        <span>{{ checkingTransferId === selectedTransfer?.id ? 'Checking...' : 'Check Status' }}</span>
                                    </button>
                                    <span
                                        v-if="selectedTransfer?.mpesa_transaction?.status || selectedTransfer?.status"
                                        class="rounded-full px-1.5 py-0.5 text-[7px] font-black uppercase"
                                        :class="
                                            (selectedTransfer?.mpesa_transaction?.status === 'completed' || selectedTransfer?.status === 'approved')
                                                ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                                : selectedTransfer?.status === 'pending'
                                                ? 'bg-amber-50 text-amber-700 border border-amber-200'
                                                : 'bg-rose-50 text-rose-700 border border-rose-200'
                                        "
                                    >
                                        {{ selectedTransfer?.mpesa_transaction?.status || selectedTransfer?.status }}
                                    </span>
                                </div>
                            </div>

                            <div
                                v-if="pollingStatusMessage"
                                class="rounded border border-indigo-200 bg-indigo-50/80 p-2 text-[8px] font-bold text-indigo-700 flex items-center gap-1.5"
                            >
                                <Loader2
                                    v-if="checkingTransferId"
                                    class="h-3 w-3 animate-spin shrink-0"
                                />
                                <AlertCircle v-else class="h-3 w-3 shrink-0" />
                                <span>{{ pollingStatusMessage }}</span>
                            </div>

                            <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3 space-y-2.5">
                                <div class="grid grid-cols-2 gap-2 text-[8.5px]">
                                    <div class="rounded bg-white p-2 border border-slate-100 space-y-0.5">
                                        <span class="text-[7px] font-bold text-slate-400 uppercase">M-Pesa Receipt (Tx ID)</span>
                                        <div class="flex items-center justify-between">
                                            <span class="font-mono font-black text-slate-800">
                                                {{ selectedTransfer?.mpesa_transaction?.transaction_id || selectedTransfer?.transaction_number || 'Pending' }}
                                            </span>
                                            <button
                                                v-if="selectedTransfer?.mpesa_transaction?.transaction_id || selectedTransfer?.transaction_number"
                                                @click="copyMpesaJson(selectedTransfer?.mpesa_transaction?.transaction_id || selectedTransfer?.transaction_number)"
                                                class="text-slate-400 hover:text-slate-600 transition-colors"
                                                title="Copy Receipt"
                                            >
                                                <Check v-if="copiedMpesaJson" class="h-2.5 w-2.5 text-emerald-600" />
                                                <Copy v-else class="h-2.5 w-2.5" />
                                            </button>
                                        </div>
                                    </div>

                                    <div class="rounded bg-white p-2 border border-slate-100 space-y-0.5">
                                        <span class="text-[7px] font-bold text-slate-400 uppercase">Beneficiary Name</span>
                                        <span class="block truncate font-black text-slate-800 uppercase">
                                            {{ selectedTransfer?.mpesa_transaction?.mpesa_last_name || selectedTransfer?.to_account?.name || 'Bank Account' }}
                                        </span>
                                    </div>
                                </div>

                                <div v-if="selectedTransfer?.mpesa_transaction?.result_desc" class="rounded bg-white p-2 border border-slate-100 text-[8px] space-y-0.5">
                                    <span class="text-[7px] font-bold text-slate-400 uppercase">Gateway Result Description</span>
                                    <p class="font-bold text-slate-700 italic">
                                        {{ selectedTransfer.mpesa_transaction.result_desc }}
                                    </p>
                                </div>

                                <!-- Collapsible Raw Payload -->
                                <div v-if="selectedTransfer?.mpesa_transaction?.raw_payload" class="space-y-1.5 pt-1">
                                    <div class="flex items-center justify-between">
                                        <button
                                            type="button"
                                            @click="showMpesaRawPayload = !showMpesaRawPayload"
                                            class="flex items-center gap-1 text-[7.5px] font-black uppercase text-slate-600 hover:text-slate-900 transition-colors"
                                        >
                                            <Code2 class="h-2.5 w-2.5 text-slate-500" />
                                            <span>{{ showMpesaRawPayload ? 'Hide Raw Callback Data' : 'View Full Safaricom Callback JSON' }}</span>
                                            <ChevronUp v-if="showMpesaRawPayload" class="h-2.5 w-2.5" />
                                            <ChevronDown v-else class="h-2.5 w-2.5" />
                                        </button>

                                        <button
                                            v-if="showMpesaRawPayload"
                                            type="button"
                                            @click="copyMpesaJson(selectedTransfer.mpesa_transaction.raw_payload)"
                                            class="inline-flex items-center gap-1 rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[7px] font-black uppercase text-slate-600 hover:bg-slate-50 active:scale-95"
                                        >
                                            <Check v-if="copiedMpesaJson" class="h-2 w-2 text-emerald-600" />
                                            <Copy v-else class="h-2 w-2" />
                                            <span>{{ copiedMpesaJson ? 'Copied' : 'Copy JSON' }}</span>
                                        </button>
                                    </div>

                                    <div
                                        v-if="showMpesaRawPayload"
                                        class="max-h-[160px] overflow-auto rounded-md border border-slate-800 bg-slate-950 p-2.5 shadow-inner"
                                    >
                                        <pre class="font-mono text-[9px] leading-tight text-emerald-400 select-all whitespace-pre-wrap">{{ JSON.stringify(selectedTransfer.mpesa_transaction.raw_payload, null, 2) }}</pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Sidebar (Financial Summary) -->
                    <div
                        class="flex h-full w-full flex-col border-t border-slate-100 bg-[#FAFAFA] p-4 md:w-[240px] md:border-t-0"
                    >
                        <div class="space-y-5">
                            <!-- Amount Breakdown -->
                            <div class="space-y-4">
                                <div class="space-y-1 text-right">
                                    <span
                                        class="block text-[7.5px] font-black tracking-widest text-slate-500 uppercase"
                                        >Principal Amount</span
                                    >
                                    <div class="flex flex-col items-end">
                                        <span
                                            class="text-lg leading-none font-black tracking-tighter text-slate-900 tabular-nums"
                                            >{{
                                                formatCurrency(
                                                    selectedTransfer?.amount,
                                                )
                                            }}</span
                                        >
                                        <span
                                            class="mt-1 text-[8px] font-black tracking-widest text-slate-400 uppercase"
                                            >{{ account.currency }}</span
                                        >
                                    </div>
                                </div>

                                <Separator class="opacity-40" />

                                <div
                                    v-if="
                                        selectedTransfer?.status === 'approved'
                                    "
                                    class="space-y-1 text-right"
                                >
                                    <span
                                        class="block text-[7.5px] font-black tracking-widest text-emerald-600 uppercase"
                                        >Settled Amount</span
                                    >
                                    <div class="flex flex-col items-end">
                                        <span
                                            class="text-lg leading-none font-black tracking-tighter text-emerald-600 tabular-nums"
                                            >{{
                                                formatCurrency(
                                                    selectedTransfer?.received_amount,
                                                )
                                            }}</span
                                        >
                                        <div
                                            v-if="
                                                Number(
                                                    selectedTransfer?.received_amount,
                                                ) <
                                                Number(selectedTransfer?.amount)
                                            "
                                            class="mt-0.5 text-[6.5px] font-black tracking-tighter text-rose-500 uppercase italic"
                                        >
                                            -{{
                                                formatCurrency(
                                                    Number(
                                                        selectedTransfer?.amount,
                                                    ) -
                                                        Number(
                                                            selectedTransfer?.received_amount,
                                                        ),
                                                )
                                            }}
                                            REVERTED
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="selectedTransfer?.transaction_number"
                                    class="rounded border border-emerald-100/50 bg-emerald-50/50 p-2.5"
                                >
                                    <span
                                        class="mb-1 block text-[7px] leading-none font-black tracking-widest text-emerald-600 uppercase"
                                        >Bank Ref #</span
                                    >
                                    <span
                                        class="text-[8.5px] leading-none font-black tracking-tight break-all text-emerald-800 uppercase"
                                        >#{{
                                            selectedTransfer?.transaction_number
                                        }}</span
                                    >
                                </div>
                            </div>

                            <!-- Meta Info -->
                            <div
                                class="space-y-1.5 border-t border-slate-200 pt-3"
                            >
                                <div
                                    class="flex items-center justify-between text-[7.5px] font-black tracking-tight uppercase"
                                >
                                    <span class="text-slate-400">STATUS</span>
                                    <span
                                        :class="
                                            getStatusStyles(
                                                selectedTransfer?.status ||
                                                    'pending',
                                            )
                                        "
                                        class="origin-right scale-90 rounded-sm border px-1.5 py-0.5 shadow-sm"
                                    >
                                        {{ selectedTransfer?.status }}
                                    </span>
                                </div>
                                <div
                                    class="flex items-center justify-between text-[7.5px] font-black tracking-tight uppercase"
                                >
                                    <span class="text-slate-400">REF CODE</span>
                                    <span class="text-slate-600 tabular-nums">{{
                                        selectedTransfer?.reference
                                    }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-auto pt-4">
                            <Button
                                variant="outline"
                                @click="showDetailsModal = false"
                                class="h-8 w-full border-slate-200 bg-white text-[8.5px] font-black tracking-widest text-slate-400 uppercase shadow-sm hover:text-slate-900"
                            >
                                Close View
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Footer Audit -->
                <div
                    class="flex h-auto min-h-[40px] items-center justify-between border-t border-slate-50 bg-white px-5 py-2"
                >
                    <div class="flex items-center gap-2">
                        <div
                            class="h-1.5 w-1.5 rounded-full"
                            :class="
                                selectedTransfer?.status === 'approved'
                                    ? 'bg-emerald-500'
                                    : 'bg-amber-500'
                            "
                        ></div>
                        <span
                            class="text-[8px] font-black tracking-[0.2em] text-slate-400 uppercase"
                            >Transaction Verified and Logged</span
                        >
                    </div>
                    <div
                        class="text-[8px] font-bold tracking-tight text-slate-300 italic"
                    >
                        Security Hash:
                        {{
                            selectedTransfer?.id
                                ?.toString(16)
                                .padStart(8, '0')
                                .toUpperCase()
                        }}
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    height: 4px;
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
