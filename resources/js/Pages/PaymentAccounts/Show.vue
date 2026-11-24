<script setup lang="ts">
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    ArrowLeftRight,
    Banknote,
    CreditCard,
    History,
    Vault,
} from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';
import { QzPrinterService } from '@/services/qzPrinter';

// Partial Components
import AccountHeader from './Partials/AccountHeader.vue';
import AccountStats from './Partials/AccountStats.vue';
import ChequesTab from './Partials/Tabs/ChequesTab.vue';
import OverviewTab from './Partials/Tabs/OverviewTab.vue';
import PayoutsTab from './Partials/Tabs/PayoutsTab.vue';
import TransactionsTab from './Partials/Tabs/TransactionsTab.vue';
import TransfersTab from './Partials/Tabs/TransfersTab.vue';
import VaultBatchesTab from './Partials/Tabs/VaultBatchesTab.vue';
import TransferRequestModal from './Partials/TransferRequestModal.vue';
import B2bTransferModal from './Partials/B2bTransferModal.vue';
import PaymentAccountModal from './PaymentAccountModal.vue';

const props = defineProps<{
    data: {
        paymentAccount: any;
        transactions: any;
        transfers: any;
        vaultBatches: any;
        allAccounts: any[];
        paymentMethods: any[];
        cheques: any;
        pendingPayouts: any;
        pendingIncomingAmount: string | number;
        balanceStats?: any;
        futureLiabilities?: any;
        filters: any;
        banks?: any[];
        companies?: any[];
        branches?: any[];
        users?: any[];
        allBankAccounts?: any[];
        userAssignments?: Record<number, string>;
    };
}>();

const page = usePage();
const showTransferModal = ref(false);
const showB2bModal = ref(false);
const showEditModal = ref(false);

// Auto-print group transfer voucher on submission
watch(
    () => (page.props.flash as any)?.auto_print_transfer_id,
    async (transferId) => {
        if (transferId) {
            try {
                const printerService = QzPrinterService.getInstance();
                const url = `/organization/financial-profiles/payment-account-transfers/${transferId}/print`;
                await printerService.print(url, 'thermal');
            } catch (err) {
                console.error('Auto-print vault group transfer voucher failed:', err);
            }
        }
    },
    { immediate: true }
);

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Financial Profiles', href: '/organization/financial-profiles' },
    {
        title: 'Payment Accounts',
        href: '/organization/financial-profiles/payment-accounts',
    },
    { title: props.data.paymentAccount.name, href: '' },
];

const currentTab = ref('overview');

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (
        tabParam &&
        [
            'overview',
            'transactions',
            'transfers',
            'vault_batches',
            'payouts',
            'cheques',
        ].includes(tabParam)
    ) {
        currentTab.value = tabParam;
    }
});

const handleTabChange = (value: string) => {
    currentTab.value = value;
    router.get(
        `/organization/financial-profiles/payment-accounts/${props.data.paymentAccount.id}`,
        { tab: value },
        { preserveState: true, preserveScroll: true, replace: true, only: ['data'] },
    );
};
</script>

