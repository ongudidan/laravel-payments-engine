<script setup lang="ts">
import Pagination from '../../../../Components/Pagination.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import TransactionMappingModal from '@/components/Mpesa/TransactionMappingModal.vue';
import TransactionSyncModal from '@/components/Mpesa/TransactionSyncModal.vue';
import LiveBalanceModal from '@/components/Mpesa/LiveBalanceModal.vue';
import { Badge } from '@/components/ui/badge';
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
import { router, useForm } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import {
    Activity,
    AlertCircle,
    Check,
    CheckCircle2,
    Copy,
    DownloadCloud,
    FileSpreadsheet,
    Loader2,
    Scale,
    Search,
    ShieldAlert,
    Sparkles,
    Wallet,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    paymentAccount: any;
    transactions: {
        data: any[];
        links: any[];
        from: number;
        to: number;
        total: number;
        per_page?: number;
    };
    summary: {
        total_imported: number;
        matched_local: number;
        not_recorded: number;
    };
    assignedCustomer?: any;
    companies?: any[];
    branches?: any[];
    stats: any;
    filters: {
        search: string;
        status: string;
        date: string;
        per_page?: number | string;
    };
    pullCallbackUrl?: string;
    nominatedNumber?: string;
    lastLiveBalance?: any;
    canMap?: boolean;
}>();

const emit = defineEmits<{
    (e: 'switch-tab', tab: string): void;
}>();

// Search & Filter State
const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || 'all');
const dateFilter = ref(props.filters.date || '');
const perPage = ref<number>(Number(props.filters.per_page) || 25);

const statusOptions = [
    { id: 'all', label: 'All Statuses' },
    { id: 'unmatched', label: 'Not Recorded' },
    { id: 'matched', label: 'Recorded in ERP' },
    { id: 'settled', label: 'Settled' },
];

const perPageOptions = [
    { id: 10, label: '10 / PAGE' },
    { id: 25, label: '25 / PAGE' },
    { id: 50, label: '50 / PAGE' },
    { id: 100, label: '100 / PAGE' },
];

const applyFilters = debounce(() => {
    router.get(
        `/organization/financial-profiles/payment-accounts/${props.paymentAccount.id}/pull-transactions`,
        {
            tab: 'transactions',
            search: search.value || undefined,
            status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
            date: dateFilter.value || undefined,
            per_page: perPage.value !== 25 ? perPage.value : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true, only: ['transactions', 'stats', 'summary', 'filters'] }
    );
}, 350);

watch([search, statusFilter, dateFilter, perPage], () => {
    applyFilters();
});

// Format helpers
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

const copiedId = ref<string | null>(null);
const copyText = (text: string) => {
    if (!text || text === '---') return;
    navigator.clipboard.writeText(text);
    copiedId.value = text;
    setTimeout(() => {
        if (copiedId.value === text) copiedId.value = null;
    }, 2000);
};

// Live Balance Modal state
const isLiveBalanceModalOpen = ref(false);
const liveBalanceData = ref<any>(props.lastLiveBalance || null);

const handleBalanceUpdated = (newData: any) => {
    liveBalanceData.value = newData;
};

