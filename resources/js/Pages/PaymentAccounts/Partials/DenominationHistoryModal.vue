<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { 
    Dialog, 
    DialogContent, 
    DialogHeader, 
    DialogTitle, 
    DialogDescription 
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { 
    Banknote, 
    ArrowDownRight, 
    ArrowUpRight, 
    RotateCcw, 
    Search, 
    Calendar, 
    Download, 
    RefreshCw, 
    X, 
    ShieldCheck, 
    User as UserIcon, 
    Clock, 
    FileText,
    ExternalLink,
    Filter,
    ChevronLeft,
    ChevronRight
} from 'lucide-vue-next';
import flatPickr from 'vue-flatpickr-component';
import 'flatpickr/dist/flatpickr.css';
import { format } from 'date-fns';

const props = defineProps<{
    open: boolean;
    paymentAccount: any;
    denomination: any;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const isOpen = computed({
    get: () => props.open,
    set: (val: boolean) => emit('update:open', val)
});

const isLoading = ref(false);
const error = ref<string | null>(null);
const historyData = ref<any>(null);

// Filters
const searchQuery = ref('');
const selectedType = ref<'all' | 'in' | 'out' | 'adjustment'>('all');
const selectedDateRange = ref('');
const currentPage = ref(1);
const perPage = ref(25);

const flatpickrConfig = {
    mode: 'range' as const,
    dateFormat: 'Y-m-d',
    disableMobile: true,
};

const formatCurrency = (val: number | string | null | undefined) => {
    const num = Number(val) || 0;
    return (props.paymentAccount?.currency || 'KES') + ' ' + num.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

const formatDate = (date: any, formatStr: string) => {
    if (!date) return '—';
    const parsed = new Date(date);
    if (isNaN(parsed.getTime())) return '—';
    return format(parsed, formatStr);
};

const fetchHistory = async () => {
    if (!props.paymentAccount?.id || !props.denomination?.denomination_id) return;
    
    isLoading.value = true;
    error.value = null;

    try {
        const denomId = props.denomination.denomination_id;
        const response = await fetch(
            `/organization/financial-profiles/payment-accounts/${props.paymentAccount.id}/denominations/${denomId}/history`
        );
        
        if (!response.ok) {
            throw new Error(`Server returned ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();
        historyData.value = data;
        currentPage.value = 1;
    } catch (e: any) {
        error.value = e?.message || 'Failed to load denomination history.';
    } finally {
        isLoading.value = false;
    }
};

watch(() => [props.open, props.denomination], ([newOpen]) => {
    if (newOpen) {
        searchQuery.value = '';
        selectedType.value = 'all';
        selectedDateRange.value = '';
        currentPage.value = 1;
        fetchHistory();
    }
});

// Movements filtering
const filteredMovements = computed(() => {
    if (!historyData.value?.movements) return [];
    let items = historyData.value.movements as any[];

    // Filter by type
    if (selectedType.value === 'in') {
        items = items.filter((m) => m.qty_in > 0 && m.type !== 'adjustment');
    } else if (selectedType.value === 'out') {
        items = items.filter((m) => m.qty_out > 0 && m.type !== 'adjustment');
    } else if (selectedType.value === 'adjustment') {
        items = items.filter((m) => m.type === 'adjustment' || m.type === 'opening_balance');
    }

    // Filter by date range
    if (selectedDateRange.value) {
        const parts = selectedDateRange.value.split(' to ');
        const start = parts[0]?.trim();
        const end = parts[1]?.trim() || start;
        if (start) {
            items = items.filter((m) => m.date >= `${start} 00:00:00`);
        }
        if (end) {
            items = items.filter((m) => m.date <= `${end} 23:59:59`);
        }
    }

    // Filter by search query
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim();
        items = items.filter((m) => 
            (m.reference && m.reference.toLowerCase().includes(q)) ||
            (m.counterparty && m.counterparty.toLowerCase().includes(q)) ||
            (m.performer && m.performer.toLowerCase().includes(q)) ||
            (m.approver && m.approver.toLowerCase().includes(q)) ||
            (m.notes && m.notes.toLowerCase().includes(q))
        );
    }

    return items;
});

// Pagination
const totalItems = computed(() => filteredMovements.value.length);
const totalPages = computed(() => Math.ceil(totalItems.value / perPage.value) || 1);

const paginatedMovements = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    return filteredMovements.value.slice(start, start + perPage.value);
});

const countsByType = computed(() => {
    if (!historyData.value?.movements) return { in: 0, out: 0, adj: 0, all: 0 };
    const all = historyData.value.movements as any[];
    return {
        all: all.length,
        in: all.filter((m) => m.qty_in > 0 && m.type !== 'adjustment').length,
        out: all.filter((m) => m.qty_out > 0 && m.type !== 'adjustment').length,
        adj: all.filter((m) => m.type === 'adjustment' || m.type === 'opening_balance').length,
    };
});

const exportToCsv = () => {
    if (!filteredMovements.value.length) return;

    const denomVal = props.denomination?.denomination?.value || 0;
    const denomLabel = `${props.paymentAccount?.currency || 'KES'} ${Number(denomVal).toLocaleString()}`;
    const filename = `${props.paymentAccount?.name || 'Vault'}_${denomLabel.replace(/\s+/g, '_')}_history.csv`;

    const headers = [
        'Reference',
        'Date',
        'Time',
        'Movement Type',
        'Counterparty / Channel',
        'Inflow (Notes)',
        'Outflow (Notes)',
        'Running Note Balance',
        'Running Total Value',
        'Initiated By',
        'Approved By',
        'Notes'
    ];

    const rows = filteredMovements.value.map((m) => [
        `"${m.reference || ''}"`,
        `"${formatDate(m.date, 'yyyy-MM-dd')}"`,
        `"${formatDate(m.date, 'HH:mm')}"`,
        `"${m.type_label || m.type || ''}"`,
        `"${m.counterparty || ''}"`,
        m.qty_in || 0,
        m.qty_out || 0,
        m.running_quantity || 0,
        m.running_value || 0,
        `"${m.performer || ''}"`,
        `"${m.approver || ''}"`,
        `"${(m.notes || '').replace(/"/g, '""')}"`
    ]);

    const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map((e) => e.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};
</script>

<template>
    <Dialog :open="isOpen" @update:open="(val) => emit('update:open', val)">
        <DialogContent 
            class="flex flex-col gap-0 p-0 overflow-hidden bg-background border-border/80 shadow-2xl w-[96vw] sm:w-[96vw] sm:max-w-[96vw] xl:max-w-[1550px] max-h-[92vh]"
        >
            <!-- Header -->
            <DialogHeader class="px-3.5 py-1.5 border-b border-border bg-muted/20 flex flex-row items-center justify-between shrink-0 pr-12">
                <div class="flex items-center gap-2">
                    <div class="h-6 w-6 rounded bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 flex items-center justify-center shrink-0 shadow-2xs">
                        <Banknote class="h-3.5 w-3.5" />
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5">
                            <DialogTitle class="text-[12px] font-black tracking-tight text-foreground flex items-center gap-1.5 leading-none">
                                <span>{{ denomination?.denomination?.value ? `${paymentAccount.currency} ${Math.round(denomination.denomination.value).toLocaleString()} Note Flow History` : 'Denomination Flow History' }}</span>
                            </DialogTitle>
                            <span class="rounded bg-emerald-500/10 border border-emerald-500/20 px-1 py-0.2 text-[8px] font-black text-emerald-600 uppercase tracking-wide">
                                Single Source of Truth
                            </span>
                        </div>
                        <DialogDescription class="text-[9.5px] text-muted-foreground mt-0.5 flex items-center gap-1.5 leading-none">
                            <span>Account: <strong class="text-foreground font-bold">{{ paymentAccount?.name }}</strong></span>
                            <span class="opacity-40">•</span>
                            <span>Live Count: <strong class="text-emerald-600 font-black">{{ (historyData?.current_quantity ?? denomination?.quantity ?? 0).toLocaleString() }} notes</strong></span>
                            <span class="opacity-40">•</span>
                            <span>Subtotal: <strong class="text-foreground font-black">{{ formatCurrency(historyData?.current_value ?? ((denomination?.quantity || 0) * (denomination?.denomination?.value || 0))) }}</strong></span>
                        </DialogDescription>
                    </div>
                </div>

                <div class="flex items-center gap-1">
                    <Button 
                        size="sm" 
                        variant="outline" 
                        class="h-6 px-2 text-[9.5px] font-bold text-muted-foreground hover:text-foreground border-border"
                        :disabled="isLoading || !filteredMovements.length"
                        @click="exportToCsv"
                    >
                        <Download class="h-2.5 w-2.5 mr-1" />
                        Export CSV
                    </Button>
                    <Button 
                        size="sm" 
                        variant="outline" 
                        class="h-6 px-1.5 text-[9.5px] font-bold text-muted-foreground hover:text-foreground border-border"
                        :disabled="isLoading"
                        @click="fetchHistory"
                        title="Reload history"
                    >
                        <RefreshCw :class="['h-2.5 w-2.5', isLoading ? 'animate-spin' : '']" />
                    </Button>
                </div>
            </DialogHeader>

            <!-- Compact KPI Cards Overview -->
            <div class="px-3.5 py-1.5 bg-muted/10 border-b border-border grid grid-cols-2 md:grid-cols-4 gap-2 shrink-0">
                <div class="rounded border border-border/80 bg-card px-2.5 py-1 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[8px] font-black tracking-widest text-muted-foreground uppercase">Total Inflow</span>
                        <div class="h-4 w-4 rounded bg-emerald-500/10 text-emerald-600 flex items-center justify-center">
                            <ArrowUpRight class="h-2.5 w-2.5" />
                        </div>
                    </div>
                    <div class="text-[11px] font-black text-emerald-600 tabular-nums leading-tight mt-0.5">
                        +{{ (historyData?.summary?.total_in_quantity || 0).toLocaleString() }} <span class="text-[8px] text-muted-foreground font-bold uppercase">notes</span>
                    </div>
                    <div class="text-[8.5px] text-muted-foreground font-semibold tabular-nums leading-none">
                        {{ formatCurrency(historyData?.summary?.total_in_value || 0) }}
                    </div>
                </div>

                <div class="rounded border border-border/80 bg-card px-2.5 py-1 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[8px] font-black tracking-widest text-muted-foreground uppercase">Total Outflow</span>
                        <div class="h-4 w-4 rounded bg-rose-500/10 text-rose-600 flex items-center justify-center">
                            <ArrowDownRight class="h-2.5 w-2.5" />
                        </div>
                    </div>
                    <div class="text-[11px] font-black text-rose-600 tabular-nums leading-tight mt-0.5">
                        -{{ (historyData?.summary?.total_out_quantity || 0).toLocaleString() }} <span class="text-[8px] text-muted-foreground font-bold uppercase">notes</span>
                    </div>
                    <div class="text-[8.5px] text-muted-foreground font-semibold tabular-nums leading-none">
                        {{ formatCurrency(historyData?.summary?.total_out_value || 0) }}
                    </div>
                </div>

                <div class="rounded border border-border/80 bg-card px-2.5 py-1 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[8px] font-black tracking-widest text-muted-foreground uppercase">Net Movement</span>
                        <div class="h-4 w-4 rounded bg-blue-500/10 text-blue-600 flex items-center justify-center">
                            <Banknote class="h-2.5 w-2.5" />
                        </div>
                    </div>
                    <div :class="['text-[11px] font-black tabular-nums leading-tight mt-0.5', (historyData?.summary?.net_quantity || 0) >= 0 ? 'text-foreground' : 'text-rose-600']">
                        {{ (historyData?.summary?.net_quantity || 0) >= 0 ? '+' : '' }}{{ (historyData?.summary?.net_quantity || 0).toLocaleString() }} <span class="text-[8px] text-muted-foreground font-bold uppercase">notes</span>
                    </div>
                    <div class="text-[8.5px] text-muted-foreground font-semibold tabular-nums leading-none">
                        {{ formatCurrency(historyData?.summary?.net_value || 0) }}
                    </div>
                </div>

                <div class="rounded border border-border/80 bg-card px-2.5 py-1 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[8px] font-black tracking-widest text-muted-foreground uppercase">Baseline Audit</span>
                        <div class="h-4 w-4 rounded bg-amber-500/10 text-amber-600 flex items-center justify-center">
                            <RotateCcw class="h-2.5 w-2.5" />
                        </div>
                    </div>
                    <div class="text-[10px] font-black text-foreground truncate leading-tight mt-0.5">
                        {{ historyData?.summary?.baseline_adjustment?.reference || 'Initial Opening' }}
                    </div>
                    <div class="text-[8.5px] text-muted-foreground font-semibold truncate leading-none">
                        {{ historyData?.summary?.baseline_adjustment?.date ? formatDate(historyData.summary.baseline_adjustment.date, 'MMM dd, yyyy') : 'No Adjustments' }}
                        <span v-if="historyData?.summary?.baseline_adjustment?.quantity !== undefined">
                            ({{ (historyData.summary.baseline_adjustment.quantity || 0).toLocaleString() }} notes)
                        </span>
                    </div>
                </div>
            </div>

            <!-- Controls: Search, Type Tabs & Date Picker -->
            <div class="px-3.5 py-1 border-b border-border bg-background flex flex-col sm:flex-row items-center justify-between gap-1.5 shrink-0">
                <!-- Type Filter Pills -->
                <div class="flex items-center gap-0.5 p-0.5 bg-muted/40 border border-border/70 rounded w-full sm:w-auto">
                    <button 
                        type="button" 
                        @click="selectedType = 'all'; currentPage = 1"
                        :class="[
                            'px-2 py-0.5 text-[9px] font-bold rounded transition-all',
                            selectedType === 'all' 
                                ? 'bg-background text-foreground shadow-2xs' 
                                : 'text-muted-foreground hover:text-foreground'
                        ]"
                    >
                        All Movements ({{ countsByType.all }})
                    </button>
                    <button 
                        type="button" 
                        @click="selectedType = 'in'; currentPage = 1"
                        :class="[
                            'px-2 py-0.5 text-[9px] font-bold rounded transition-all flex items-center gap-1',
                            selectedType === 'in' 
                                ? 'bg-background text-emerald-600 shadow-2xs' 
                                : 'text-muted-foreground hover:text-emerald-600'
                        ]"
                    >
                        <ArrowUpRight class="h-2 w-2" />
                        Inflow ({{ countsByType.in }})
                    </button>
                    <button 
                        type="button" 
                        @click="selectedType = 'out'; currentPage = 1"
                        :class="[
                            'px-2 py-0.5 text-[9px] font-bold rounded transition-all flex items-center gap-1',
                            selectedType === 'out' 
                                ? 'bg-background text-rose-600 shadow-2xs' 
                                : 'text-muted-foreground hover:text-rose-600'
                        ]"
                    >
                        <ArrowDownRight class="h-2 w-2" />
                        Outflow ({{ countsByType.out }})
                    </button>
                    <button 
                        type="button" 
                        @click="selectedType = 'adjustment'; currentPage = 1"
                        :class="[
                            'px-2 py-0.5 text-[9px] font-bold rounded transition-all flex items-center gap-1',
                            selectedType === 'adjustment' 
                                ? 'bg-background text-amber-600 shadow-2xs' 
                                : 'text-muted-foreground hover:text-amber-600'
                        ]"
                    >
                        <RotateCcw class="h-2 w-2" />
                        Audits ({{ countsByType.adj }})
                    </button>
                </div>

                <!-- Search & Date Filter -->
                <div class="flex items-center gap-1.5 w-full sm:w-auto">
                    <div class="relative flex-1 sm:w-52">
                        <Search class="absolute left-2 top-1.5 h-2.5 w-2.5 text-muted-foreground" />
                        <Input 
                            v-model="searchQuery" 
                            type="text" 
                            placeholder="Filter by Ref, Drawer, User..." 
                            class="h-6 pl-6 pr-5 text-[9.5px] bg-card border-border py-0"
                        />
                        <button 
                            v-if="searchQuery" 
                            @click="searchQuery = ''"
                            class="absolute right-1.5 top-1 text-muted-foreground hover:text-foreground"
                        >
                            <X class="h-2.5 w-2.5" />
                        </button>
                    </div>

                    <div class="relative w-40">
                        <flatPickr
                            v-model="selectedDateRange"
                            :config="flatpickrConfig"
                            placeholder="Filter dates..."
                            class="flex h-6 w-full rounded border border-input bg-card px-2 py-0 text-[9.5px] shadow-2xs transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        />
                        <button 
                            v-if="selectedDateRange" 
                            @click="selectedDateRange = ''"
                            class="absolute right-1.5 top-1 text-muted-foreground hover:text-foreground"
                        >
                            <X class="h-2.5 w-2.5" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Table Container -->
            <div class="flex-1 overflow-auto min-h-[300px]">
                <!-- Loading State -->
                <div v-if="isLoading" class="flex flex-col items-center justify-center py-16 gap-2">
                    <RefreshCw class="h-5 w-5 animate-spin text-emerald-600" />
                    <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-widest">
                        Reconstructing note movement audit history...
                    </p>
                </div>

                <!-- Error State -->
                <div v-else-if="error" class="flex flex-col items-center justify-center py-12 px-4 gap-2 text-center">
                    <p class="text-[10px] font-bold text-rose-600">{{ error }}</p>
                    <Button size="sm" variant="outline" @click="fetchHistory" class="mt-2 h-6 text-[10px]">
                        Try Again
                    </Button>
                </div>

                <!-- Empty State -->
                <div v-else-if="!paginatedMovements.length" class="flex flex-col items-center justify-center py-16 text-center px-4">
                    <Banknote class="h-7 w-7 text-muted-foreground/40 mb-1.5" />
                    <p class="text-[10px] font-black text-foreground uppercase tracking-wider">No Denomination Movements Found</p>
                    <p class="text-[9.5px] text-muted-foreground mt-0.5">
                        {{ searchQuery || selectedDateRange || selectedType !== 'all' ? 'Try adjusting your filters or date range.' : 'No transfers or adjustments have been recorded for this note denomination yet.' }}
                    </p>
                </div>

                <!-- Data Table -->
                <table v-else class="w-full text-left border-collapse">
                    <thead class="sticky top-0 bg-muted/60 z-10 backdrop-blur-xs">
                        <tr>
                            <th class="border-b border-border px-2.5 py-1 text-left text-[8px] font-black text-muted-foreground uppercase whitespace-nowrap">
                                Ref #
                            </th>
                            <th class="border-b border-border px-2.5 py-1 text-left text-[8px] font-black text-muted-foreground uppercase whitespace-nowrap">
                                Date & Time
                            </th>
                            <th class="border-b border-border px-2.5 py-1 text-left text-[8px] font-black text-muted-foreground uppercase whitespace-nowrap">
                                Channel
                            </th>
                            <th class="border-b border-border px-2.5 py-1 text-left text-[8px] font-black text-muted-foreground uppercase whitespace-nowrap">
                                Counterparty / Origin / Destination
                            </th>
                            <th class="border-b border-border px-2.5 py-1 text-left text-[8px] font-black text-muted-foreground uppercase whitespace-nowrap">
                                Remarks / Notes
                            </th>
                            <th class="border-b border-border px-2.5 py-1 text-right text-[8px] font-black text-muted-foreground uppercase whitespace-nowrap">
                                In (+Notes)
                            </th>
                            <th class="border-b border-border px-2.5 py-1 text-right text-[8px] font-black text-muted-foreground uppercase whitespace-nowrap">
                                Out (-Notes)
                            </th>
                            <th class="border-b border-border px-2.5 py-1 text-right text-[8px] font-black text-muted-foreground uppercase whitespace-nowrap">
                                Running Count
                            </th>
                            <th class="border-b border-border px-2.5 py-1 text-right text-[8px] font-black text-muted-foreground uppercase whitespace-nowrap">
                                Running Value
                            </th>
                            <th class="border-b border-border px-2.5 py-1 text-left text-[8px] font-black text-muted-foreground uppercase whitespace-nowrap">
                                Authorized By
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-[9.5px]">
                        <tr 
                            v-for="log in paginatedMovements" 
                            :key="log.id"
                            :class="[
                                'transition-colors',
                                log.type === 'adjustment' 
                                    ? 'bg-amber-500/10 dark:bg-amber-500/15 border-l-4 border-l-amber-500 hover:bg-amber-500/20' 
                                    : log.qty_in > 0 
                                        ? 'hover:bg-emerald-500/5' 
                                        : 'hover:bg-rose-500/5'
                            ]"
                        >
                            <!-- Ref # -->
                            <td class="px-2.5 py-1 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 rounded border border-border/70 bg-muted/30 px-1 py-0.2 text-[8.5px] font-black tracking-tighter uppercase whitespace-nowrap">
                                    <template v-if="log.type === 'adjustment'">
                                        <a :href="`/organization/adjustments/payment`" target="_blank" class="text-amber-700 dark:text-amber-400 hover:underline flex items-center gap-0.5">
                                            {{ log.reference }}
                                            <ExternalLink class="h-2 w-2" />
                                        </a>
                                    </template>
                                    <template v-else-if="log.reference_type === 'transfer'">
                                        <a :href="`/organization/financial-profiles/payment-accounts/${paymentAccount.id}?tab=transfers&search=${log.reference}`" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-0.5">
                                            {{ log.reference }}
                                            <ExternalLink class="h-2 w-2" />
                                        </a>
                                    </template>
                                    <template v-else>
                                        {{ log.reference }}
                                    </template>
                                </span>
                            </td>

                            <!-- Date & Time -->
                            <td class="px-2.5 py-1 whitespace-nowrap leading-tight">
                                <div class="text-[9px] font-bold text-foreground">
                                    {{ formatDate(log.date, 'MMM dd, yyyy') }}
                                </div>
                                <div class="text-[8px] font-medium text-muted-foreground uppercase opacity-75">
                                    {{ formatDate(log.date, 'HH:mm') }}
                                </div>
                            </td>

                            <!-- Movement Channel Pill -->
                            <td class="px-2.5 py-1 whitespace-nowrap">
                                <span 
                                    :class="[
                                        'inline-flex items-center gap-1 rounded px-1.5 py-0.2 text-[8px] font-black tracking-wider uppercase border leading-none',
                                        log.type === 'adjustment' 
                                            ? 'bg-amber-500/10 text-amber-600 border-amber-500/30' 
                                            : log.type === 'transfer_in' 
                                                ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/30' 
                                                : log.type === 'transfer_out' 
                                                    ? 'bg-rose-500/10 text-rose-600 border-rose-500/30' 
                                                    : 'bg-blue-500/10 text-blue-600 border-blue-500/30'
                                    ]"
                                >
                                    <RotateCcw v-if="log.type === 'adjustment'" class="h-2 w-2" />
                                    <ArrowUpRight v-else-if="log.type === 'transfer_in'" class="h-2 w-2" />
                                    <ArrowDownRight v-else-if="log.type === 'transfer_out'" class="h-2 w-2" />
                                    <Banknote v-else class="h-2 w-2" />
                                    {{ log.type_label }}
                                </span>
                            </td>

                            <!-- Counterparty / Channel Details -->
                            <td class="px-2.5 py-1 max-w-[240px] truncate">
                                <span class="text-[9.5px] font-bold text-foreground" :title="log.counterparty">
                                    {{ log.counterparty }}
                                </span>
                            </td>

                            <!-- Remarks / Notes -->
                            <td class="px-2.5 py-1 max-w-[240px] truncate">
                                <span v-if="log.notes" class="text-[8.5px] text-muted-foreground italic truncate block" :title="log.notes">
                                    {{ log.notes }}
                                </span>
                                <span v-else class="text-muted-foreground/30 text-[8px] font-mono">—</span>
                            </td>

                            <!-- Qty In -->
                            <td class="px-2.5 py-1 text-right whitespace-nowrap tabular-nums">
                                <span v-if="log.qty_in > 0" class="font-black text-emerald-600 text-[9.5px]">
                                    +{{ Number(log.qty_in).toLocaleString() }}
                                </span>
                                <span v-else class="text-muted-foreground/30 font-mono text-[8.5px]">—</span>
                            </td>

                            <!-- Qty Out -->
                            <td class="px-2.5 py-1 text-right whitespace-nowrap tabular-nums">
                                <span v-if="log.qty_out > 0" class="font-black text-rose-600 text-[9.5px]">
                                    -{{ Number(log.qty_out).toLocaleString() }}
                                </span>
                                <span v-else class="text-muted-foreground/30 font-mono text-[8.5px]">—</span>
                            </td>

                            <!-- Running Count -->
                            <td class="px-2.5 py-1 text-right whitespace-nowrap tabular-nums">
                                <span class="font-black text-foreground text-[9.5px]">
                                    {{ Number(log.running_quantity || 0).toLocaleString() }}
                                </span>
                                <span class="text-[8px] font-bold text-muted-foreground uppercase ml-0.5">notes</span>
                            </td>

                            <!-- Running Value -->
                            <td class="px-2.5 py-1 text-right whitespace-nowrap tabular-nums">
                                <span class="font-black text-foreground text-[9.5px]">
                                    {{ formatCurrency(log.running_value) }}
                                </span>
                            </td>

                            <!-- Authorized By -->
                            <td class="px-2.5 py-1 whitespace-nowrap leading-tight">
                                <div class="flex items-center gap-1 text-[9px] font-bold text-foreground">
                                    <UserIcon class="h-2 w-2 text-muted-foreground" />
                                    <span>{{ log.performer || 'System' }}</span>
                                </div>
                                <div v-if="log.approver" class="text-[8px] text-muted-foreground pl-3">
                                    Approved: {{ log.approver }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer: Pagination & Total Summary -->
            <div class="px-3.5 py-1 border-t border-border bg-muted/20 flex flex-col sm:flex-row items-center justify-between gap-1.5 shrink-0 text-[9.5px] text-muted-foreground">
                <div class="flex items-center gap-1.5">
                    <span>Showing <strong class="text-foreground">{{ paginatedMovements.length ? (currentPage - 1) * perPage + 1 : 0 }}</strong> to <strong class="text-foreground">{{ Math.min(currentPage * perPage, totalItems) }}</strong> of <strong class="text-foreground">{{ totalItems }}</strong> records</span>
                    <span v-if="selectedType !== 'all' || searchQuery || selectedDateRange" class="text-[8.5px] font-bold text-emerald-600 uppercase">
                        (Filtered)
                    </span>
                </div>

                <div v-if="totalPages > 1" class="flex items-center gap-1">
                    <Button 
                        size="sm" 
                        variant="outline" 
                        class="h-5 px-1.5 text-[9px]" 
                        :disabled="currentPage <= 1"
                        @click="currentPage--"
                    >
                        <ChevronLeft class="h-2.5 w-2.5 mr-0.5" />
                        Prev
                    </Button>
                    <span class="text-[9px] font-bold text-foreground px-1">
                        Page {{ currentPage }} of {{ totalPages }}
                    </span>
                    <Button 
                        size="sm" 
                        variant="outline" 
                        class="h-5 px-1.5 text-[9px]" 
                        :disabled="currentPage >= totalPages"
                        @click="currentPage++"
                    >
                        Next
                        <ChevronRight class="h-2.5 w-2.5 ml-0.5" />
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