<template>
    <Head :title="`Account: ${data.paymentAccount.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex w-full flex-1 flex-col gap-4 overflow-hidden p-4 text-foreground"
        >
            <AccountHeader
                :account="data.paymentAccount"
                @b2b-transfer="showB2bModal = true"
                @edit="showEditModal = true"
            />

            <AccountStats
                :account="data.paymentAccount"
                :pending-amount="data.pendingIncomingAmount"
            />

            <!-- Content Area with Tabs -->
            <Tabs
                v-model="currentTab"
                @update:modelValue="handleTabChange"
                class="flex min-h-0 w-full flex-1 flex-col"
            >
                <div
                    class="mb-4 flex shrink-0 items-center justify-between border-b border-border/50 pb-px"
                >
                    <TabsList
                        class="flex h-auto items-stretch gap-1 rounded-lg border border-border/50 bg-muted/30 p-1 backdrop-blur-md"
                    >
                        <TabsTrigger
                            value="overview"
                            class="group flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-1.5 transition-all data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-sm"
                        >
                            <Activity class="h-3.5 w-3.5 text-blue-500" />
                            <span
                                class="text-[9px] font-black tracking-widest uppercase"
                                >Account Overview</span
                            >
                        </TabsTrigger>
                        <TabsTrigger
                            value="transactions"
                            class="group flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-1.5 transition-all data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-sm"
                        >
                            <History class="h-3.5 w-3.5 text-emerald-500" />
                            <span
                                class="text-[9px] font-black tracking-widest uppercase"
                                >Activity Ledger</span
                            >
                        </TabsTrigger>
                        <TabsTrigger
                            value="transfers"
                            class="group flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-1.5 transition-all data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-sm"
                        >
                            <ArrowLeftRight
                                class="h-3.5 w-3.5 text-orange-500"
                            />
                            <span
                                class="text-[9px] font-black tracking-widest uppercase"
                                >Liquidity Movements</span
                            >
                        </TabsTrigger>
                        <TabsTrigger
                            value="vault_batches"
                            class="group flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-1.5 transition-all data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-sm"
                        >
                            <Vault class="h-3.5 w-3.5 text-purple-500" />
                            <span
                                class="text-[9px] font-black tracking-widest uppercase"
                                >Vault Batches</span
                            >
                        </TabsTrigger>
                        <TabsTrigger
                            value="payouts"
                            class="group flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-1.5 transition-all data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-sm"
                        >
                            <Banknote class="h-3.5 w-3.5 text-rose-500" />
                            <span
                                class="text-[9px] font-black tracking-widest uppercase"
                                >Pending Payouts</span
                            >
                        </TabsTrigger>
                        <TabsTrigger
                            value="cheques"
                            class="group flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-1.5 transition-all data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-sm"
                        >
                            <CreditCard class="h-3.5 w-3.5 text-orange-500" />
                            <span
                                class="text-[9px] font-black tracking-widest uppercase"
                                >Cheques & Settlement</span
                            >
                        </TabsTrigger>
                    </TabsList>
                </div>

                <div class="min-h-0 flex-1 overflow-hidden">
                    <TabsContent
                        value="overview"
                        class="custom-scrollbar m-0 h-full overflow-auto"
                    >
                        <OverviewTab :account="data.paymentAccount" />
                    </TabsContent>

                    <TabsContent
                        value="transactions"
                        class="custom-scrollbar m-0 h-full overflow-auto"
                    >
                        <TransactionsTab
                            :account="data.paymentAccount"
                            :transactions="data.transactions"
                            :filters="data.filters"
                            :balance-stats="data.balanceStats"
                        />
                    </TabsContent>

                    <TabsContent
                        value="transfers"
                        class="custom-scrollbar m-0 h-full overflow-auto"
                    >
                        <TransfersTab
                            :account="data.paymentAccount"
                            :transfers="data.transfers"
                            :filters="data.filters"
                            @request-transfer="showTransferModal = true"
                            @b2b-transfer="showB2bModal = true"
                        />
                    </TabsContent>

                    <TabsContent
                        value="vault_batches"
                        class="custom-scrollbar m-0 h-full overflow-auto"
                    >
                        <VaultBatchesTab
                            :account="data.paymentAccount"
                            :vault-batches="data.vaultBatches"
                        />
                    </TabsContent>

                    <TabsContent
                        value="payouts"
                        class="custom-scrollbar m-0 h-full overflow-auto"
                    >
                        <PayoutsTab
                            :account="data.paymentAccount"
                            :payouts="data.pendingPayouts"
                        />
                    </TabsContent>

                    <TabsContent
                        value="cheques"
                        class="custom-scrollbar m-0 h-full overflow-auto"
                    >
                        <ChequesTab
                            :account="data.paymentAccount"
                            :cheques="data.cheques"
                            :future-liabilities="data.futureLiabilities"
                            :filters="data.filters"
                        />
                    </TabsContent>
                </div>
            </Tabs>
        </div>

        <!-- Modals -->
        <TransferRequestModal
            v-if="showTransferModal"
            v-model:open="showTransferModal"
            :source-account="data.paymentAccount"
            :all-accounts="data.allAccounts"
            :payment-methods="data.paymentMethods"
        />

        <B2bTransferModal
            v-model:open="showB2bModal"
            :account="data.paymentAccount"
        />

        <PaymentAccountModal
            v-if="showEditModal"
            :show="showEditModal"
            :payment-account="data.paymentAccount"
            :methods="data.paymentMethods"
            :banks="data.banks || []"
            :companies="data.companies || []"
            :branches="data.branches || []"
            :users="data.users || []"
            :all-bank-accounts="data.allBankAccounts || []"
            :user-assignments="data.userAssignments || {}"
            @close="showEditModal = false"
        />
    </AppLayout>
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
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: hsl(var(--muted-foreground) / 0.5);
}
</style>
