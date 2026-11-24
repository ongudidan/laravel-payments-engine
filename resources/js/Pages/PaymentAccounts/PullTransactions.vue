<script setup lang="ts">
import AutoPullTab from '@/components/Mpesa/AutoPullTab.vue';
import AutoSweepTab from '@/components/Mpesa/AutoSweepTab.vue';
import PulledTransactionsTab from './Partials/Tabs/PulledTransactionsTab.vue';
import PullRequestsTab from './Partials/Tabs/PullRequestsTab.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ExternalLink,
    History,
    Sparkles,
    Wallet,
    Zap,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    paymentAccount: any;
    activeTab: string;
    transactions: {
        data: any[];
        links: any[];
        from: number;
        to: number;
        total: number;
        per_page?: number;
    };
    requests: {
        data: any[];
        links: any[];
        from: number;
        to: number;
        total: number;
    };
    summary: {
        total_imported: number;
        matched_local: number;
        not_recorded: number;
    };
    assignedCustomer?: any;
    companies?: any[];
    branches?: any[];
    stats: {
        total_queries: number;
        total_pulled_count: number;
        total_pulled_amount: number;
        matched_count: number;
        unmatched_count: number;
        has_pending_query: boolean;
    };
    filters: {
        search: string;
        status: string;
        date: string;
        per_page?: number | string;
    };
    pullCallbackUrl?: string;
    nominatedNumber?: string;
    lastLiveBalance?: any;
    autoPullConfig?: any;
    autoPullLogs?: any[];
    autoSweepConfig?: any;
    todaySweepRun?: any;
    recentSweepRuns?: any[];
}>();

const page = usePage();
const canMap = computed(() => {
    const permissions = (page.props.auth as any)?.permissions || [];
    return (
        permissions.includes('Finance: Mpesa: Map') ||
        permissions.includes('Finance: Mpesa: Manage')
    );
});

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Financial Profiles', href: '/organization/financial-profiles' },
    {
        title: 'Payment Accounts',
        href: '/organization/financial-profiles/payment-accounts',
    },
    {
        title: props.paymentAccount.name,
        href: `/organization/financial-profiles/payment-accounts/${props.paymentAccount.id}`,
    },
    { title: 'Pull Transactions API', href: '' },
];

// Independent URL-driven active tab state matching Show.vue pattern
const currentTab = ref(props.activeTab || 'transactions');

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam && ['transactions', 'requests', 'auto-pull', 'auto-sweep'].includes(tabParam)) {
        currentTab.value = tabParam;
    }
});

watch(
    () => props.activeTab,
    (newTab) => {
        if (newTab && ['transactions', 'requests', 'auto-pull', 'auto-sweep'].includes(newTab)) {
            currentTab.value = newTab;
        }
    }
);

const handleTabChange = (value: string) => {
    currentTab.value = value;
    router.get(
        `/organization/financial-profiles/payment-accounts/${props.paymentAccount.id}/pull-transactions`,
        { tab: value },
        { preserveState: true, preserveScroll: true, replace: true }
    );
};

