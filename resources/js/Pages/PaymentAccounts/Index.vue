<script setup lang="ts">
import SearchableSelect from '@/components/SearchableSelect.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '../../Layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Plus, Search, SlidersHorizontal } from 'lucide-vue-next';
import { reactive, ref, watch } from 'vue';
import PaymentAccountModal from './PaymentAccountModal.vue';
import PaymentAccountTable from './PaymentAccountTable.vue';
import PaymentMethodStats from './Partials/PaymentMethodStats.vue';

interface PaymentAccount {
    id: number;
    company_id: number;
    branch_id: number;
    payment_method_id: number;
    name: string;
    account_number: string | null;
    bank_id: number | null;
    bank_name: string | null;
    currency: string;
    balance: string;
    is_active: boolean;
    is_vault: boolean;
    payment_method?: { id: number; name: string };
    bank?: { id: number; name: string };
    company?: { id: number; name: string };
    branch?: { id: number; name: string };
}

interface MethodSummary {
    id: number;
    name: string;
    code: string;
    total_balance: number;
    total_pending_cheque: number;
    total_pending_in: number;
    total_accounts: number;
    active_accounts: number;
}

interface OverallSummary {
    total_balance: number;
    total_pending_cheque: number;
    total_pending_in: number;
    total_accounts: number;
    active_accounts: number;
}

interface Props {
    data: {
        paymentAccounts: any;
        methods: { id: number; name: string }[];
        methodSummaries?: {
            overall: OverallSummary;
            methods: MethodSummary[];
        };
        banks: { id: number; name: string }[];
        companies: { id: number; name: string }[];
        branches: { id: number; name: string; company_id: number }[];
        users: { id: number; name: string }[];
        allBankAccounts: { id: number; name: string; branch_id: number }[];
        userAssignments: Record<number, string>;
        filters: {
            search: string | null;
            active: string | null;
            method_id: string | number | null;
            sort_by: string;
            sort_direction: string;
        };
    };
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Finance Management', href: '/organization/finance' },
    { title: 'Payment Accounts', href: '#' },
];

// Column visibility
const availableColumns = [
    { key: 'ref', label: 'Ref', default: true },
    { key: 'name', label: 'Account Name', default: true },
    { key: 'ownership', label: 'Ownership', default: true },
    { key: 'method', label: 'Method', default: true },
    { key: 'details', label: 'Account Details', default: true },
    { key: 'balance', label: 'Balance', default: true },
    { key: 'status', label: 'Status & Scope', default: true },
];

const page = usePage();

const getInitialColumnVisibility = () => {
    const saved = (page.props.auth as any)?.user?.preferences?.payment_account_table_columns;
    const visibility: Record<string, boolean> = {};

    availableColumns.forEach((col) => {
        visibility[col.key] =
            saved && typeof saved[col.key] === 'boolean'
                ? saved[col.key]
                : col.default;
    });

    return visibility;
};

const columnVisibility = reactive<Record<string, boolean>>(
    getInitialColumnVisibility(),
);

const savePreferences = async () => {
    try {
        const response = await fetch('/organization/sales/pos/preferences', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    (
                        document.querySelector(
                            'meta[name="csrf-token"]',
                        ) as HTMLMetaElement
                    )?.content || '',
            },
            body: JSON.stringify({
                key: 'payment_account_table_columns',
                value: { ...columnVisibility },
            }),
        });

        if (response.ok) {
            const user = (page.props.auth as any)?.user;
            if (user) {
                if (!user.preferences) {
                    user.preferences = {};
                }
                user.preferences.payment_account_table_columns = {
                    ...columnVisibility,
                };
            }
        }
    } catch (e) {
        console.error('Error saving column preferences:', e);
    }
};

const toggleColumn = async (key: string) => {
    columnVisibility[key] = !columnVisibility[key];
    await savePreferences();
};

watch(
    () => (page.props.auth as any)?.user?.preferences?.payment_account_table_columns,
    (newVal) => {
        if (newVal) {
            availableColumns.forEach((col) => {
                if (typeof newVal[col.key] === 'boolean') {
                    columnVisibility[col.key] = newVal[col.key];
                }
            });
        }
    },
    { deep: true },
);

