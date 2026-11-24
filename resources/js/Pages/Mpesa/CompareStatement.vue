<script setup lang="ts">
import TransactionMappingModal from '@/components/Mpesa/TransactionMappingModal.vue';
import TransactionSyncModal from '@/components/Mpesa/TransactionSyncModal.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import 'flatpickr/dist/flatpickr.css';
import {
    Activity,
    AlertCircle,
    ArrowLeft,
    Calendar,
    Check,
    CheckCircle2,
    Copy,
    FileSpreadsheet,
    Loader2,
    RefreshCw,
    Upload,
    XCircle,
} from 'lucide-vue-next';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const canMap = computed(() => {
    const permissions = (page.props.auth as any)?.permissions || [];
    return (
        permissions.includes('Finance: Mpesa: Map') ||
        permissions.includes('Finance: Mpesa: Manage')
    );
});

const copiedReceipt = ref<string | null>(null);

const copyToClipboard = (text: string) => {
    if (!text || text === '---') return;
    navigator.clipboard.writeText(text);
    copiedReceipt.value = text;
    setTimeout(() => {
        if (copiedReceipt.value === text) {
            copiedReceipt.value = null;
        }
    }, 2000);
};

interface PaymentAccount {
    id: number;
    name: string;
    account_number: string;
}

interface Summary {
    total_imported: number;
    matched_local: number;
    not_recorded: number;
}

interface Transaction {
    receipt_no: string;
    completion_time: string;
    details: string;
    status: string;
    paid_in: number;
    withdrawn: number;
    type: string;
    is_recorded: boolean;
    local_transaction?: {
        id?: number;
        status?: string;
        amount?: string | number;
        phone_number?: string;
        [key: string]: any;
    } | null;
}

interface Props {
    paymentAccounts: PaymentAccount[];
    transactions: Transaction[];
    summary: Summary;
    filters: {
        payment_account_id?: string | number;
        start_date?: string;
        end_date?: string;
    };
    companies?: { id: number; name: string }[];
    branches?: { id: number; name: string; company_id: number }[];
    assignedCustomer?: {
        id: number;
        company_name: string;
        contact_person_names: string;
    } | null;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Finance Management', href: '/organization/finance' },
    { title: 'M-Pesa Ledger', href: '/mpesa/transactions' },
    { title: 'Compare Statement', href: '#' },
];

import { watch } from 'vue';

const selectedPaymentAccountId = ref(props.filters.payment_account_id || null);

watch(
    () => props.filters.payment_account_id,
    (val) => {
        if (val) {
            selectedPaymentAccountId.value = val;
        }
    },
    { immediate: true },
);

// Auto-reload when Paybill is selected to fetch assigned customer and cache the selection
watch(selectedPaymentAccountId, (newId) => {
    if (newId && newId != props.filters.payment_account_id) {
        router.get(
            '/mpesa/transactions/compare-statement',
            {
                payment_account_id: newId,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    // Ensure the select stays in sync
                    selectedPaymentAccountId.value = newId;
                },
            },
        );
    }
});

const dateRange = ref(
    props.filters.start_date && props.filters.end_date
        ? `${props.filters.start_date} to ${props.filters.end_date}`
        : props.filters.start_date || '',
);
const selectedFile = ref<File | null>(null);

const paybillOptions = computed(() => {
    return props.paymentAccounts.map((account) => ({
        id: account.id,
        label: `${account.name} | ${account.account_number}`,
    }));
});
const processing = ref(false);

const handleFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        selectedFile.value = target.files[0];
    }
};

const submitCompare = () => {
    if (!selectedPaymentAccountId.value) {
        alert('Please select a Payment Account (Paybill).');
        return;
    }

    if (!selectedFile.value && props.transactions.length === 0) {
        alert('Please choose an Excel statement file.');
        return;
    }

    const formData = new FormData();
    formData.append(
        'payment_account_id',
        selectedPaymentAccountId.value.toString(),
    );
    if (selectedFile.value) formData.append('excel_file', selectedFile.value);

    router.post('/mpesa/transactions/compare-statement', formData, {
        forceFormData: true,
        onBefore: () => {
            processing.value = true;
        },
        onFinish: () => {
            processing.value = false;
        },
        onError: (err) => {
            alert('Error processing file: ' + JSON.stringify(err));
        },
    });
};