// Date Presets Logic
const toDatetimeLocal = (date: Date) => {
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

const queryForm = useForm({
    start_date: toDatetimeLocal(new Date(Date.now() - 2 * 60 * 60 * 1000)),
    end_date: toDatetimeLocal(new Date()),
    offset_value: 0,
});

const isStartDateOver48h = computed(() => {
    if (!queryForm.start_date) return false;
    const startMs = new Date(queryForm.start_date).getTime();
    const fortyEightHoursAgo = Date.now() - 48 * 60 * 60 * 1000;
    return startMs < fortyEightHoursAgo;
});

const activePreset = ref<'2h' | '6h' | 'today' | '24h' | '48h' | 'custom'>('2h');

const selectPreset = (preset: '2h' | '6h' | 'today' | '24h' | '48h') => {
    activePreset.value = preset;
    const now = new Date();
    queryForm.end_date = toDatetimeLocal(now);

    if (preset === '2h') {
        queryForm.start_date = toDatetimeLocal(new Date(now.getTime() - 2 * 60 * 60 * 1000));
    } else if (preset === '6h') {
        queryForm.start_date = toDatetimeLocal(new Date(now.getTime() - 6 * 60 * 60 * 1000));
    } else if (preset === 'today') {
        const midnight = new Date(now);
        midnight.setHours(0, 0, 0, 0);
        queryForm.start_date = toDatetimeLocal(midnight);
    } else if (preset === '24h') {
        queryForm.start_date = toDatetimeLocal(new Date(now.getTime() - 24 * 60 * 60 * 1000));
    } else if (preset === '48h') {
        queryForm.start_date = toDatetimeLocal(new Date(now.getTime() - 47.9 * 60 * 60 * 1000));
    }
};

const submitQuery = () => {
    queryForm.post(
        `/organization/financial-profiles/payment-accounts/${props.paymentAccount.id}/pull-transactions/query`,
        {
            preserveScroll: true,
            onSuccess: () => {
                emit('switch-tab', 'requests');
            },
        }
    );
};

// Register Pull URL form and Modal
const registerForm = useForm({
    nominated_number: props.nominatedNumber || '',
});
const registerModalOpen = ref(false);

const openRegisterModal = () => {
    registerForm.nominated_number = props.nominatedNumber || '';
    registerModalOpen.value = true;
};

const confirmRegisterPull = () => {
    registerForm.post(
        `/organization/financial-profiles/payment-accounts/${props.paymentAccount.id}/pull-transactions/register`,
        {
            preserveScroll: true,
            onFinish: () => {
                registerModalOpen.value = false;
            },
        }
    );
};

// Reconciliation modals & functions
const paybillOptions = computed(() => [
    {
        id: props.paymentAccount.id,
        label: `${props.paymentAccount.name} | ${props.paymentAccount.mpesa_config?.shortcode || props.paymentAccount.account_number}`,
    },
]);

const syncModalOpen = ref(false);
const syncModalReceiptNo = ref<string>('');

const openSyncModal = (receiptNo?: string) => {
    syncModalReceiptNo.value = receiptNo || '';
    syncModalOpen.value = true;
};

const syncImportedTransaction = (tx: any) => {
    openSyncModal(tx.receipt_no || tx.trans_id);
};

const handleSyncSuccess = () => {
    router.reload({ preserveScroll: true });
};

const recordConfirmModalOpen = ref(false);
const transactionToRecord = ref<any>(null);
const recordingLoading = ref(false);

const confirmRecordToErp = (tx: any) => {
    transactionToRecord.value = tx;
    recordConfirmModalOpen.value = true;
};

const submitRecordToErp = () => {
    if (!transactionToRecord.value?.id) return;
    router.post(
        `/organization/financial-profiles/payment-accounts/${props.paymentAccount.id}/pull-transactions/${transactionToRecord.value.id}/reconcile`,
        {},
        {
            preserveScroll: true,
            onBefore: () => {
                recordingLoading.value = true;
            },
            onSuccess: () => {
                recordConfirmModalOpen.value = false;
                transactionToRecord.value = null;
            },
            onError: (errors: any) => {
                console.error('Error settling transaction into ERP:', errors);
            },
            onFinish: () => {
                recordingLoading.value = false;
            },
        },
    );
};

// Batch Record All Missing into ERP
const recordAllModalOpen = ref(false);
const recordingAllLoading = ref(false);

const confirmRecordAll = () => {
    recordAllModalOpen.value = true;
};

const submitRecordAll = () => {
    router.post(
        `/organization/financial-profiles/payment-accounts/${props.paymentAccount.id}/pull-transactions/reconcile-all`,
        {},
        {
            preserveScroll: true,
            onBefore: () => {
                recordingAllLoading.value = true;
            },
            onSuccess: () => {
                recordAllModalOpen.value = false;
            },
            onError: (errors: any) => {
                console.error('Error settling all transactions into ERP:', errors);
            },
            onFinish: () => {
                recordingAllLoading.value = false;
            },
        },
    );
};

const isTxMapped = (tx: any): boolean => {
    return !!(tx.local_transaction?.customer_id || tx.local_transaction?.sale_id || tx.mapped_customer || tx.mapped_sale || tx.matched_customer || tx.matched_sale);
};

const getEffectiveCustomer = (tx: any) => {
    return tx.local_transaction?.customer || tx.mapped_customer || tx.matched_customer || tx.matched_mpesa_transaction?.customer || tx.matched_mpesa_transaction?.sale?.customer || null;
};

const getEffectiveSale = (tx: any) => {
    return tx.local_transaction?.sale || tx.mapped_sale || tx.matched_sale || tx.matched_mpesa_transaction?.sale || null;
};

// Map Modal state
const mapModalOpen = ref(false);
const mappingTransaction = ref<any>(null);
const mappingLoading = ref(false);
const customerSearchResults = ref<any[]>([]);
const searchingCustomers = ref(false);
const selectedCustomer = ref<any>(null);
const searchCustomerQuery = ref('');
let customerSearchTimeout: any = null;

const openMapModal = (tx: any) => {
    const mpesaTx = tx.matched_mpesa_transaction || tx.local_transaction;
    if (!mpesaTx) return;

    mappingTransaction.value = mpesaTx;
    selectedCustomer.value = getEffectiveCustomer(tx) || null;
    searchCustomerQuery.value = selectedCustomer.value ? (selectedCustomer.value.company_name || selectedCustomer.value.contact_person_names || '') : '';
    customerSearchResults.value = [];
    mapModalOpen.value = true;
};

const closeMapModal = () => {
    mapModalOpen.value = false;
    mappingTransaction.value = null;
    selectedCustomer.value = null;
    searchCustomerQuery.value = '';
    customerSearchResults.value = [];
};

const handleCustomerSearch = (payload: any) => {
    clearTimeout(customerSearchTimeout);
    selectedCustomer.value = null;

    let queryStr = '';
    let companyId = '';
    let branchId = '';

    if (typeof payload === 'object' && payload !== null) {
        queryStr = payload.query || '';
        companyId = payload.company_id || '';
        branchId = payload.branch_id || '';
    } else {
        queryStr = String(payload || '');
    }

    searchCustomerQuery.value = queryStr;

    if (queryStr.length < 2 && !companyId && !branchId) {
        customerSearchResults.value = [];
        searchingCustomers.value = false;
        return;
    }

    searchingCustomers.value = true;
    customerSearchTimeout = setTimeout(async () => {
        try {
            const params = new URLSearchParams();
            if (queryStr) params.append('query', queryStr);
            if (companyId) params.append('company_id', String(companyId));
            if (branchId) params.append('branch_id', String(branchId));

            const response = await fetch(`/api/mpesa/search-customers?${params.toString()}`);
            if (response.ok) {
                customerSearchResults.value = await response.json();
            }
        } catch (error) {
            console.error('Error searching customers:', error);
        } finally {
            searchingCustomers.value = false;
        }
    }, 400);
};

const selectCustomer = (customer: any) => {
    selectedCustomer.value = customer;
    searchCustomerQuery.value = `${customer.contact_person_names} (${customer.company_name})`;
    customerSearchResults.value = [];
};

const submitMapping = () => {
    if (!mappingTransaction.value || !selectedCustomer.value?.id) return;

    router.post(
        `/mpesa/transactions/${mappingTransaction.value.id}/map`,
        {
            customer_id: selectedCustomer.value.id,
        },
        {
            onBefore: () => {
                mappingLoading.value = true;
            },
            onSuccess: () => {
                closeMapModal();
                router.reload({ preserveScroll: true });
            },
            onError: (errors) => {
                console.error('Error mapping transaction:', errors);
            },
            onFinish: () => {
                mappingLoading.value = false;
            },
        },
    );
};

// Bulk Auto Map
const bulkAutoMapLoading = ref(false);
const bulkAutoMap = () => {
    if (
        !confirm(
            `Are you sure you want to automatically map all unmapped synced transactions for this Paybill to ${props.assignedCustomer?.company_name}?`,
        )
    )
        return;

    router.post(
        '/mpesa/transactions/bulk-auto-map',
        {
            payment_account_id: props.paymentAccount.id,
        },
        {
            onBefore: () => {
                bulkAutoMapLoading.value = true;
            },
            onSuccess: () => {
                router.reload({ preserveScroll: true });
            },
            onError: (err: any) => {
                alert(
                    'Error during bulk mapping: ' +
                        (err.response?.data?.message || 'Check logs for details.'),
                );
            },
            onFinish: () => {
                bulkAutoMapLoading.value = false;
            },
        },
    );
};

const isTransactionRecorded = (tx: any): boolean => {
    if (tx.reconciliation_status === 'matched' || tx.reconciliation_status === 'settled') return true;
    if (tx.matched_mpesa_transaction_id) return true;
    if (tx.matched_mpesa_transaction && tx.matched_mpesa_transaction.status === 'completed') return true;
    if (tx.local_transaction && tx.local_transaction.status === 'completed') return true;
    return false;
};
</script>

<template>
    <div class="space-y-2">
        <!-- Top Toolbar Strip: Live Balance, Auto-Map, Register Pull, Pull Query Drawer -->
        <div class="rounded-lg border border-border/50 bg-card/40 p-2.5 shadow-xs backdrop-blur-md">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <!-- Left: Quick Action Buttons -->
                <div class="flex flex-wrap items-center gap-1.5">
                    <!-- Live Balance Button -->
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="isLiveBalanceModalOpen = true"
                        class="h-6.5 gap-1 rounded border-emerald-500/30 bg-emerald-500/10 px-2 text-[9px] font-black uppercase text-emerald-700 hover:bg-emerald-500/20 dark:text-emerald-300 shadow-xs active:scale-95 transition"
                        title="Check Live Balance on Paybill via Safaricom Daraja"
                    >
                        <Scale class="h-2.5 w-2.5 text-emerald-600 dark:text-emerald-400" />
                        <span>Live Balance</span>
                        <span
                            v-if="liveBalanceData?.is_balanced"
                            class="h-1.5 w-1.5 rounded-full bg-emerald-500 shrink-0"
                            title="Ledger Balanced"
                        />
                        <span
                            v-else-if="liveBalanceData && !liveBalanceData.is_balanced"
                            class="h-1.5 w-1.5 rounded-full bg-amber-500 shrink-0"
                            title="Discrepancy Detected"
                        />
                    </Button>

                    <!-- Record All Missing Button -->
                    <Button
                        v-if="(summary?.not_recorded || 0) > 0"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="confirmRecordAll"
                        :disabled="recordingAllLoading"
                        class="h-6.5 gap-1 rounded border-emerald-500/40 bg-emerald-500/15 px-2 text-[9px] font-black uppercase text-emerald-700 hover:bg-emerald-500/25 dark:text-emerald-300 shadow-xs active:scale-95 transition"
                        title="Automatically record all missing pulled transactions into the ERP ledger"
                    >
                        <CheckCircle2 v-if="!recordingAllLoading" class="h-2.5 w-2.5 text-emerald-600 dark:text-emerald-400" />
                        <Loader2 v-else class="h-2.5 w-2.5 animate-spin" />
                        <span>Record All Missing ({{ summary.not_recorded }})</span>
                    </Button>

                    <!-- Auto Map Synced Button -->
                    <button
                        v-if="transactions.data.length > 0"
                        type="button"
                        @click="bulkAutoMap"
                        :disabled="bulkAutoMapLoading || !assignedCustomer"
                        :class="[
                            'flex h-6.5 shrink-0 items-center justify-center gap-1 rounded border px-2 text-[9px] font-black uppercase transition select-none active:scale-95',
                            assignedCustomer
                                ? 'border-indigo-500/30 bg-indigo-500/10 text-indigo-600 hover:bg-indigo-500/20 shadow-xs dark:text-indigo-400'
                                : 'cursor-not-allowed border-border bg-muted/20 text-muted-foreground opacity-60',
                        ]"
                        :title="
                            assignedCustomer
                                ? 'Auto-map all unmapped synced transactions to assigned customer'
                                : 'Select a Paybill with an assigned customer to enable auto-mapping'
                        "
                    >
                        <Activity v-if="!bulkAutoMapLoading" class="h-2.5 w-2.5" />
                        <Loader2 v-else class="h-2.5 w-2.5 animate-spin" />
                        <span>{{ bulkAutoMapLoading ? 'Mapping...' : 'Auto Map Synced' }}</span>
                    </button>

                    <!-- Register Pull URL Button -->
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="registerForm.processing"
                        @click="openRegisterModal"
                        class="h-6.5 gap-1 rounded border-indigo-500/30 bg-indigo-500/5 px-2 text-[9px] font-black text-indigo-600 hover:bg-indigo-500/10 dark:text-indigo-400"
                    >
                        <Sparkles class="h-2.5 w-2.5" />
                        <span>Register Pull URL</span>
                    </Button>
                </div>

                <!-- Right: Date Presets & Pull API Query -->
                <form @submit.prevent="submitQuery" class="flex flex-wrap items-center gap-1.5 text-xs">
                    <div class="flex items-center gap-0.5 rounded border border-border/50 bg-background/50 p-0.5">
                        <button
                            type="button"
                            v-for="preset in ['2h', '6h', 'today', '24h', '48h'] as const"
                            :key="preset"
                            @click="selectPreset(preset)"
                            :class="[
                                'rounded px-1.5 py-0.5 text-[8.5px] font-black uppercase transition',
                                activePreset === preset
                                    ? 'bg-foreground text-background shadow-2xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            ]"
                        >
                            {{ preset }}
                        </button>
                    </div>

                    <div class="flex items-center gap-1">
                        <Input
                            type="datetime-local"
                            v-model="queryForm.start_date"
                            class="h-6.5 w-36 px-1.5 text-[10px]"
                            required
                        />
                        <span class="text-[9px] font-bold text-muted-foreground">to</span>
                        <Input
                            type="datetime-local"
                            v-model="queryForm.end_date"
                            class="h-6.5 w-36 px-1.5 text-[10px]"
                            required
                        />
                    </div>

                    <Button
                        type="submit"
                        size="sm"
                        :disabled="queryForm.processing || isStartDateOver48h"
                        class="h-6.5 gap-1 bg-indigo-600 hover:bg-indigo-700 text-white text-[9px] font-black uppercase px-2 shadow-xs active:scale-95"
                    >
                        <DownloadCloud v-if="!queryForm.processing" class="h-2.5 w-2.5" />
                        <Loader2 v-else class="h-2.5 w-2.5 animate-spin" />
                        <span>{{ queryForm.processing ? 'Pulling...' : 'Submit Query' }}</span>
                    </Button>
                </form>
            </div>

            <!-- Over 48h warning -->
            <div
                v-if="isStartDateOver48h"
                class="mt-1 flex items-center gap-1 rounded border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[9px] text-amber-700 dark:text-amber-300"
            >
                <ShieldAlert class="h-3 w-3 shrink-0" />
                <span><strong>Warning:</strong> Safaricom Pull API only supports querying the last 48 hours. Please adjust Start Date.</span>
            </div>
        </div>

        <!-- 3 Summary Metrics Strip -->
        <div
            v-if="summary && (transactions.total > 0 || summary.total_imported > 0)"
            class="grid grid-cols-1 sm:grid-cols-3 gap-1.5 shrink-0"
        >
            <div class="flex items-center gap-2 rounded border border-border/50 bg-card/40 px-2 py-1 shadow-xs backdrop-blur-md">
                <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded border border-indigo-500/20 bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                    <FileSpreadsheet class="h-3 w-3" />
                </div>
                <div class="min-w-0 flex-1 flex items-baseline justify-between">
                    <span class="text-[8px] font-black text-muted-foreground uppercase tracking-wider">Total Pulled</span>
                    <div class="flex items-baseline gap-1">
                        <span class="text-[11px] font-black text-indigo-600 dark:text-indigo-400">{{ summary.total_imported }} Records</span>
                        <span class="text-[9px] text-muted-foreground font-bold">(KES {{ formatCurrency(stats.total_pulled_amount) }})</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 rounded border border-border/50 bg-card/40 px-2 py-1 shadow-xs backdrop-blur-md">
                <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded border border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                    <CheckCircle2 class="h-3 w-3" />
                </div>
                <div class="min-w-0 flex-1 flex items-baseline justify-between">
                    <span class="text-[8px] font-black text-muted-foreground uppercase tracking-wider">Matched / Recorded</span>
                    <span class="text-[11px] font-black text-emerald-600 uppercase">{{ summary.matched_local }} Records</span>
                </div>
            </div>

            <div class="flex items-center gap-2 rounded border border-border/50 bg-card/40 px-2 py-1 shadow-xs backdrop-blur-md">
                <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded border border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400">
                    <AlertCircle class="h-3 w-3" />
                </div>
                <div class="min-w-0 flex-1 flex items-baseline justify-between">
                    <span class="text-[8px] font-black text-muted-foreground uppercase tracking-wider">Not Yet Recorded</span>
                    <span class="text-[11px] font-black text-amber-600 uppercase">{{ summary.not_recorded }} Records</span>
                </div>
            </div>
        </div>

        <!-- Assigned Customer Banner -->
        <div
            v-if="assignedCustomer"
            class="flex items-center gap-2 rounded border border-indigo-500/20 bg-indigo-500/5 px-2.5 py-1 text-[10.5px] backdrop-blur-md"
        >
            <span class="font-black text-indigo-700 dark:text-indigo-300 uppercase">Assigned Customer:</span>
            <span class="font-bold text-foreground">{{ assignedCustomer.company_name }}</span>
            <span class="text-muted-foreground">({{ assignedCustomer.contact_person_names }})</span>
        </div>

        <!-- Quick Filters Bar -->
        <div class="flex flex-wrap items-center justify-between gap-1.5 rounded-lg border border-border/50 bg-card/30 p-1.5 backdrop-blur-md">
            <div class="flex items-center gap-1.5 flex-1 min-w-[200px]">
                <div class="relative w-full max-w-xs">
                    <Search class="absolute top-1/2 left-2 h-3 w-3 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="search"
                        placeholder="Search receipt, phone, name..."
                        class="h-7 pl-7 text-[11px] bg-background/80"
                    />
                </div>

                <div class="w-36">
                    <SearchableSelect
                        v-model="statusFilter"
                        :options="statusOptions"
                        placeholder="STATUS"
                        class="h-7 !py-0 !text-[9.5px] font-black tracking-wider uppercase"
                        :clearable="false"
                    />
                </div>
            </div>

            <div class="w-28">
                <SearchableSelect
                    v-model="perPage"
                    :options="perPageOptions"
                    placeholder="PER PAGE"
                    class="h-7 !py-0 !text-[9.5px] font-black tracking-wider uppercase"
                    :clearable="false"
                    :searchable="false"
                />
            </div>
        </div>

        <!-- Transactions Ledger Table -->
        <div class="flex-1 overflow-hidden rounded-lg border border-border/50 bg-card/30 shadow-xs backdrop-blur-md">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-border/60 bg-muted/60 text-[9.5px] font-black uppercase tracking-wider text-muted-foreground">
                            <th class="px-3 py-1.5">Date / Time</th>
                            <th class="px-3 py-1.5">Receipt #</th>
                            <th class="px-3 py-1.5">Customer / Entity</th>
                            <th class="px-3 py-1.5">Phone (MSISDN)</th>
                            <th class="px-3 py-1.5">Bill Ref</th>
                            <th class="px-3 py-1.5 text-right">Amount</th>
                            <th class="px-3 py-1.5">ERP Status</th>
                            <th class="px-3 py-1.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/30">
                        <tr
                            v-for="tx in transactions.data"
                            :key="tx.id"
                            :class="[
                                'transition-colors hover:bg-muted/20',
                                isTransactionRecorded(tx) ? 'bg-emerald-500/5' : '',
                            ]"
                        >
                            <!-- Date / Time -->
                            <td class="px-3 py-1 whitespace-nowrap text-muted-foreground text-[10px]">
                                {{ formatDateTime(tx.trans_time) }}
                            </td>

                            <!-- Receipt No -->
                            <td class="px-3 py-1 font-mono font-bold text-foreground text-[11px]">
                                <div class="flex items-center gap-1">
                                    <span>{{ tx.receipt_no || tx.trans_id }}</span>
                                    <button
                                        type="button"
                                        @click="copyText(tx.receipt_no || tx.trans_id)"
                                        class="text-muted-foreground/60 hover:text-foreground"
                                        title="Copy Receipt No"
                                    >
                                        <Copy v-if="copiedId !== (tx.receipt_no || tx.trans_id)" class="h-2.5 w-2.5" />
                                        <Check v-else class="h-2.5 w-2.5 text-emerald-500" />
                                    </button>
                                </div>
                            </td>

                            <!-- Customer / Entity -->
                            <td class="px-3 py-1">
                                <div v-if="getEffectiveCustomer(tx)" class="flex flex-col text-[10.5px]">
                                    <span class="font-bold text-foreground uppercase truncate max-w-[140px]" :title="getEffectiveCustomer(tx).company_name">
                                        {{ getEffectiveCustomer(tx).company_name }}
                                    </span>
                                    <span class="text-[9px] text-muted-foreground truncate max-w-[140px]">
                                        {{ getEffectiveCustomer(tx).contact_person_names }}
                                    </span>
                                </div>
                                <span v-else class="text-[10px] text-muted-foreground italic">
                                    {{ [tx.first_name, tx.middle_name, tx.last_name].filter(Boolean).join(' ') || '—' }}
                                </span>
                            </td>

                            <!-- Phone -->
                            <td class="px-3 py-1 font-mono text-[10px] text-muted-foreground">
                                {{ tx.msisdn }}
                            </td>

                            <!-- Bill Ref -->
                            <td class="px-3 py-1 font-mono text-[10px] text-muted-foreground">
                                {{ tx.bill_ref_number || '—' }}
                            </td>

                            <!-- Amount -->
                            <td class="px-3 py-1 text-right font-black tabular-nums text-foreground text-[11px]">
                                {{ formatCurrency(tx.trans_amount) }}
                            </td>

                            <!-- ERP Status Badge -->
                            <td class="px-3 py-1">
                                <Badge
                                    v-if="isTransactionRecorded(tx)"
                                    variant="outline"
                                    class="border-emerald-500/30 bg-emerald-500/10 text-[8px] font-black uppercase text-emerald-600 px-1.5 py-0.2"
                                >
                                    Recorded in ERP
                                </Badge>
                                <Badge
                                    v-else
                                    variant="outline"
                                    class="border-amber-500/30 bg-amber-500/10 text-[8px] font-black uppercase text-amber-600 px-1.5 py-0.2"
                                >
                                    Not Recorded
                                </Badge>
                            </td>

                            <!-- Actions -->
                            <td class="px-3 py-1 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <!-- Record into ERP Button (If unmatched) -->
                                    <Button
                                        v-if="!isTransactionRecorded(tx)"
                                        size="sm"
                                        variant="outline"
                                        @click="confirmRecordToErp(tx)"
                                        class="h-5.5 px-2 text-[8px] font-black uppercase border-emerald-500/30 bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/20"
                                        title="Directly settle and book transaction into ERP ledger"
                                    >
                                        Record in ERP
                                    </Button>

                                    <!-- Map to Customer Trigger (Only for recorded transactions sitting unmapped in Suspense) -->
                                    <Button
                                        v-if="canMap && isTransactionRecorded(tx) && !isTxMapped(tx)"
                                        size="sm"
                                        variant="ghost"
                                        @click="openMapModal(tx)"
                                        class="h-5.5 px-1.5 text-[8px] font-bold text-indigo-600 hover:bg-indigo-500/10 dark:text-indigo-400"
                                        title="Map transaction in Suspense to Customer"
                                    >
                                        Map
                                    </Button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="transactions.data.length === 0">
                            <td colspan="8" class="p-8 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center gap-1.5">
                                    <Wallet class="h-6 w-6 text-muted-foreground/40" />
                                    <p class="text-[11px] font-bold uppercase tracking-wider">
                                        No pulled transactions found
                                    </p>
                                    <p class="text-[9.5px]">Use the date query tool above to fetch transactions from Safaricom.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination
                v-if="transactions.links && transactions.total > 0"
                :links="transactions.links"
                :from="transactions.from || 0"
                :to="transactions.to || 0"
                :total="transactions.total"
                :only="['transactions', 'stats', 'summary']"
            />
        </div>

        <!-- Modals -->
        <LiveBalanceModal
            v-if="isLiveBalanceModalOpen"
            :open="isLiveBalanceModalOpen"
            :payment-account="paymentAccount"
            :last-balance="liveBalanceData"
            @update:open="isLiveBalanceModalOpen = $event"
            @balance-updated="handleBalanceUpdated"
        />

        <TransactionSyncModal
            :open="syncModalOpen"
            :default-receipt-number="syncModalReceiptNo"
            :paybill-options="paybillOptions"
            :default-payment-account-id="paymentAccount.id"
            @update:open="syncModalOpen = $event"
            @success="handleSyncSuccess"
        />

        <TransactionMappingModal
            :open="mapModalOpen"
            :transaction="mappingTransaction"
            :searching-customers="searchingCustomers"
            :customer-search-results="customerSearchResults"
            :mapping-loading="mappingLoading"
            :companies="companies || []"
            :branches="branches || []"
            @close="closeMapModal"
            @search="handleCustomerSearch"
            @select="selectCustomer"
            @submit="submitMapping"
        />

        <!-- Confirm Record to ERP Modal -->
        <Dialog :open="recordConfirmModalOpen" @update:open="recordConfirmModalOpen = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-sm font-black uppercase tracking-wider text-foreground">
                        Record Transaction into ERP Ledger
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        This will create a verified ERP payment transaction and post the accounting entries.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="transactionToRecord" class="space-y-2 py-2 text-xs">
                    <div class="flex justify-between border-b pb-1 text-muted-foreground">
                        <span>Receipt / Trans ID:</span>
                        <span class="font-mono font-bold text-foreground">{{ transactionToRecord.receipt_no || transactionToRecord.trans_id }}</span>
                    </div>
                    <div class="flex justify-between border-b pb-1 text-muted-foreground">
                        <span>Amount:</span>
                        <span class="font-black text-emerald-600">KES {{ formatCurrency(transactionToRecord.trans_amount) }}</span>
                    </div>
                    <div class="flex justify-between border-b pb-1 text-muted-foreground">
                        <span>Phone / Payer:</span>
                        <span class="font-bold text-foreground">{{ transactionToRecord.msisdn }} ({{ [transactionToRecord.first_name, transactionToRecord.last_name].filter(Boolean).join(' ') || 'Customer' }})</span>
                    </div>
                    <div class="flex justify-between pb-1 text-muted-foreground">
                        <span>Bill Ref Number:</span>
                        <span class="font-mono font-bold text-foreground">{{ transactionToRecord.bill_ref_number || 'None' }}</span>
                    </div>
                </div>

                <DialogFooter class="flex items-center justify-end gap-2 pt-2">
                    <Button variant="ghost" size="sm" @click="recordConfirmModalOpen = false" :disabled="recordingLoading" class="text-xs">
                        Cancel
                    </Button>
                    <Button
                        size="sm"
                        @click="submitRecordToErp"
                        :disabled="recordingLoading"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold gap-1"
                    >
                        <Loader2 v-if="recordingLoading" class="h-3 w-3 animate-spin" />
                        <span>{{ recordingLoading ? 'Recording...' : 'Confirm & Record' }}</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Confirm Record All Missing Modal -->
        <Dialog :open="recordAllModalOpen" @update:open="recordAllModalOpen = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-sm font-black uppercase tracking-wider text-foreground">
                        Record All Missing Transactions
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        This will automatically settle all {{ summary?.not_recorded || 0 }} unrecorded pulled transaction(s) into the ERP ledger using their C2B account references.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-2 py-3 text-xs">
                    <div class="rounded-md border border-emerald-500/20 bg-emerald-500/5 p-3 text-foreground">
                        <p class="font-bold text-emerald-700 dark:text-emerald-400 mb-1">
                            Settlement pipeline:
                        </p>
                        <ul class="list-disc pl-4 space-y-1 text-muted-foreground text-[11px]">
                            <li>Each transaction is settled into the M-Pesa ledger atomically.</li>
                            <li>Payments matching invoices or customer wallets are allocated automatically.</li>
                            <li>Unmatched transactions credit the Paybill account balance and Suspense (2016).</li>
                            <li>Paybill balance in ERP will immediately match the Safaricom statement.</li>
                        </ul>
                    </div>
                </div>

                <DialogFooter class="flex items-center justify-end gap-2 pt-2">
                    <Button variant="ghost" size="sm" @click="recordAllModalOpen = false" :disabled="recordingAllLoading" class="text-xs">
                        Cancel
                    </Button>
                    <Button
                        size="sm"
                        @click="submitRecordAll"
                        :disabled="recordingAllLoading"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold gap-1"
                    >
                        <Loader2 v-if="recordingAllLoading" class="h-3 w-3 animate-spin" />
                        <span>{{ recordingAllLoading ? 'Recording All...' : 'Confirm & Record All' }}</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Register Pull URL Modal -->
        <Dialog :open="registerModalOpen" @update:open="registerModalOpen = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-sm font-black uppercase tracking-wider text-foreground">
                        Register Safaricom Pull Callback URL
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        Configure the Safaricom Daraja Pull Transactions webhook endpoint and nominated phone number.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-3 py-2 text-xs">
                    <div class="space-y-1">
                        <label class="font-bold text-foreground text-[11px] block">Target Callback URL</label>
                        <div class="p-2 rounded bg-muted/30 border border-border/50 font-mono text-[10px] break-all select-all">
                            {{ pullCallbackUrl || 'Auto-generated webhook URL' }}
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="font-bold text-foreground text-[11px] block">Nominated Phone Number (254...)</label>
                        <Input
                            v-model="registerForm.nominated_number"
                            placeholder="e.g. 254712345678"
                            class="h-8 text-xs font-mono"
                            required
                        />
                    </div>
                </div>

                <DialogFooter class="flex items-center justify-end gap-2 pt-2">
                    <Button variant="ghost" size="sm" @click="registerModalOpen = false" :disabled="registerForm.processing" class="text-xs">
                        Cancel
                    </Button>
                    <Button
                        size="sm"
                        @click="confirmRegisterPull"
                        :disabled="registerForm.processing || !registerForm.nominated_number"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold gap-1"
                    >
                        <Loader2 v-if="registerForm.processing" class="h-3 w-3 animate-spin" />
                        <span>{{ registerForm.processing ? 'Registering...' : 'Register URL' }}</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