const formatCurrency = (amount: number | string) => {
    return Number(amount || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};
</script>

<template>
    <Head :title="`Pull API: ${paymentAccount.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-2.5 overflow-hidden p-3 text-foreground">
            <!-- Account Info Header Banner (Compact) -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 rounded-lg border border-border/50 bg-card/40 p-2.5 shadow-xs backdrop-blur-md shrink-0">
                <div class="flex items-center gap-2.5 min-w-0">
                    <Link
                        :href="`/organization/financial-profiles/payment-accounts/${paymentAccount.id}`"
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded border border-border bg-background shadow-xs hover:bg-muted transition"
                        title="Back to Account Details"
                    >
                        <ArrowLeft class="h-3.5 w-3.5 text-muted-foreground" />
                    </Link>

                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h1 class="text-sm font-black uppercase tracking-tight text-foreground truncate">
                                {{ paymentAccount.name }}
                            </h1>
                            <Badge variant="outline" class="font-mono text-[9px] uppercase border-emerald-500/30 bg-emerald-500/10 text-emerald-600 px-1.5 py-0">
                                Paybill: {{ paymentAccount.account_number || paymentAccount.mpesa_config?.shortcode || 'N/A' }}
                            </Badge>
                        </div>
                        <p class="text-[10px] text-muted-foreground truncate">
                            Daraja Pull Transactions Engine &bull; Automated Settlement to Bank
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <!-- Current ERP Balance Indicator -->
                    <div class="text-right px-2 py-0.5 rounded bg-background/50 border border-border/40">
                        <span class="text-[8.5px] font-bold text-muted-foreground uppercase block leading-none">ERP Balance</span>
                        <span class="text-xs font-black text-emerald-600 tabular-nums">
                            {{ paymentAccount.currency || 'KES' }} {{ formatCurrency(paymentAccount.balance) }}
                        </span>
                    </div>

                    <!-- Link to Account Details -->
                    <Link
                        :href="`/organization/financial-profiles/payment-accounts/${paymentAccount.id}`"
                        class="flex h-7 items-center gap-1 rounded border border-border bg-background px-2 text-[9px] font-black uppercase text-foreground hover:bg-muted shadow-xs transition"
                    >
                        <span>Account Details</span>
                        <ExternalLink class="h-2.5 w-2.5 text-muted-foreground" />
                    </Link>
                </div>
            </div>

            <!-- Independent Tabs Interface (Mirroring Show.vue on Payment Accounts) -->
            <Tabs
                :model-value="currentTab"
                @update:model-value="handleTabChange"
                class="flex min-h-0 w-full flex-1 flex-col"
            >
                <!-- Tabs List Navigation Strip with Independent Links -->
                <div class="mb-1.5 flex shrink-0 items-center justify-between border-b border-border/50 pb-1">
                    <TabsList class="flex h-auto items-stretch gap-1 rounded-lg border border-border/50 bg-muted/30 p-1 backdrop-blur-md">
                        <!-- 1. Pulled Transactions Tab Trigger -->
                        <TabsTrigger
                            value="transactions"
                            class="flex items-center gap-1.5 rounded-md px-2.5 py-1 text-[9.5px] font-black uppercase tracking-wider data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-xs transition-all"
                        >
                            <Wallet class="h-3 w-3 text-emerald-500" />
                            <span>Pulled Transactions</span>
                            <Badge
                                variant="outline"
                                class="ml-0.5 border-emerald-500/20 bg-emerald-500/10 text-emerald-600 text-[8px] font-mono px-1 py-0"
                            >
                                {{ stats.total_pulled_count }}
                            </Badge>
                        </TabsTrigger>

                        <!-- 2. Query Batches Tab Trigger -->
                        <TabsTrigger
                            value="requests"
                            class="flex items-center gap-1.5 rounded-md px-2.5 py-1 text-[9.5px] font-black uppercase tracking-wider data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-xs transition-all"
                        >
                            <History class="h-3 w-3 text-blue-500" />
                            <span>Query Batches</span>
                            <Badge
                                variant="outline"
                                class="ml-0.5 border-blue-500/20 bg-blue-500/10 text-blue-600 text-[8px] font-mono px-1 py-0"
                            >
                                {{ stats.total_queries }}
                            </Badge>
                        </TabsTrigger>

                        <!-- 3. Auto-Pull & Reconcile Tab Trigger -->
                        <TabsTrigger
                            value="auto-pull"
                            class="flex items-center gap-1.5 rounded-md px-2.5 py-1 text-[9.5px] font-black uppercase tracking-wider data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-xs transition-all"
                        >
                            <Zap class="h-3 w-3 text-emerald-500" />
                            <span>Auto-Pull & Reconcile</span>
                            <span
                                v-if="autoPullConfig?.is_enabled"
                                class="h-1.5 w-1.5 rounded-full bg-emerald-500 shrink-0 animate-pulse"
                                title="Auto-Pull Active"
                            />
                        </TabsTrigger>

                        <!-- 4. Auto-Sweep to Bank Tab Trigger -->
                        <TabsTrigger
                            value="auto-sweep"
                            class="flex items-center gap-1.5 rounded-md px-2.5 py-1 text-[9.5px] font-black uppercase tracking-wider data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-xs transition-all"
                        >
                            <Sparkles class="h-3 w-3 text-amber-500" />
                            <span>Auto-Sweep to Bank</span>
                            <span
                                v-if="autoSweepConfig?.is_enabled"
                                class="h-1.5 w-1.5 rounded-full bg-emerald-500 shrink-0"
                                title="Auto-Sweep Active"
                            />
                        </TabsTrigger>
                    </TabsList>
                </div>

                <!-- Independent Tab Contents -->
                <div class="min-h-0 flex-1 overflow-hidden">
                    <!-- Tab 1: Pulled Transactions -->
                    <TabsContent
                        value="transactions"
                        class="custom-scrollbar m-0 h-full overflow-y-auto outline-none"
                    >
                        <PulledTransactionsTab
                            :payment-account="paymentAccount"
                            :transactions="transactions"
                            :summary="summary"
                            :assigned-customer="assignedCustomer"
                            :companies="companies || []"
                            :branches="branches || []"
                            :stats="stats"
                            :filters="filters"
                            :pull-callback-url="pullCallbackUrl"
                            :nominated-number="nominatedNumber"
                            :last-live-balance="lastLiveBalance"
                            :can-map="canMap"
                            @switch-tab="handleTabChange"
                        />
                    </TabsContent>

                    <!-- Tab 2: Query Batches -->
                    <TabsContent
                        value="requests"
                        class="custom-scrollbar m-0 h-full overflow-y-auto outline-none"
                    >
                        <PullRequestsTab
                            :payment-account="paymentAccount"
                            :requests="requests"
                            :stats="stats"
                        />
                    </TabsContent>

                    <!-- Tab 3: Auto-Pull & Reconcile -->
                    <TabsContent
                        value="auto-pull"
                        class="custom-scrollbar m-0 h-full overflow-y-auto outline-none"
                    >
                        <AutoPullTab
                            :payment-account="paymentAccount"
                            :auto-pull-config="autoPullConfig"
                            :auto-pull-logs="autoPullLogs || []"
                        />
                    </TabsContent>

                    <!-- Tab 4: Auto-Sweep to Bank -->
                    <TabsContent
                        value="auto-sweep"
                        class="custom-scrollbar m-0 h-full overflow-y-auto outline-none"
                    >
                        <AutoSweepTab
                            :payment-account="paymentAccount"
                            :auto-sweep-config="autoSweepConfig"
                            :today-sweep-run="todaySweepRun"
                            :recent-sweep-runs="recentSweepRuns || []"
                        />
                    </TabsContent>
                </div>
            </Tabs>
        </div>
    </AppLayout>
</template>
