<script setup lang="ts">
import Pagination from '../../../../Components/Pagination.vue';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    ArrowRight,
    Banknote,
    Boxes,
    CheckCircle2,
    Clock,
    Coins,
    Eye,
    FileSpreadsheet,
    Landmark,
    Layers,
    Printer,
    Search,
    ShieldCheck,
    TrendingUp,
    UserCheck,
    Vault,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

import { QzPrinterService } from '../../../../Services/qzPrinter';

const props = defineProps<{
    account: any;
    vaultBatches: any;
}>();

const searchQuery = ref('');
const statusFilter = ref('all');
const selectedBatch = ref<any>(null);
const showViewModal = ref(false);
const isPrinting = ref(false);

const filteredBatches = computed(() => {
    if (!props.vaultBatches?.data) return [];
    return props.vaultBatches.data.filter((batch: any) => {
        // Search filter
        const query = searchQuery.value.toLowerCase().trim();
        const matchesSearch =
            !query ||
            batch.reference?.toLowerCase().includes(query) ||
            batch.performer?.name?.toLowerCase().includes(query) ||
            batch.notes?.toLowerCase().includes(query) ||
            (batch.forwarded_transfers &&
                batch.forwarded_transfers.some((sub: any) =>
                    sub.reference?.toLowerCase().includes(query) ||
                    sub.performer?.name?.toLowerCase().includes(query) ||
                    sub.notes?.toLowerCase().includes(query)
                ));

        // Status filter
        let matchesStatus = true;
        if (statusFilter.value === 'available') {
            matchesStatus =
                String(batch.to_account_id) === String(props.account.id) &&
                !batch.forwarding_transfer_id &&
                batch.status === 'approved';
        } else if (statusFilter.value === 'forwarded') {
            matchesStatus = !!batch.forwarding_transfer_id || (batch.forwarded_transfers && batch.forwarded_transfers.length > 0);
        } else if (statusFilter.value === 'received') {
            matchesStatus =
                String(batch.to_account_id) === String(props.account.id);
        }

        return matchesSearch && matchesStatus;
    });
});

