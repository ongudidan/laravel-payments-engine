<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import {
    AlertTriangle,
    ArrowDownLeft,
    ArrowUpRight,
    Calendar,
    CheckCircle,
    Clock,
    CreditCard,
    Hash,
    MessageSquare,
    UserCircle,
    XCircle,
} from 'lucide-vue-next';

defineProps<{
    show: boolean;
    cheque: any;
    account: any;
    formatCurrency: (amount: string | number, currency: string) => string;
    formatDate: (date: string) => string;
}>();

defineEmits(['close']);
</script>

<template>
    <Dialog :open="show" @update:open="$emit('close')">
        <DialogContent
            class="overflow-hidden rounded-lg border-none bg-[#FAFAFA] p-0 shadow-2xl sm:max-w-[800px]"
        >
            <DialogHeader
                class="border-b border-slate-100 bg-white px-5 py-3 pr-12"
            >
                <DialogTitle
                    class="flex items-center gap-2 text-[13px] font-black tracking-tight text-slate-900 uppercase"
                >
                    <CreditCard class="h-3.5 w-3.5 text-orange-500" />
                    Cheque Payment Details
                </DialogTitle>
                <DialogDescription
                    class="text-[8px] font-bold tracking-[0.15em] text-slate-400 uppercase"
                >
                    Committed Fund Instrument Log
                </DialogDescription>
            </DialogHeader>

            <div class="flex h-[380px] max-h-[60vh] flex-col md:flex-row">
                <!-- Left Body (Details) -->
                <div
                    class="custom-scrollbar flex-1 space-y-4 overflow-y-auto border-r border-slate-100 bg-white p-4"
                >
                    <div class="grid grid-cols-2 gap-3">
                        <!-- Issuer/Origin -->
                        <div
                            class="space-y-1.5 rounded-lg border border-slate-100 bg-slate-50/50 p-2.5"
                        >
                            <div class="flex items-center gap-1.5">
                                <ArrowUpRight
                                    class="h-2.5 w-2.5 text-amber-600"
                                />
                                <span
                                    class="text-[7.5px] leading-none font-black tracking-widest text-slate-400 uppercase"
                                    >Originating Point</span
                                >
                            </div>
                            <div class="space-y-0.5">
                                <span
                                    class="block truncate text-[9px] font-black tracking-tight text-slate-900 uppercase"
                                    >{{ account.name }}</span
                                >
                                <div class="flex items-center gap-1 opacity-60">
                                    <Hash class="h-2 w-2" />
                                    <span
                                        class="text-[7.5px] font-black tracking-tighter uppercase italic"
                                        >Cheque #{{
                                            cheque?.cheque_number
                                        }}</span
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Destination (Supplier/Customer) -->
                        <div
                            class="space-y-1.5 rounded-lg border border-slate-100 bg-slate-50/50 p-2.5"
                        >
                            <div class="flex items-center gap-1.5">
                                <ArrowDownLeft
                                    class="h-2.5 w-2.5 text-emerald-600"
                                />
                                <span
                                    class="text-[7.5px] leading-none font-black tracking-widest text-slate-400 uppercase"
                                    >External Partner</span
                                >
                            </div>
                            <div class="space-y-0.5">
                                <span
                                    class="block truncate text-[9px] font-black tracking-tight text-slate-900 uppercase"
                                >
                                    {{
                                        cheque?.direction === 'Outward'
                                            ? cheque.chequeable?.supplier
                                                  ?.company_name ||
                                              cheque.chequeable?.supplier
                                                  ?.name ||
                                              cheque.chequeable?.employee
                                                  ?.official_names ||
                                              (cheque.chequeable_type.includes(
                                                  'WithholdingBatch',
                                              )
                                                  ? 'Kenya Revenue Authority (KRA)'
                                                  : '') ||
                                              cheque.chequeable?.lender?.name ||
                                              cheque.chequeable?.borrower
                                                  ?.name ||
                                              'Local Recipient'
                                            : cheque.chequeable?.customer
                                                  ?.company_name ||
                                              cheque.chequeable?.customer
                                                  ?.name ||
                                              cheque.chequeable
                                                  ?.allocations?.[0]?.sale
                                                  ?.customer?.company_name ||
                                              cheque.chequeable
                                                  ?.allocations?.[0]?.sale
                                                  ?.customer?.name ||
                                              'Customer'
                                    }}
                                </span>
                                <div class="flex items-center gap-1 opacity-60">
                                    <Calendar class="h-2 w-2" />
                                    <span
                                        class="text-[7.5px] font-black tracking-tighter uppercase italic"
                                        >Maturity Date:
                                        {{
                                            cheque?.cheque_date
                                                ? formatDate(
                                                      cheque?.cheque_date,
                                                  )
                                                : 'N/A'
                                        }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes & Observations -->
                    <div class="space-y-2">
                        <div class="flex items-center gap-1.5 px-0.5">
                            <MessageSquare class="h-2.5 w-2.5 text-slate-400" />
                            <span
                                class="text-[7.5px] leading-none font-black tracking-widest text-slate-400 uppercase"
                                >Instrument Observations & Notes</span
                            >
                        </div>
                        <div
                            class="flex min-h-[100px] flex-col rounded-lg border border-slate-100 bg-slate-50 p-3.5"
                        >
                            <div class="mb-2 flex items-center gap-2">
                                <Clock class="h-2 w-2 text-slate-300" />
                                <span
                                    class="text-[7.5px] font-black tracking-tighter text-slate-500 uppercase"
                                    >Record Initialized:
                                    {{
                                        cheque?.created_at
                                            ? formatDate(cheque.created_at)
                                            : 'N/A'
                                    }}</span
                                >
                            </div>
                            <Separator class="mb-2 opacity-20" />

                            <div
                                v-if="cheque?.status === 'Cancelled'"
                                class="mb-2 flex items-center gap-1.5 rounded border border-rose-100 bg-rose-50 px-2 py-1 text-[8.5px] font-bold text-rose-600 uppercase"
                            >
                                <XCircle class="h-2 w-2" /> Cancellation Reason:
                                {{
                                    cheque.cancel_reason ||
                                    'No specific reason given'
                                }}
                            </div>

                            <div
                                v-if="cheque?.transaction_number"
                                class="mb-2 flex items-center gap-1.5 rounded border border-emerald-100 bg-emerald-50 px-2 py-1 text-[8.5px] font-bold text-emerald-600 uppercase"
                            >
                                <CheckCircle class="h-2 w-2" /> Bank Ref: #{{
                                    cheque.transaction_number
                                }}
                            </div>

                            <div
                                v-if="cheque?.collected_by_name"
                                class="mb-2 flex items-center gap-1.5 rounded border border-blue-100 bg-blue-50 px-2 py-1 text-[8.5px] font-bold text-blue-600 uppercase"
                            >
                                <UserCircle class="h-2 w-2" /> Collected by
                                {{ cheque.collected_by_name }} ({{
                                    cheque.collected_by_mobile
                                }})
                            </div>

                            <div
                                class="mt-auto text-[8.5px] font-bold text-slate-400 italic"
                            >
                                System-generated financial instrument record.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Sidebar (Financial Summary) -->
                <div
                    class="flex h-full w-full flex-col border-t border-slate-100 bg-[#FAFAFA] p-4 md:w-[240px] md:border-t-0"
                >
                    <div class="space-y-5">
                        <div class="space-y-1 text-right">
                            <span
                                class="block text-[7.5px] font-black tracking-widest text-slate-500 uppercase"
                                >Face Value</span
                            >
                            <div class="flex flex-col items-end">
                                <span
                                    class="text-lg leading-none font-black tracking-tighter text-slate-900 tabular-nums"
                                >
                                    {{
                                        formatCurrency(
                                            cheque?.amount || 0,
                                            account.currency,
                                        )
                                    }}
                                </span>
                                <span
                                    class="mt-1 text-[8px] font-black tracking-widest text-slate-400 uppercase"
                                    >{{ account.currency }}</span
                                >
                            </div>
                        </div>

                        <div
                            class="space-y-1.5 rounded border border-orange-100/50 bg-orange-50/50 p-2.5"
                        >
                            <div class="flex items-center gap-1.5">
                                <AlertTriangle
                                    class="h-2 w-2 text-orange-500"
                                />
                                <span
                                    class="text-[7.5px] font-black tracking-widest text-orange-600 uppercase"
                                    >Financial Impact</span
                                >
                            </div>
                            <p
                                class="text-[7.5px] leading-tight font-bold text-orange-700 uppercase"
                            >
                                This amount is currently committed and will
                                remain uncleared until final bank authorization.
                            </p>
                        </div>

                        <div class="space-y-1.5 border-t border-slate-200 pt-3">
                            <div
                                class="flex items-center justify-between text-[7.5px] font-black tracking-tight uppercase"
                            >
                                <span class="text-slate-400"
                                    >Current Phase</span
                                >
                                <span
                                    :class="{
                                        'border-blue-500/20 bg-blue-500/10 text-blue-500':
                                            cheque?.status === 'Collected',
                                        'border-orange-500/20 bg-orange-500/10 text-orange-500':
                                            cheque?.status === 'Pending',
                                        'border-emerald-500/20 bg-emerald-500/10 text-emerald-600':
                                            cheque?.status === 'Cleared',
                                        'border-rose-500/20 bg-rose-500/10 text-rose-600':
                                            cheque?.status === 'Cancelled',
                                    }"
                                    class="origin-right scale-90 rounded-sm border px-1.5 py-0.5 shadow-sm"
                                >
                                    {{ cheque?.status }}
                                </span>
                            </div>
                            <div
                                class="flex items-center justify-between text-[7.5px] font-black tracking-tight uppercase"
                            >
                                <span class="text-slate-400">Direction</span>
                                <span
                                    class="font-black text-slate-600 uppercase"
                                    >{{ cheque?.direction }}</span
                                >
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto pt-4">
                        <Button
                            variant="outline"
                            @click="$emit('close')"
                            class="h-8 w-full border-slate-200 bg-white text-[8.5px] font-black tracking-widest text-slate-400 uppercase shadow-sm hover:text-slate-900"
                        >
                            Close Instrument
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Footer Audit -->
            <div
                class="flex h-auto min-h-[40px] items-center justify-between border-t border-slate-50 bg-white px-5 py-2"
            >
                <div class="flex items-center gap-2">
                    <div class="h-1.5 w-1.5 rounded-full bg-slate-300"></div>
                    <span
                        class="text-[8px] font-black tracking-[0.2em] text-slate-400 uppercase"
                        >Cheque Registry Entry verified</span
                    >
                </div>
                <div
                    class="text-[8px] font-bold tracking-tight text-slate-300 italic"
                >
                    Instrument ID:
                    {{
                        cheque?.id?.toString(16).padStart(8, '0').toUpperCase()
                    }}
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
