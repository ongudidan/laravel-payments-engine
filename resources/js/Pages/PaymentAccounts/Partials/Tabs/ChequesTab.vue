<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import { QzPrinterService } from '@/services/qzPrinter';
import { router } from '@inertiajs/vue3';
import 'flatpickr/dist/flatpickr.css';
import debounce from 'lodash/debounce';
import { Calendar, Search, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import FlatPickr from 'vue-flatpickr-component';
import ChequeListHeader from './Cheques/ChequeListHeader.vue';
import ChequeListTable from './Cheques/ChequeListTable.vue';
import CancellationModal from './Cheques/Modals/CancellationModal.vue';
import ChequeDetailsModal from './Cheques/Modals/ChequeDetailsModal.vue';
import ClearingModal from './Cheques/Modals/ClearingModal.vue';
import CollectionModal from './Cheques/Modals/CollectionModal.vue';

const props = defineProps<{
    account: any;
    cheques: any;
    futureLiabilities: any[];
    filters: {
        cheque_direction?: string;
        cheque_search?: string;
        cheque_status?: string;
        cheque_date_range?: string;
        settlement_date?: string;
    };
}>();

const chequeSearch = ref(props.filters.cheque_search || '');
const chequeStatus = ref(props.filters.cheque_status || '');
const chequeDateRange = ref(props.filters.cheque_date_range || '');

const statusOptions = [
    { id: '', label: 'All Statuses' },
    { id: 'Pending', label: 'Pending' },
    { id: 'Collected', label: 'Collected' },
    { id: 'Cleared', label: 'Cleared' },
    { id: 'Cancelled', label: 'Cancelled' },
];

const updateTabFilters = debounce(() => {
    router.get(
        window.location.pathname,
        {
            tab: 'cheques',
            cheque_direction: props.filters.cheque_direction || '',
            cheque_search: chequeSearch.value,
            cheque_status: chequeStatus.value,
            cheque_date_range: chequeDateRange.value,
            settlement_date: props.filters.settlement_date || '',
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}, 300);

watch([chequeSearch, chequeStatus], () => {
    updateTabFilters();
});

const dateRangeConfig = {
    mode: 'range' as const,
    dateFormat: 'Y-m-d',
    altInput: true,
    altFormat: 'F j, Y',
    disableMobile: true,
    onChange: (selectedDates: Date[], dateStr: string, instance: any) => {
        if (selectedDates.length === 2) {
            const d0 = instance.formatDate(selectedDates[0], 'Y-m-d');
            const d1 = instance.formatDate(selectedDates[1], 'Y-m-d');
            const fullRange = `${d0} to ${d1}`;
            chequeDateRange.value = fullRange;
            updateTabFilters();
        }
    },
    onClose: (selectedDates: Date[], dateStr: string, instance: any) => {
        if (selectedDates.length === 1) {
            const d = instance.formatDate(selectedDates[0], 'Y-m-d');
            const fullRange = `${d} to ${d}`;
            instance.setDate([selectedDates[0], selectedDates[0]], false);
            chequeDateRange.value = fullRange;
            updateTabFilters();
        } else if (
            selectedDates.length === 2 &&
            instance.formatDate(selectedDates[0], 'Y-m-d') === instance.formatDate(selectedDates[1], 'Y-m-d')
        ) {
            const d = instance.formatDate(selectedDates[0], 'Y-m-d');
            const fullRange = `${d} to ${d}`;
            chequeDateRange.value = fullRange;
        }
    },
    onReady: (selectedDates: Date[], dateStr: string, instance: any) => {
        if (chequeDateRange.value) {
            let val = chequeDateRange.value.trim();
            if (!val.includes(' to ')) {
                val = `${val} to ${val}`;
            }
            if (instance.input) {
                instance.input.value = val;
            }
        }
    },
};

const clearDateRange = () => {
    chequeDateRange.value = '';
    updateTabFilters();
};

const formatCurrency = (amount: string | number, currency: string) => {
    return new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: currency,
    }).format(Number(amount));
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const printerService = QzPrinterService.getInstance();

const clearingCheque = ref<any>(null);
const cancellingCheque = ref<any>(null);
const collectingCheque = ref<any>(null);
const selectedCheque = ref<any>(null);
const showDetailsModal = ref(false);
const processing = ref(false);

const openDetailsModal = (cheque: any) => {
    selectedCheque.value = cheque;
    showDetailsModal.value = true;
};

const clearCheque = (transactionNumber: string) => {
    if (!clearingCheque.value || processing.value) return;

    processing.value = true;
    router.post(
        `/organization/purchases/cheque-clearances/${clearingCheque.value.id}/mark-as-cleared`,
        {
            transaction_number: transactionNumber,
        },
        {
            onSuccess: () => {
                clearingCheque.value = null;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};

const cancelCheque = (reason: string) => {
    if (!cancellingCheque.value || !reason || processing.value) return;

    processing.value = true;
    router.post(
        `/organization/purchases/cheque-clearances/${cancellingCheque.value.id}/mark-as-cancelled`,
        {
            reason: reason,
        },
        {
            onSuccess: () => {
                cancellingCheque.value = null;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};

const markAsCollected = (formData: any) => {
    if (
        !collectingCheque.value ||
        !formData.collected_by_name?.trim() ||
        !formData.collected_by_mobile?.trim() ||
        processing.value
    )
        return;

    processing.value = true;
    const chequeId = collectingCheque.value.id;
    router.post(
        `/organization/purchases/cheque-clearances/${chequeId}/mark-as-collected`,
        formData,
        {
            onSuccess: () => {
                collectingCheque.value = null;
                const printUrl = `/organization/finance/cheques/${chequeId}/print`;
                printerService.print(printUrl, "thermal").catch((err) => {
                    console.error("Auto-print failed:", err);
                });
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};
</script>

<template>
    <div class="flex h-full flex-col overflow-hidden">
        <ChequeListHeader
            :account="account"
            :future-liabilities="futureLiabilities"
            :filters="filters"
            :format-currency="formatCurrency"
        />

        <div
            class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-border bg-orange-500/5 px-6 py-1.5"
        >
            <span
                class="text-[9px] font-black tracking-widest text-muted-foreground/60 uppercase"
            >
                Showing {{ cheques.from || 1 }}-{{
                    cheques.to || (cheques.data || cheques).length
                }}
                of
                {{ cheques.total || (cheques.data || cheques).length }} Entries
            </span>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Search Name / Payee Input -->
                <div class="relative w-44 sm:w-56">
                    <Search
                        class="absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground/50"
                    />
                    <input
                        v-model="chequeSearch"
                        type="text"
                        placeholder="Search name or info..."
                        class="h-7 w-full rounded-md border border-border bg-background pr-6 pl-8 text-[10px] font-medium transition-all outline-none placeholder:text-muted-foreground/50 focus:ring-1 focus:ring-primary/25"
                    />
                    <button
                        v-if="chequeSearch"
                        @click="chequeSearch = ''"
                        class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded-full p-0.5 text-muted-foreground/60 transition-all hover:bg-muted-foreground/10 hover:text-foreground"
                    >
                        <X class="h-3 w-3" />
                    </button>
                </div>

                <!-- Date Range Picker -->
                <div class="relative flex items-center w-44">
                    <Calendar
                        class="pointer-events-none absolute top-1/2 left-2 z-10 h-3 w-3 -translate-y-1/2 text-muted-foreground/60"
                    />
                    <FlatPickr
                        v-model="chequeDateRange"
                        :config="dateRangeConfig"
                        class="h-7 w-full cursor-pointer rounded-md border border-border bg-background pr-6 pl-7 text-[10px] font-medium transition-all outline-none placeholder:text-muted-foreground/50 focus:ring-1 focus:ring-primary/25"
                        placeholder="Date range..."
                    />
                    <button
                        v-if="chequeDateRange"
                        @click="clearDateRange"
                        class="absolute right-1.5 z-20 cursor-pointer rounded-full p-0.5 text-muted-foreground/60 transition-all hover:bg-muted-foreground/10 hover:text-foreground"
                    >
                        <X class="h-3 w-3" />
                    </button>
                </div>

                <!-- Status Filter Dropdown -->
                <div class="w-36">
                    <SearchableSelect
                        v-model="chequeStatus"
                        :options="statusOptions"
                        label="label"
                        value-key="id"
                        placeholder="All Statuses"
                        class="h-7 min-h-0 py-0 text-[10px] font-medium"
                    />
                </div>
            </div>
        </div>

        <ChequeListTable
            :cheques="cheques"
            :account="account"
            :format-currency="formatCurrency"
            :format-date="formatDate"
            @collect="collectingCheque = $event"
            @clear="clearingCheque = $event"
            @cancel="cancellingCheque = $event"
            @view-details="openDetailsModal"
        />

        <!-- Footer Pagination -->
        <div
            v-if="cheques.links && cheques.links.length > 3"
            class="shrink-0 border-t border-border bg-card/60 px-4 py-3"
        >
            <Pagination
                :links="cheques.links"
                :from="cheques.from"
                :to="cheques.to"
                :total="cheques.total"
            />
        </div>

        <ClearingModal
            :cheque="clearingCheque"
            :account="account"
            :processing="processing"
            :format-currency="formatCurrency"
            @close="clearingCheque = null"
            @confirm="clearCheque"
        />

        <CancellationModal
            :cheque="cancellingCheque"
            :processing="processing"
            @close="cancellingCheque = null"
            @confirm="cancelCheque"
        />

        <CollectionModal
            :cheque="collectingCheque"
            :processing="processing"
            @close="collectingCheque = null"
            @confirm="markAsCollected"
        />

        <ChequeDetailsModal
            :show="showDetailsModal"
            :cheque="selectedCheque"
            :account="account"
            :format-currency="formatCurrency"
            :format-date="formatDate"
            @close="showDetailsModal = false"
        />
    </div>
</template>