const formatCurrency = (val: number | string) => {
    return Number(val || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

const formatDate = (dateStr: string) => {
    if (!dateStr) return 'N/A';
    return new Date(dateStr).toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const openBatchModal = (batch: any) => {
    selectedBatch.value = batch;
    showViewModal.value = true;
};

// Extract individual sub-batches contained inside a master transfer group
const getSubBatches = (masterTransfer: any) => {
    if (!masterTransfer) return [];

    // 1. Relational forwarded transfers (the original single intake batches linked via forwarding_transfer_id)
    if (masterTransfer.forwarded_transfers && masterTransfer.forwarded_transfers.length > 0) {
        return masterTransfer.forwarded_transfers.map((sub: any) => {
            let subDenoms = (sub.denominations || [])
                .map((d: any) => ({
                    value: Number(d.denomination?.value || 0),
                    quantity: Number(d.received_quantity || d.quantity || 0),
                }))
                .filter((d: any) => d.value > 0 && d.quantity > 0);

            if (!subDenoms || subDenoms.length === 0) {
                subDenoms = getBatchDenominations(sub);
            }

            // Fallback for batches created without explicit note breakdown rows
            if ((!subDenoms || subDenoms.length === 0) && (sub.received_amount || sub.amount) > 0) {
                const totalAmt = Number(sub.received_amount || sub.amount || 0);
                if (totalAmt % 1000 === 0) {
                    subDenoms = [{ value: 1000, quantity: totalAmt / 1000 }];
                } else if (totalAmt % 500 === 0) {
                    subDenoms = [{ value: 500, quantity: totalAmt / 500 }];
                }
            }

            return {
                id: sub.id,
                reference: sub.reference,
                created_at: sub.created_at,
                performer: sub.performer,
                notes: sub.notes,
                amount: sub.received_amount || sub.amount,
                received_amount: sub.received_amount || sub.amount,
                denominationsList: subDenoms,
            };
        });
    }

    // 2. Dedicated audit table items (batch_items or vault_batch_group.items)
    const groupItems = masterTransfer.batch_items || masterTransfer.vault_batch_group?.items;
    if (groupItems && groupItems.length > 0) {
        return groupItems.map((item: any) => {
            let subDenoms: any[] = [];

            if (item.intake_transfer?.denominations && item.intake_transfer.denominations.length > 0) {
                subDenoms = item.intake_transfer.denominations
                    .map((d: any) => ({
                        value: Number(d.denomination?.value || 0),
                        quantity: Number(d.received_quantity || d.quantity || 0),
                    }))
                    .filter((d: any) => d.value > 0 && d.quantity > 0);
            } else if (Array.isArray(item.denominations_json) && item.denominations_json.length > 0) {
                subDenoms = item.denominations_json
                    .map((d: any) => ({
                        value: Number(d.value || 0),
                        quantity: Number(d.quantity || 0),
                    }))
                    .filter((d: any) => d.value > 0 && d.quantity > 0);
            }

            if ((!subDenoms || subDenoms.length === 0) && item.amount > 0) {
                const totalAmt = Number(item.amount || 0);
                if (totalAmt % 1000 === 0) {
                    subDenoms = [{ value: 1000, quantity: totalAmt / 1000 }];
                } else if (totalAmt % 500 === 0) {
                    subDenoms = [{ value: 500, quantity: totalAmt / 500 }];
                }
            }

            return {
                id: item.id,
                reference: item.intake_transfer?.reference || item.batch_reference,
                created_at: item.intake_transfer?.created_at || item.intake_date,
                performer: item.intake_transfer?.performer || item.collector,
                notes: item.intake_transfer?.notes || item.notes,
                amount: item.intake_transfer?.received_amount || item.intake_transfer?.amount || item.amount,
                received_amount: item.intake_transfer?.received_amount || item.amount,
                denominationsList: subDenoms,
            };
        });
    }

    // 3. Single transfer fallback
    const directDenoms = (masterTransfer.denominations || [])
        .map((d: any) => ({
            value: Number(d.denomination?.value || 0),
            quantity: Number(d.received_quantity || d.quantity || 0),
        }))
        .filter((d: any) => d.value > 0 && d.quantity > 0);

    return [
        {
            ...masterTransfer,
            denominationsList: directDenoms,
        },
    ];
};

// Aggregated combined denominations across all single intake batches in the master transfer group
const getBatchDenominations = (batch: any) => {
    if (!batch) return [];

    // 1. Direct denominations on this transfer
    if (batch.denominations && batch.denominations.length > 0) {
        const directList = batch.denominations
            .map((d: any) => ({
                value: Number(d.denomination?.value || 0),
                quantity: Number(d.received_quantity || d.quantity || 0),
            }))
            .filter((d: any) => d.value > 0 && d.quantity > 0);

        if (directList.length > 0) {
            return directList.sort((a, b) => b.value - a.value);
        }
    }

    // 2. Aggregate denominations from all single intake sub-batches in this group
    const subBatches = getSubBatches(batch);
    if (subBatches && subBatches.length > 0) {
        const denomMap: Record<number, number> = {};
        subBatches.forEach((sub: any) => {
            if (sub.denominationsList && sub.denominationsList.length > 0) {
                sub.denominationsList.forEach((d: any) => {
                    const val = Number(d.value || 0);
                    const qty = Number(d.quantity || 0);
                    if (val > 0 && qty > 0) {
                        denomMap[val] = (denomMap[val] || 0) + qty;
                    }
                });
            }
        });

        const combined = Object.entries(denomMap)
            .map(([val, qty]) => ({
                value: Number(val),
                quantity: qty,
            }))
            .sort((a, b) => b.value - a.value);

        if (combined.length > 0) {
            return combined;
        }
    }

    // 3. Direct forwarded_transfers fallback
    if (batch.forwarded_transfers && batch.forwarded_transfers.length > 0) {
        const denomMap: Record<number, number> = {};
        batch.forwarded_transfers.forEach((ft: any) => {
            if (ft.denominations && ft.denominations.length > 0) {
                ft.denominations.forEach((d: any) => {
                    const val = Number(d.denomination?.value || 0);
                    const qty = Number(d.received_quantity || d.quantity || 0);
                    if (val > 0 && qty > 0) {
                        denomMap[val] = (denomMap[val] || 0) + qty;
                    }
                });
            }
        });

        return Object.entries(denomMap)
            .map(([val, qty]) => ({
                value: Number(val),
                quantity: qty,
            }))
            .sort((a, b) => b.value - a.value);
    }

    return [];
};

const printingBatchId = ref<number | null>(null);

const printVoucher = async (batch?: any) => {
    const targetBatch = batch || selectedBatch.value;
    if (!targetBatch?.id || printingBatchId.value) return;

    printingBatchId.value = targetBatch.id;
    try {
        const printerService = QzPrinterService.getInstance();
        const url = `/organization/financial-profiles/payment-account-transfers/${targetBatch.id}/print`;
        await printerService.print(url, 'thermal');
    } catch (e: any) {
        console.error('Print voucher error:', e);
    } finally {
        printingBatchId.value = null;
    }
};

// Summary metrics
const totalBatchesCount = computed(
    () => props.vaultBatches?.total || filteredBatches.value.length || 0,
);

const availableInVaultCount = computed(() => {
    if (!props.vaultBatches?.data) return 0;
    return props.vaultBatches.data.filter(
        (b: any) =>
            String(b.to_account_id) === String(props.account.id) &&
            !b.forwarding_transfer_id &&
            b.status === 'approved',
    ).length;
});

const totalVaultValue = computed(() => {
    if (!props.vaultBatches?.data) return 0;
    return props.vaultBatches.data.reduce(
        (sum: number, b: any) => sum + Number(b.received_amount || b.amount || 0),
        0,
    );
});
</script>

<template>
    <div class="flex flex-col gap-4 p-1">
        <!-- Filter & Search Controls -->
        <div
            class="flex flex-col gap-2 rounded-xl border border-border/60 bg-card p-3 shadow-xs sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="relative flex-1">
                <Search
                    class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground"
                />
                <Input
                    v-model="searchQuery"
                    placeholder="Search master group reference, sub-batches, or collector..."
                    class="h-9 pl-9 text-xs font-medium"
                />
            </div>

            <div class="flex items-center gap-2">
                <select
                    v-model="statusFilter"
                    class="h-9 w-[190px] rounded-md border border-border bg-background px-3 text-xs font-bold tracking-widest text-foreground uppercase shadow-xs focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                >
                    <option value="all">All Group Transfers</option>
                    <option value="forwarded">Transferred Out Groups</option>
                    <option value="received">Received from Vault</option>
                </select>
            </div>
        </div>

        <!-- Vault Batches Data Table -->
        <div
            class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
        >
            <Table>
                <TableHeader class="bg-muted/40">
                    <TableRow class="hover:bg-transparent">
                        <TableHead
                            class="text-[10px] font-black uppercase tracking-wider"
                            >Master Group Ref</TableHead
                        >
                        <TableHead
                            class="text-[10px] font-black uppercase tracking-wider"
                            >Transfer Date</TableHead
                        >
                        <TableHead
                            class="text-[10px] font-black uppercase tracking-wider"
                            >Route (From &rarr; To)</TableHead
                        >
                        <TableHead
                            class="text-[10px] font-black uppercase tracking-wider"
                            >Included Batches</TableHead
                        >
                        <TableHead
                            class="text-[10px] font-black uppercase tracking-wider"
                            >Group Denominations Summary</TableHead
                        >
                        <TableHead
                            class="text-right text-[10px] font-black uppercase tracking-wider"
                            >Group Total</TableHead
                        >
                        <TableHead
                            class="text-center text-[10px] font-black uppercase tracking-wider"
                            >Actions</TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="batch in filteredBatches"
                        :key="batch.id"
                        class="transition-colors hover:bg-muted/20"
                    >
                        <!-- Master Reference -->
                        <TableCell>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs font-black text-foreground">
                                    {{ batch.reference }}
                                </span>
                                <Badge
                                    v-if="getSubBatches(batch).length > 1"
                                    variant="secondary"
                                    class="bg-purple-500/10 text-purple-600 border-purple-500/20 text-[9px] font-bold"
                                >
                                    {{ getSubBatches(batch).length }} Sub-Batches
                                </Badge>
                            </div>
                        </TableCell>

                        <!-- Date -->
                        <TableCell
                            class="text-xs font-medium whitespace-nowrap text-muted-foreground"
                        >
                            {{ formatDate(batch.created_at) }}
                        </TableCell>

                        <!-- Flow Route -->
                        <TableCell class="text-xs">
                            <div
                                class="flex items-center gap-1.5 font-bold uppercase"
                            >
                                <span class="text-orange-600">{{
                                    batch.from_account?.name || 'Vault'
                                }}</span>
                                <ArrowRight
                                    class="h-3 w-3 text-muted-foreground"
                                />
                                <span class="text-blue-600">{{
                                    batch.to_account?.name || 'Bank'
                                }}</span>
                            </div>
                        </TableCell>

                        <!-- Included Batches Count & Preview -->
                        <TableCell class="text-xs">
                            <div class="flex flex-col gap-0.5">
                                <span class="text-[10px] font-black text-foreground uppercase">
                                    {{ getSubBatches(batch).length }} Intake Batch(es)
                                </span>
                                <span class="text-[9px] font-mono text-muted-foreground truncate max-w-[200px]" :title="getSubBatches(batch).map((s: any) => s.reference).join(', ')">
                                    {{ getSubBatches(batch).map((s: any) => s.reference).join(', ') }}
                                </span>
                            </div>
                        </TableCell>

                        <!-- Denominations Aggregated Summary -->
                        <TableCell class="text-xs">
                            <div
                                v-if="getBatchDenominations(batch).length"
                                class="flex flex-wrap gap-1"
                            >
                                <Badge
                                    v-for="d in getBatchDenominations(batch)"
                                    :key="d.value"
                                    variant="outline"
                                    class="bg-emerald-500/10 text-emerald-700 border-emerald-500/20 text-[9px] font-mono font-bold dark:text-emerald-400"
                                >
                                    {{ formatCurrency(d.value) }} &times;
                                    {{ d.quantity }}
                                </Badge>
                            </div>
                            <span
                                v-else
                                class="text-[10px] font-medium text-muted-foreground italic"
                                >Standard Transfer</span
                            >
                        </TableCell>

                        <!-- Amount -->
                        <TableCell
                            class="text-right font-mono text-xs font-black text-emerald-600"
                        >
                            {{ account.currency }}
                            {{
                                formatCurrency(
                                    batch.received_amount || batch.amount,
                                )
                            }}
                        </TableCell>

                        <!-- Actions -->
                        <TableCell class="text-center">
                            <div
                                class="flex items-center justify-center gap-1"
                            >
                                <Button
                                    size="xs"
                                    variant="outline"
                                    class="h-7 px-2.5 text-[10px] font-black uppercase text-blue-600 gap-1 hover:bg-blue-500/10"
                                    title="View Group Details"
                                    @click="openBatchModal(batch)"
                                >
                                    <Eye class="h-3.5 w-3.5" />
                                    <span>Inspect</span>
                                </Button>
                                <Button
                                    size="xs"
                                    variant="outline"
                                    class="h-7 w-7 p-0"
                                    :disabled="printingBatchId === batch.id"
                                    title="Print Group Voucher via QZ Tray"
                                    @click.stop="printVoucher(batch)"
                                >
                                    <Printer
                                        class="h-3.5 w-3.5 text-slate-700 dark:text-slate-300"
                                        :class="{ 'animate-pulse text-blue-600': printingBatchId === batch.id }"
                                    />
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>

                    <TableRow v-if="filteredBatches.length === 0">
                        <TableCell
                            colspan="7"
                            class="h-32 text-center text-xs text-muted-foreground"
                        >
                            No transferred-out vault batch groups found for this account.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div v-if="props.vaultBatches?.links?.length" class="mt-2">
            <Pagination :links="props.vaultBatches.links" />
        </div>

        <!-- MASTER GROUP BATCH AUDIT & PRINTABLE VOUCHER MODAL -->
        <Dialog :open="showViewModal" @update:open="showViewModal = $event">
            <DialogContent
                class="print-modal-content max-h-[92vh] flex flex-col overflow-hidden rounded-xl border border-border bg-card p-0 shadow-2xl sm:max-w-3xl md:max-w-4xl"
            >
                <!-- Non-Print Header (Ultra Thin) -->
                <DialogHeader
                    class="no-print flex flex-row items-center justify-between border-b border-border/50 bg-muted/20 px-4 py-2"
                >
                    <div class="flex items-center gap-2">
                        <Layers class="h-4 w-4 text-purple-600 shrink-0" />
                        <DialogTitle
                            class="text-xs font-black tracking-tight text-foreground uppercase"
                        >
                            Vault Group Transfer Voucher — {{ selectedBatch?.reference }}
                        </DialogTitle>
                    </div>
                </DialogHeader>

                <!-- Printable Voucher Body -->
                <div class="print-area custom-scrollbar overflow-y-auto space-y-3.5 bg-white p-4 sm:p-5 text-slate-900">
                    <!-- Ultra Compact Top Voucher Header & Meta Grid -->
                    <div class="rounded-xl border-2 border-slate-900 bg-slate-50/80 p-3 space-y-2.5">
                        <!-- Top Row: Voucher Title + Master Reference + Date -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-300 pb-2">
                            <div>
                                <h2 class="text-sm sm:text-base font-black tracking-tight text-slate-900 uppercase">
                                    AL-AMIN ERP &bull; VAULT GROUP BATCH REQUISITION
                                </h2>
                                <p class="text-[10px] font-bold text-slate-600 uppercase">
                                    Official Multi-Batch Outward Cash Transfer Voucher
                                </p>
                            </div>
                            <div class="sm:text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <span class="text-[9px] font-black text-slate-500 uppercase">Master Ref:</span>
                                    <span class="font-mono text-xs font-black text-slate-900 bg-white border border-slate-300 rounded px-2 py-0.5">{{ selectedBatch?.reference }}</span>
                                </div>
                                <p class="mt-0.5 text-[10px] font-bold text-slate-500">
                                    Date: {{ formatDate(selectedBatch?.created_at) }}
                                </p>
                            </div>
                        </div>

                        <!-- Bottom Row: Flow Route & Group Officer Compact Bar -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs font-bold pt-0.5">
                            <div class="flex items-center gap-1.5">
                                <span class="text-[9px] font-black text-slate-500 uppercase">Origin Vault:</span>
                                <span class="font-black text-slate-900 uppercase truncate">{{ selectedBatch?.from_account?.name || 'Vault 1' }}</span>
                            </div>
                            <div class="flex items-center gap-1.5 sm:justify-center">
                                <span class="text-[9px] font-black text-slate-500 uppercase">Created By:</span>
                                <span class="font-black text-purple-800 uppercase truncate">{{ selectedBatch?.performer?.name || 'Authorized Vault Officer' }}</span>
                            </div>
                            <div class="flex items-center gap-1.5 sm:justify-end">
                                <span class="text-[9px] font-black text-slate-500 uppercase">Destination:</span>
                                <span class="font-black text-blue-700 uppercase truncate">{{ selectedBatch?.to_account?.name || 'Bank Account' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- ITEMISED SUB-BATCHES INCLUDED IN THIS GROUP -->
                    <div class="space-y-2.5">
                        <div
                            class="flex items-center justify-between border-b border-slate-200 pb-1"
                        >
                            <p
                                class="text-[11px] font-black tracking-wider text-slate-800 uppercase"
                            >
                                Included Received Batches ({{
                                    getSubBatches(selectedBatch).length
                                }} Batches)
                            </p>
                            <p class="font-mono text-xs font-black text-slate-900">
                                Group Total: KES
                                {{
                                    formatCurrency(
                                        selectedBatch?.received_amount ||
                                            selectedBatch?.amount,
                                    )
                                }}
                            </p>
                        </div>

                        <div
                            v-for="(sub, index) in getSubBatches(selectedBatch)"
                            :key="sub.id || index"
                            class="rounded-lg border border-slate-200 bg-slate-50/50 p-3 space-y-2 shadow-xs"
                        >
                            <div
                                class="flex items-center justify-between border-b border-slate-200/80 pb-2"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-slate-900 font-mono text-[10px] font-bold text-white"
                                    >
                                        {{ index + 1 }}
                                    </span>
                                    <div>
                                        <span
                                            class="font-mono text-xs font-black text-slate-900"
                                        >Ref: {{ sub.reference }}</span>
                                        <span
                                            v-if="sub.performer?.name || sub.collector"
                                            class="ml-2 text-xs font-bold text-slate-700"
                                        >
                                            &bull; Collector: {{ sub.performer?.name || sub.collector }}
                                        </span>
                                        <span class="ml-2 text-[10px] font-semibold text-slate-500">
                                            ({{ formatDate(sub.created_at) }})
                                        </span>
                                    </div>
                                </div>
                                <span
                                    class="font-mono text-xs font-black text-emerald-700"
                                >
                                    KES
                                    {{
                                        formatCurrency(
                                            sub.received_amount || sub.amount,
                                        )
                                    }}
                                </span>
                            </div>

                            <!-- ORIGINAL INTAKE NOTE -->
                            <div
                                v-if="sub.notes"
                                class="rounded bg-amber-500/10 border border-amber-500/20 px-2.5 py-1 text-[11px] font-medium text-amber-900 flex items-center gap-1.5"
                            >
                                <span class="font-black uppercase text-[9px] tracking-wider text-amber-800">Original Intake Note:</span>
                                <span class="italic font-bold">"{{ sub.notes }}"</span>
                            </div>

                            <!-- Sub-Batch Denominations Breakdown -->
                            <div
                                v-if="
                                    sub.denominationsList &&
                                    sub.denominationsList.length
                                "
                                class="pt-1"
                            >
                                <p
                                    class="mb-1 text-[9px] font-black tracking-widest text-slate-500 uppercase"
                                >
                                    Physical Note Denominations:
                                </p>
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="d in sub.denominationsList"
                                        :key="d.value"
                                        class="inline-flex items-center gap-1 rounded border border-slate-200 bg-white px-2 py-0.5 font-mono text-[11px] font-bold text-slate-900 shadow-2xs"
                                    >
                                        <span class="font-black text-slate-800">KES {{ formatCurrency(d.value) }}</span>
                                        <span class="rounded bg-emerald-500/10 px-1 py-0.2 font-black text-emerald-800">&times; {{ d.quantity }}</span>
                                        <span class="text-[9.5px] font-normal text-slate-500">(= KES {{ formatCurrency(d.value * d.quantity) }})</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- GRAND TOTAL AGGREGATED GROUP DENOMINATIONS SUMMARY TABLE -->
                    <div
                        v-if="getBatchDenominations(selectedBatch).length"
                        class="space-y-1.5 pt-1"
                    >
                        <p
                            class="text-[11px] font-black tracking-wider text-slate-900 uppercase"
                        >
                            Grand Total Group Denominations Summary
                        </p>
                        <table
                            class="w-full overflow-hidden rounded-md border border-slate-300 text-xs"
                        >
                            <thead
                                class="border-b border-slate-300 bg-slate-100"
                            >
                                <tr>
                                    <th
                                        class="py-1.5 px-3 text-left font-black uppercase"
                                    >
                                        Denomination Note
                                    </th>
                                    <th
                                        class="py-1.5 px-3 text-center font-black uppercase"
                                    >
                                        Total Group Note Count
                                    </th>
                                    <th
                                        class="py-1.5 px-3 text-right font-black uppercase"
                                    >
                                        Total Value (KES)
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <tr
                                    v-for="d in getBatchDenominations(
                                        selectedBatch,
                                    )"
                                    :key="d.value"
                                >
                                    <td
                                        class="py-1.5 px-3 font-bold text-slate-800"
                                    >
                                        KES {{ formatCurrency(d.value) }} Note
                                    </td>
                                    <td
                                        class="py-1.5 px-3 text-center font-mono font-bold text-slate-900"
                                    >
                                        {{ d.quantity }} Notes
                                    </td>
                                    <td
                                        class="py-1.5 px-3 text-right font-mono font-black text-slate-900"
                                    >
                                        KES
                                        {{ formatCurrency(d.value * d.quantity) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot
                                class="border-t-2 border-slate-900 bg-slate-100 font-black"
                            >
                                <tr>
                                    <td
                                        class="py-1.5 px-3 uppercase text-slate-900"
                                    >
                                        TOTAL GROUP CASH VALUE
                                    </td>
                                    <td
                                        class="py-1.5 px-3 text-center font-mono text-slate-900"
                                    >
                                        {{
                                            getBatchDenominations(
                                                selectedBatch,
                                            ).reduce(
                                                (sum, d) => sum + d.quantity,
                                                0,
                                            )
                                        }}
                                        Total Notes
                                    </td>
                                    <td
                                        class="py-1.5 px-3 text-right font-mono text-xs text-emerald-700"
                                    >
                                        KES
                                        {{
                                            formatCurrency(
                                                selectedBatch?.received_amount ||
                                                    selectedBatch?.amount,
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Footer Actions -->
                <DialogFooter
                    class="no-print flex shrink-0 items-center justify-end gap-2 border-t border-border bg-muted/20 px-5 py-3"
                >
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-8 text-xs font-bold uppercase"
                        @click="showViewModal = false"
                    >
                        Close
                    </Button>
                    <Button
                        size="sm"
                        class="h-8 bg-blue-600 hover:bg-blue-700 text-xs font-black uppercase text-white gap-1.5"
                        :disabled="printingBatchId === selectedBatch?.id"
                        @click="printVoucher(selectedBatch)"
                    >
                        <Printer class="h-3.5 w-3.5" :class="{ 'animate-spin': printingBatchId === selectedBatch?.id }" />
                        <span>{{ printingBatchId === selectedBatch?.id ? 'Printing Voucher...' : 'Print Group Voucher' }}</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
@media print {
    body * {
        visibility: hidden;
    }
    .print-modal-content,
    .print-modal-content * {
        visibility: visible;
    }
    .print-modal-content {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        max-width: 100% !important;
        margin: 0;
        box-shadow: none !important;
        border: none !important;
    }
    .no-print {
        display: none !important;
    }
    .print-area {
        padding: 0 !important;
        background: white !important;
        color: black !important;
    }
}
</style>
