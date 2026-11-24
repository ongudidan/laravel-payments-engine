<script setup lang="ts">
import AppLayout from '../../Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Plus, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import MpesaConfigModal from './MpesaConfigModal.vue';
import MpesaConfigTable from './MpesaConfigTable.vue';

interface MpesaConfig {
    id: number;
    company_id: number;
    branch_id: number | null;
    outlet_id: number | null;
    customer_id: number | null;
    payment_account_id: number | null;
    name: string;
    shortcode: string;
    environment: 'sandbox' | 'live';
    is_active: boolean;
    consumer_key?: string;
    consumer_secret?: string;
    passkey?: string;
    initiator_name?: string;
    initiator_password?: string;
    company?: { id: number; name: string };
    branch?: { id: number; name: string };
    outlet?: { id: number; name: string };
    customer?: {
        id: number;
        company_name: string;
        contact_person_names: string;
        name: string;
    };
    payment_account?: { id: number; name: string; account_number: string };
}

interface Props {
    configs: MpesaConfig[];
    companies: any[];
    branches: any[];
    outlets: any[];
    retailCustomers: any[];
    paymentAccounts: any[];
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Finance Management', href: '/organization/finance' },
    { title: 'M-Pesa Integrations', href: '#' },
];

const showModal = ref(false);
const editingConfig = ref<MpesaConfig | undefined>(undefined);
const searchQuery = ref('');

const filteredConfigs = computed(() => {
    if (!searchQuery.value) return props.configs;
    const query = searchQuery.value.toLowerCase();
    return props.configs.filter(
        (config) =>
            config.name.toLowerCase().includes(query) ||
            config.shortcode.includes(query) ||
            config.company?.name.toLowerCase().includes(query),
    );
});

const openCreateModal = () => {
    router.reload({
        only: [
            'paymentAccounts',
            'companies',
            'branches',
            'outlets',
            'retailCustomers',
            'configs',
        ],
    });
    editingConfig.value = undefined;
    showModal.value = true;
};

const openEditModal = (config: MpesaConfig) => {
    editingConfig.value = config;
    showModal.value = true;
    router.reload({
        only: [
            'paymentAccounts',
            'companies',
            'branches',
            'outlets',
            'retailCustomers',
        ],
    });
};

const closeModal = () => {
    showModal.value = false;
    editingConfig.value = undefined;
};

const handleToggleActive = (config: MpesaConfig) => {
    router.post(
        `/organization/financial-profiles/mpesa-configs/${config.id}/toggle-active`,
        {},
        {
            preserveScroll: true,
        },
    );
};

const handleDelete = (config: MpesaConfig) => {
    if (confirm(`Are you sure you want to delete "${config.name}"?`)) {
        router.delete(
            `/organization/financial-profiles/mpesa-configs/${config.id}`,
            {
                preserveScroll: true,
            },
        );
    }
};

const handleRegisterUrls = (config: MpesaConfig) => {
    if (
        confirm(`Register/Update C2B URLs for "${config.name}" with Safaricom?`)
    ) {
        router.post(
            `/organization/financial-profiles/mpesa-configs/${config.id}/register-urls`,
            {},
            {
                preserveScroll: true,
            },
        );
    }
};

const handleVerifyRegistration = (config: MpesaConfig) => {
    if (
        confirm(
            `Are you sure you want to manually mark URLs for "${config.name}" as verified? Only do this if you know they are already registered on Daraja.`,
        )
    ) {
        router.post(
            `/organization/financial-profiles/mpesa-configs/${config.id}/verify-registration`,
            {},
            {
                preserveScroll: true,
            },
        );
    }
};
</script>

<template>
    <Head title="M-Pesa Integrations" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex w-full flex-1 flex-col overflow-hidden p-4">
            <!-- Compact Header -->
            <div class="mb-4 flex shrink-0 items-center justify-between gap-4">
                <div>
                    <h1
                        class="text-2xl font-black tracking-tight text-foreground uppercase"
                    >
                        M-Pesa Integrations
                    </h1>
                    <p
                        class="text-[11px] font-bold tracking-wider text-muted-foreground uppercase"
                    >
                        API Keys & Paybill Management
                    </p>
                </div>

                <button
                    @click="openCreateModal"
                    class="flex items-center gap-1.5 rounded-lg bg-white px-4 py-2 text-xs font-black text-black shadow-md transition-all hover:bg-gray-200 active:scale-95"
                >
                    <Plus class="h-3.5 w-3.5" />
                    <span>Add Integration</span>
                </button>
            </div>

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
                            placeholder="Search by name, shortcode..."
                            class="w-full rounded-lg border-border bg-background/50 py-1.5 pr-3 pl-8 text-xs font-medium transition-all focus:border-primary focus:ring-primary/20"
                        />
                    </div>
                </div>

                <!-- Configs Table -->
                <MpesaConfigTable
                    :configs="filteredConfigs"
                    @edit="openEditModal"
                    @delete="handleDelete"
                    @toggle="handleToggleActive"
                    @register="handleRegisterUrls"
                    @verify="handleVerifyRegistration"
                />
            </div>
        </div>

        <!-- Mpesa Config Modal -->
        <MpesaConfigModal
            :show="showModal"
            :config="editingConfig"
            :companies="companies"
            :branches="branches"
            :outlets="outlets"
            :retail-customers="retailCustomers"
            :payment-accounts="paymentAccounts"
            @close="closeModal"
        />
    </AppLayout>
</template>