// Modal state
const showModal = ref(false);
const editingItem = ref<PaymentAccount | undefined>(undefined);
const deletingItem = ref<PaymentAccount | undefined>(undefined);

// Search, filter, and sort state
const searchQuery = ref(props.data.filters?.search || '');
const activeFilter = ref(props.data.filters?.active || '');
const methodFilter = ref(props.data.filters?.method_id || '');
const sortBy = ref(props.data.filters?.sort_by || 'balance');
const sortDirection = ref(props.data.filters?.sort_direction || 'desc');

const statusOptions = [
    { id: '', label: 'All Status' },
    { id: '1', label: 'Active' },
    { id: '0', label: 'Inactive' },
];

// Modal handlers
const openCreateModal = () => {
    editingItem.value = undefined;
    showModal.value = true;
};

const openEditModal = (item: PaymentAccount) => {
    editingItem.value = item;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingItem.value = undefined;
};

// Search, filter & sort handlers
const handleMethodSelect = (methodId: string | number) => {
    if (String(methodFilter.value) === String(methodId)) {
        methodFilter.value = '';
    } else {
        methodFilter.value = methodId;
    }
};

const handleSort = (column: string) => {
    if (sortBy.value === column) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortDirection.value = column === 'balance' ? 'desc' : 'asc';
    }
    handleSearch();
};

const handleSearch = () => {
    router.get(
        '/organization/financial-profiles/payment-accounts',
        {
            search: searchQuery.value,
            active: activeFilter.value,
            method_id: methodFilter.value,
            sort_by: sortBy.value,
            sort_direction: sortDirection.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

// Debounced search
let searchTimeout: ReturnType<typeof setTimeout> | null = null;
watch(searchQuery, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        handleSearch();
    }, 500);
});

// Immediate search on filter change
watch([activeFilter, methodFilter], () => {
    handleSearch();
});

// Delete handlers
const confirmDelete = (item: PaymentAccount) => {
    deletingItem.value = item;
};

const deleteItem = () => {
    if (!deletingItem.value) return;

    router.delete(
        `/organization/financial-profiles/payment-accounts/${deletingItem.value.id}`,
        {
            preserveScroll: true,
            onSuccess: () => {
                deletingItem.value = undefined;
            },
        },
    );
};

const cancelDelete = () => {
    deletingItem.value = undefined;
};
</script>