const clearCache = () => {
    if (
        !confirm(
            'Are you sure you want to clear the session cache? This will reset the statement comparison table.',
        )
    )
        return;
    router.post(
        '/mpesa/transactions/clear-statement-cache',
        {},
        {
            onSuccess: () => {
                selectedFile.value = null;
            },
        },
    );
};

// Sync Feature
const syncModalOpen = ref(false);
const syncModalReceiptNo = ref<string>('');

const openSyncModal = (receiptNo?: string) => {
    let paymentAccountId = selectedPaymentAccountId.value;
    if (
        !paymentAccountId &&
        props.paymentAccounts &&
        props.paymentAccounts.length === 1
    ) {
        paymentAccountId = props.paymentAccounts[0].id;
    }
    if (!paymentAccountId) {
        alert(
            'Please select a Payment Account (Paybill) from the dropdown first.',
        );
        return;
    }
    syncModalReceiptNo.value = receiptNo || '';
    syncModalOpen.value = true;
};

const syncImportedTransaction = (tx: Transaction) => {
    openSyncModal(tx.receipt_no);
};

const handleSyncSuccess = () => {
    router.reload();
};

const extractAccount = (details: string) => {
    if (!details) return null;
    const match = details.match(/Acc\.\s*(.+)$/i);
    return match ? match[1].trim() : null;
};

// Mapping Feature
const mapModalOpen = ref(false);
const mappingTransaction = ref<any>(null);
const mappingLoading = ref(false);

const searchCustomerQuery = ref('');
const customerSearchResults = ref<any[]>([]);
const selectedCustomer = ref<any>(null);
const searchingCustomers = ref(false);

const openMapModal = (tx: any) => {
    mappingTransaction.value = {
        ...tx.local_transaction,
        amount:
            tx.local_transaction?.amount &&
            Number(tx.local_transaction.amount) > 0
                ? tx.local_transaction.amount
                : tx.paid_in,
        phone_number:
            tx.local_transaction?.phone_number &&
            tx.local_transaction.phone_number !== '---'
                ? tx.local_transaction.phone_number
                : tx.details,
    };
    searchCustomerQuery.value = '';
    customerSearchResults.value = [];
    selectedCustomer.value = null;
    mapModalOpen.value = true;
};

const closeMapModal = () => {
    mapModalOpen.value = false;
    mappingTransaction.value = null;
    searchCustomerQuery.value = '';
    customerSearchResults.value = [];
    selectedCustomer.value = null;
};