<template>
    <Head title="Payment Accounts" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex w-full flex-1 flex-col overflow-hidden p-4">
            <!-- Compact Header -->
            <div class="mb-4 flex shrink-0 items-center justify-between gap-4">
                <div>
                    <h1
                        class="text-2xl font-black tracking-tight text-foreground uppercase"
                    >
                        Payment Accounts
                    </h1>
                    <p
                        class="text-[11px] font-bold tracking-wider text-muted-foreground uppercase"
                    >
                        Financial Liquidity & Balance Management
                    </p>
                </div>

                <button
                    @click="openCreateModal"
                    class="flex items-center gap-1.5 rounded-lg bg-white px-4 py-2 text-xs font-black text-black shadow-md transition-all hover:bg-gray-200 active:scale-95"
                >
                    <Plus class="h-3.5 w-3.5" />
                    <span>Create Account</span>
                </button>
            </div>

            <!-- Payment Method Summary Cards -->
            <PaymentMethodStats
                v-if="data.methodSummaries"
                :summaries="data.methodSummaries"
                :selected-method-id="methodFilter"
                @select-method="handleMethodSelect"
                class="mb-4"
            />

            <!-- Management Container -->
            <div
                class="flex flex-1 flex-col overflow-hidden rounded-xl border border-border bg-card/40 shadow-sm backdrop-blur-xl"
            >
                <!-- Tighter Filters Bar -->
                <div
                    class="flex items-center justify-between gap-4 border-b border-border bg-accent/5 p-3"
                >
                    <div class="relative w-full max-w-sm">
                        <Search
                            class="absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
                        />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search accounts..."
                            class="w-full rounded-lg border-border bg-background/50 py-1.5 pr-3 pl-8 text-xs font-medium transition-all focus:border-primary focus:ring-primary/20"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="w-48">
                            <SearchableSelect
                                v-model="methodFilter"
                                :options="[
                                    { id: '', name: 'All Methods' },
                                    ...data.methods,
                                ]"
                                label="name"
                                value-key="id"
                                placeholder="All Methods"
                                align="right"
                                class="h-8 text-[11px]"
                            />
                        </div>
                        <div class="w-40">
                            <SearchableSelect
                                v-model="activeFilter"
                                :options="statusOptions"
                                label="label"
                                value-key="id"
                                placeholder="All Status"
                                align="right"
                                class="h-8 text-[11px]"
                            />
                        </div>

                        <!-- Column Visibility Dropdown -->
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="h-8 gap-1.5 border-border bg-card/40 backdrop-blur-md"
                                >
                                    <SlidersHorizontal class="h-3.5 w-3.5 text-primary" />
                                    <span class="text-[10px] font-black tracking-widest uppercase">Columns</span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                align="end"
                                class="w-56 border border-border bg-card/90 backdrop-blur-xl"
                            >
                                <DropdownMenuLabel class="text-xs">Toggle Columns</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <div class="max-h-64 space-y-0.5 overflow-y-auto p-1">
                                    <DropdownMenuItem
                                        v-for="col in availableColumns"
                                        :key="col.key"
                                        as-child
                                        @select.prevent="toggleColumn(col.key)"
                                    >
                                        <button
                                            type="button"
                                            class="flex w-full cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-left text-xs transition-colors hover:bg-accent hover:text-accent-foreground focus:outline-none"
                                        >
                                            <span
                                                :class="[
                                                    'flex h-3.5 w-3.5 flex-shrink-0 items-center justify-center rounded border transition-all',
                                                    columnVisibility[col.key]
                                                        ? 'border-blue-600 bg-blue-600 text-white'
                                                        : 'border-slate-300 bg-white dark:border-slate-700 dark:bg-zinc-900',
                                                ]"
                                            >
                                                <svg
                                                    v-if="columnVisibility[col.key]"
                                                    class="h-2.5 w-2.5 text-white"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                    stroke-width="3.5"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M5 13l4 4L19 7"
                                                    />
                                                </svg>
                                            </span>
                                            <span
                                                class="truncate text-foreground/90 font-medium capitalize leading-none"
                                                >{{ col.label }}</span
                                            >
                                        </button>
                                    </DropdownMenuItem>
                                </div>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                <!-- Payment Accounts Table -->
                <PaymentAccountTable
                    :payment-accounts="data.paymentAccounts"
                    :sort-by="sortBy"
                    :sort-direction="sortDirection"
                    :column-visibility="columnVisibility"
                    @sort="handleSort"
                    @edit="openEditModal"
                    @delete="confirmDelete"
                />
            </div>
        </div>

        <!-- Payment Account Modal -->
        <PaymentAccountModal
            :show="showModal"
            :payment-account="editingItem"
            :methods="data.methods"
            :banks="data.banks"
            :companies="data.companies"
            :branches="data.branches"
            :users="data.users"
            :all-bank-accounts="data.allBankAccounts"
            :user-assignments="data.userAssignments"
            @close="closeModal"
        />

        <!-- Delete Confirmation Modal -->
        <div
            v-if="deletingItem"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
            @click.self="cancelDelete"
        >
            <div
                class="w-full max-w-md rounded-lg border border-border bg-card/95 p-6 shadow-xl"
            >
                <h3 class="text-lg font-semibold text-foreground">
                    Delete Payment Account
                </h3>
                <p class="mt-2 text-sm text-muted-foreground">
                    Are you sure you want to delete "{{ deletingItem.name }}"?
                    This action cannot be undone.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <button
                        @click="cancelDelete"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent/10 hover:text-foreground"
                    >
                        Cancel
                    </button>
                    <button
                        @click="deleteItem"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-red-700"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