let customerSearchTimeout: ReturnType<typeof setTimeout>;
const handleCustomerSearch = (payload: any) => {
    clearTimeout(customerSearchTimeout);
    selectedCustomer.value = null;

    let queryStr = '';
    let companyId = '';
    let branchId = '';

    if (typeof payload === 'object') {
        queryStr = payload.query || '';
        companyId = payload.company_id || '';
        branchId = payload.branch_id || '';
    } else {
        queryStr = String(payload);
    }

    searchCustomerQuery.value = queryStr;

    if (queryStr.length < 2 && !companyId && !branchId) {
        customerSearchResults.value = [];
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
                router.reload();
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

const bulkAutoMapLoading = ref(false);
const bulkAutoMap = () => {
    if (!selectedPaymentAccountId.value) {
        alert('Please select a Payment Account (Paybill) first.');
        return;
    }

    if (
        !confirm(
            `Are you sure you want to automatically map all unmapped synced transactions for this Paybill to ${props.assignedCustomer?.company_name}?`,
        )
    )
        return;

    router.post(
        '/mpesa/transactions/bulk-auto-map',
        {
            payment_account_id: selectedPaymentAccountId.value,
        },
        {
            onBefore: () => {
                bulkAutoMapLoading.value = true;
            },
            onSuccess: () => {
                router.reload();
            },
            onError: (err: any) => {
                alert(
                    'Error during bulk mapping: ' +
                        (err.response?.data?.message ||
                            'Check logs for details.'),
                );
            },
            onFinish: () => {
                bulkAutoMapLoading.value = false;
            },
        },
    );
};

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-KE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
};

const labelClass =
    'text-[9px] font-black text-muted-foreground uppercase tracking-widest mb-0.5 block';
</script>

<template>
    <Head title="M-Pesa Statement Comparison" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex w-full flex-1 flex-col overflow-hidden p-4">
            <!-- Breadcrumb Navigation -->
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <button
                        @click="router.get('/mpesa/transactions')"
                        class="flex items-center justify-center rounded-lg border border-border bg-card p-2 text-foreground transition-all hover:bg-muted active:scale-95"
                    >
                        <ArrowLeft class="h-4 w-4" />
                    </button>
                    <div>
                        <h1
                            class="text-2xl font-black tracking-tight text-foreground"
                        >
                            Compare M-Pesa Statement
                        </h1>
                        <p
                            class="text-[11px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Synchronize Missing Payments from Excel Statement
                        </p>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div
                v-if="transactions.length > 0"
                class="animate-fade-in mb-4 grid shrink-0 grid-cols-1 gap-4 select-none md:grid-cols-3"
            >
                <!-- Total Imported -->
                <div
                    class="flex items-start gap-3 rounded-lg border border-border bg-card/40 p-3 text-card-foreground backdrop-blur-xl transition-all hover:shadow-md"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-indigo-500/20 bg-indigo-500/10 font-black text-indigo-600"
                    >
                        <FileSpreadsheet class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <span :class="labelClass"
                            >Total Imported Transactions</span
                        >
                        <p
                            class="text-xs font-black tracking-tight text-indigo-500 uppercase"
                        >
                            {{ summary.total_imported }} Records
                        </p>
                    </div>
                </div>

                <!-- Matched / Recorded in Local ERP -->
                <div
                    class="flex items-start gap-3 rounded-lg border border-border bg-card/40 p-3 text-card-foreground backdrop-blur-xl transition-all hover:shadow-md"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-emerald-500/20 bg-emerald-500/10 font-black text-emerald-600"
                    >
                        <CheckCircle2 class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <span :class="labelClass">Matched / Recorded</span>
                        <p
                            class="text-xs font-black tracking-tight text-emerald-600 uppercase"
                        >
                            {{ summary.matched_local }} Records
                        </p>
                    </div>
                </div>

                <!-- Not Recorded in Local ERP -->
                <div
                    class="flex items-start gap-3 rounded-lg border border-border bg-card/40 p-3 text-card-foreground backdrop-blur-xl transition-all hover:shadow-md"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-amber-500/20 bg-amber-500/10 font-black text-amber-600"
                    >
                        <AlertCircle class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <span :class="labelClass">Not Yet Recorded</span>
                        <p
                            class="text-xs font-black tracking-tight text-amber-600 uppercase"
                        >
                            {{ summary.not_recorded }} Records
                        </p>
                    </div>
                </div>
            </div>

            <!-- Assigned Customer Alert -->
            <div
                v-if="assignedCustomer"
                class="mb-4 animate-in duration-300 fade-in slide-in-from-top-2"
            >
                <div
                    class="flex items-center gap-3 rounded-lg border border-indigo-500/20 bg-indigo-500/5 p-3"
                >
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-full border border-indigo-500/20 bg-indigo-500/10 text-indigo-600"
                    >
                        <Activity class="h-4 w-4" />
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span
                                class="text-[10px] font-black tracking-[0.2em] text-indigo-600 uppercase"
                                >Automatic Sync Active</span
                            >
                            <span class="h-px w-4 bg-indigo-500/30"></span>
                        </div>
                        <p class="text-xs font-bold text-foreground">
                            This Paybill is assigned to
                            <span class="font-black text-indigo-600">{{
                                assignedCustomer.company_name
                            }}</span
                            >. All synced transactions will be automatically
                            mapped to this customer.
                        </p>
                    </div>
                </div>
            </div>

            <!-- No Assignment Warning -->
            <div
                v-else-if="selectedPaymentAccountId && transactions.length > 0"
                class="mb-4 animate-in duration-300 fade-in slide-in-from-top-2"
            >
                <div
                    class="flex items-center gap-3 rounded-lg border border-amber-500/20 bg-amber-500/5 p-3"
                >
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-full border border-amber-500/20 bg-amber-500/10 text-amber-600"
                    >
                        <AlertCircle class="h-4 w-4" />
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span
                                class="text-[10px] font-black tracking-[0.2em] text-amber-600 uppercase"
                                >Manual Mapping Required</span
                            >
                            <span class="h-px w-4 bg-amber-500/30"></span>
                        </div>
                        <p class="text-xs font-bold text-foreground">
                            This Paybill is not currently assigned to a
                            customer.
                            <a
                                href="/organization/financial-profiles/mpesa-configs"
                                class="font-black text-amber-600 hover:underline"
                                >Configure Assignment</a
                            >
                            to enable automatic mapping for this Paybill.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Management Container -->
            <div
                class="animate-fade-in flex flex-1 flex-col overflow-hidden rounded-xl border border-border bg-card/40 shadow-sm backdrop-blur-xl select-none"
            >
                <!-- Filter Bar -->
                <div
                    class="flex shrink-0 flex-wrap items-center gap-4 border-b border-border bg-accent/5 p-3 select-none"
                >
                    <!-- Paybill Filter -->
                    <div class="w-64 shrink-0">
                        <SearchableSelect
                            v-model="selectedPaymentAccountId"
                            :options="paybillOptions"
                            placeholder="FILTER BY PAYBILL"
                            class="h-8 !py-0 !text-[10px] font-black tracking-wider uppercase"
                            :clearable="true"
                        />
                    </div>

                    <!-- File Upload & Actions -->
                    <div class="flex min-w-0 flex-1 items-center gap-2">
                        <label
                            class="flex h-8 flex-1 cursor-pointer items-center justify-between gap-3 overflow-hidden rounded-lg border border-border bg-background/50 px-3 text-[10px] font-black text-foreground uppercase transition hover:bg-muted"
                        >
                            <span class="flex-1 truncate select-none">{{
                                selectedFile
                                    ? selectedFile.name
                                    : 'Choose Excel file'
                            }}</span>
                            <Upload
                                class="h-3.5 w-3.5 shrink-0 text-muted-foreground"
                            />
                            <input
                                type="file"
                                @change="handleFileChange"
                                accept=".xlsx,.xls"
                                class="hidden"
                            />
                        </label>

                        <button
                            @click="submitCompare"
                            :disabled="processing"
                            class="flex h-8 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-indigo-500 px-3.5 text-[10px] font-black text-white uppercase shadow-sm transition hover:bg-indigo-600 active:scale-95 disabled:opacity-50"
                        >
                            <RefreshCw
                                v-if="processing"
                                class="h-3.5 w-3.5 animate-spin"
                            />
                            <FileSpreadsheet v-else class="h-3.5 w-3.5" />
                            <span>{{
                                processing ? 'Parsing...' : 'Compare'
                            }}</span>
                        </button>

                        <button
                            v-if="transactions.length > 0"
                            @click="bulkAutoMap"
                            :disabled="bulkAutoMapLoading || !assignedCustomer"
                            :class="[
                                'flex h-8 shrink-0 items-center justify-center gap-1.5 rounded-lg border px-3.5 text-[10px] font-black uppercase transition select-none active:scale-95',
                                assignedCustomer
                                    ? 'border-indigo-500/30 text-indigo-500 shadow-sm hover:bg-indigo-500/10'
                                    : 'cursor-not-allowed border-border bg-muted/20 text-muted-foreground opacity-60',
                            ]"
                            :title="
                                assignedCustomer
                                    ? 'Auto-map all unmapped synced transactions to assigned customer'
                                    : 'Select a Paybill with an assigned customer to enable auto-mapping'
                            "
                        >
                            <Activity
                                v-if="!bulkAutoMapLoading"
                                class="h-3.5 w-3.5"
                            />
                            <Loader2 v-else class="h-3.5 w-3.5 animate-spin" />
                            <span>{{
                                bulkAutoMapLoading
                                    ? 'Mapping...'
                                    : 'Auto Map Synced'
                            }}</span>
                        </button>

                        <button
                            v-if="transactions.length > 0"
                            @click="clearCache"
                            class="flex h-8 shrink-0 items-center justify-center gap-1.5 rounded-lg border border-red-500/30 px-3.5 text-[10px] font-black text-red-500 uppercase transition select-none hover:bg-red-500/10 active:scale-95"
                            title="Clear statement cache"
                        >
                            <XCircle class="h-3.5 w-3.5" />
                            <span>Clear Cache</span>
                        </button>
                    </div>
                </div>

                <div class="flex flex-1 flex-col overflow-auto">
                    <table
                        class="w-full border-separate border-spacing-0 whitespace-nowrap"
                    >
                        <thead
                            class="sticky top-0 z-10 bg-card/95 backdrop-blur-md"
                        >
                            <tr>
                                <th
                                    class="border-b border-border bg-accent/5 px-3 py-2 text-left text-[10px] font-black text-muted-foreground uppercase"
                                >
                                    Date / Time
                                </th>
                                <th
                                    class="border-b border-border bg-accent/5 px-3 py-2 text-left text-[10px] font-black text-muted-foreground uppercase"
                                >
                                    Receipt No
                                </th>
                                <th
                                    class="border-b border-border bg-accent/5 px-3 py-2 text-left text-[10px] font-black text-muted-foreground uppercase"
                                >
                                    Details
                                </th>
                                <th
                                    class="border-b border-border bg-accent/5 px-3 py-2 text-center text-[10px] font-black text-muted-foreground uppercase"
                                >
                                    Type
                                </th>
                                <th
                                    class="border-b border-border bg-accent/5 px-3 py-2 text-right text-[10px] font-black text-muted-foreground uppercase"
                                >
                                    Paid In
                                </th>
                                <th
                                    class="border-b border-border bg-accent/5 px-3 py-2 text-right text-[10px] font-black text-muted-foreground uppercase"
                                >
                                    Withdrawn
                                </th>
                                <th
                                    class="border-b border-border bg-accent/5 px-3 py-2 text-center text-[10px] font-black text-muted-foreground uppercase"
                                >
                                    ERP Status
                                </th>
                                <th
                                    class="sticky right-0 border-b border-l border-border bg-accent/5 px-3 py-2 text-right text-[10px] font-black text-muted-foreground uppercase backdrop-blur-md"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            v-if="transactions.length > 0"
                            class="divide-y divide-border text-[11px]"
                        >
                            <tr
                                v-for="tx in transactions"
                                :key="tx.receipt_no"
                                class="group cursor-default transition-colors hover:bg-primary/5"
                            >
                                <td class="px-3 py-2">
                                    <div
                                        class="flex items-center gap-1 text-[11px] leading-tight font-bold whitespace-nowrap text-muted-foreground"
                                    >
                                        <Calendar class="h-3 w-3 opacity-50" />
                                        {{ tx.completion_time }}
                                    </div>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-1.5">
                                        <span
                                            class="text-[13px] leading-none font-black tracking-tight text-foreground uppercase"
                                        >
                                            {{ tx.receipt_no }}
                                        </span>
                                        <button
                                            v-if="tx.receipt_no"
                                            type="button"
                                            @click.stop="copyToClipboard(tx.receipt_no)"
                                            class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded text-muted-foreground/50 transition-colors hover:bg-accent/40 hover:text-foreground active:scale-95"
                                            :title="copiedReceipt === tx.receipt_no ? 'Copied!' : 'Copy Receipt Number'"
                                        >
                                            <Check v-if="copiedReceipt === tx.receipt_no" class="h-3 w-3 text-emerald-500" />
                                            <Copy v-else class="h-3 w-3" />
                                        </button>
                                    </div>
                                </td>
                                <td
                                    class="max-w-[280px] truncate px-3 py-2"
                                    :title="tx.details"
                                >
                                    <div
                                        class="flex w-full items-center justify-between gap-1"
                                    >
                                        <span
                                            class="truncate text-[11px] font-semibold text-muted-foreground"
                                        >
                                            {{ tx.details }}
                                        </span>
                                        <span
                                            v-if="extractAccount(tx.details)"
                                            class="inline-flex shrink-0 items-center rounded border border-blue-500/20 bg-blue-500/10 px-1 py-0.5 text-[8px] leading-none font-black text-blue-600 uppercase select-none"
                                        >
                                            Acc:
                                            {{ extractAccount(tx.details) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span
                                        :class="[
                                            'inline-flex items-center rounded-full border px-2 py-0.5 text-[9px] font-black tracking-wider uppercase',
                                            tx.type === 'In'
                                                ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600'
                                                : 'border-amber-500/20 bg-amber-500/10 text-amber-600',
                                        ]"
                                    >
                                        {{ tx.type }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-2 text-right text-[13px] leading-tight font-black text-foreground"
                                >
                                    {{
                                        tx.paid_in > 0
                                            ? formatCurrency(tx.paid_in)
                                            : '-'
                                    }}
                                </td>
                                <td
                                    class="px-3 py-2 text-right text-[13px] leading-tight font-black text-foreground"
                                >
                                    {{
                                        tx.withdrawn > 0
                                            ? formatCurrency(tx.withdrawn)
                                            : '-'
                                    }}
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span
                                        :class="[
                                            'inline-flex items-center rounded-full border px-2 py-0.5 text-[9px] font-black tracking-wider uppercase',
                                            tx.is_recorded
                                                ? 'border-blue-500/20 bg-blue-500/10 text-blue-600'
                                                : tx.local_transaction
                                                        ?.status ===
                                                    'sync_initiated'
                                                  ? 'border-amber-500/20 bg-amber-500/10 text-amber-600'
                                                  : 'border-rose-500/20 bg-rose-500/10 text-rose-600',
                                        ]"
                                    >
                                        {{
                                            tx.is_recorded
                                                ? 'Recorded'
                                                : tx.local_transaction
                                                        ?.status ===
                                                    'sync_initiated'
                                                  ? 'Syncing...'
                                                  : 'Not Recorded'
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="sticky right-0 z-10 border-l bg-card/95 px-3 py-2 text-right shadow-sm backdrop-blur-md transition-colors group-hover:bg-primary/5"
                                >
                                    <div class="flex justify-end gap-1">
                                        <template
                                            v-if="
                                                tx.local_transaction?.status ===
                                                'sync_initiated'
                                            "
                                        >
                                            <button
                                                @click="
                                                    syncImportedTransaction(tx)
                                                "
                                                class="flex h-6 shrink-0 items-center gap-1 rounded border border-amber-500/30 px-1.5 text-[8px] font-black text-amber-600 uppercase transition-all select-none hover:bg-amber-500/10"
                                            >
                                                <RefreshCw class="h-2.5 w-2.5" />
                                                <span>SYNC AGAIN</span>
                                            </button>
                                        </template>
                                        <template v-else>
                                            <button
                                                v-if="
                                                    !tx.is_recorded &&
                                                    tx.type !== 'Out'
                                                "
                                                @click="
                                                    syncImportedTransaction(tx)
                                                "
                                                class="flex h-6 items-center gap-1 rounded border border-emerald-500/30 px-1.5 text-[8px] font-black text-emerald-500 uppercase shadow-sm transition-all hover:bg-emerald-500/10 active:scale-95"
                                            >
                                                <RefreshCw class="h-2.5 w-2.5" />
                                                <span>SYNC NOW</span>
                                            </button>
                                            <span
                                                v-else-if="
                                                    !tx.is_recorded &&
                                                    tx.type === 'Out'
                                                "
                                                class="flex items-center gap-1 text-[9px] font-black tracking-widest text-rose-500 uppercase opacity-60"
                                            >
                                                <span>Out of Scope</span>
                                            </span>
                                            <div
                                                v-else
                                                class="flex items-center gap-1.5 select-none"
                                            >
                                                <span
                                                    class="flex items-center gap-1 text-[9px] font-black tracking-widest text-muted-foreground uppercase opacity-60"
                                                >
                                                    <CheckCircle2
                                                        class="h-3.5 w-3.5 text-emerald-500"
                                                    />
                                                    <span>Synced</span>
                                                </span>
                                                <button
                                                    v-if="
                                                        !tx.local_transaction
                                                            ?.customer_id &&
                                                        !tx.local_transaction
                                                            ?.sale_id
                                                    "
                                                    :disabled="!canMap"
                                                    @click="canMap && openMapModal(tx)"
                                                    :class="[
                                                        'flex h-6 shrink-0 items-center gap-1 rounded border px-1.5 text-[8px] font-black uppercase transition-all select-none',
                                                        canMap
                                                            ? 'border-indigo-500/30 text-indigo-500 hover:bg-indigo-500/10 cursor-pointer'
                                                            : 'border-border/40 bg-muted/20 text-muted-foreground/50 cursor-not-allowed opacity-50',
                                                    ]"
                                                    :title="
                                                        canMap
                                                            ? 'Map Now'
                                                            : 'Permission required to map transaction (Finance: Mpesa: Map)'
                                                    "
                                                >
                                                    <span>MAP NOW</span>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td
                                    colspan="8"
                                    class="px-4 py-8 text-center text-xs font-semibold text-muted-foreground"
                                >
                                    No imported transactions matching your
                                    configuration. Upload an M-Pesa statement to
                                    get started.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mapping Modal -->
            <TransactionMappingModal
                :open="mapModalOpen"
                :transaction="mappingTransaction"
                :searching-customers="searchingCustomers"
                :customer-search-results="customerSearchResults"
                :mapping-loading="mappingLoading"
                :companies="companies"
                :branches="branches"
                @close="closeMapModal"
                @search="handleCustomerSearch"
                @select="selectCustomer"
                @submit="submitMapping"
            />

            <!-- Sync Modal -->
            <TransactionSyncModal
                :open="syncModalOpen"
                :paybill-options="paybillOptions"
                :initial-payment-account-id="selectedPaymentAccountId ? Number(selectedPaymentAccountId) : null"
                :initial-transaction-id="syncModalReceiptNo"
                :auto-submit="true"
                @close="syncModalOpen = false"
                @success="handleSyncSuccess"
            />
        </div>
    </AppLayout>
</template>

<style scoped>
.animate-fade-in {
    animation: fadeIn 0.25s ease-in-out;
}
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(2px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.overflow-auto::-webkit-scrollbar {
    height: 6px;
    width: 6px;
}
.overflow-auto::-webkit-scrollbar-track {
    background: transparent;
}
.overflow-auto::-webkit-scrollbar-thumb {
    background: hsl(var(--border));
    border-radius: 10px;
}
.overflow-auto::-webkit-scrollbar-thumb:hover {
    background: hsl(var(--muted-foreground) / 0.5);
}
</style>
