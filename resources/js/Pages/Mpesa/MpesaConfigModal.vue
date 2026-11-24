<script setup lang="ts">
import SearchableSelect from '@/components/SearchableSelect.vue';
import { useForm } from '@inertiajs/vue3';
import {
    Building2,
    CreditCard,
    Globe,
    Key,
    ShieldAlert,
    ShieldCheck,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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
}

interface Props {
    show: boolean;
    config?: MpesaConfig;
    companies: any[];
    branches: any[];
    outlets: any[];
    retailCustomers: any[];
    paymentAccounts: any[];
}

const props = defineProps<Props>();

const emit = defineEmits(['close']);

const form = useForm({
    company_id: '',
    branch_id: '',
    outlet_id: '',
    customer_id: '',
    payment_account_id: '',
    name: '',
    shortcode: '',
    consumer_key: '',
    consumer_secret: '',
    passkey: '',
    initiator_name: '',
    initiator_password: '',
    environment: 'live',
    is_active: true,
});

const showSecrets = ref(false);
const assignmentType = ref('outlet');

watch(
    () => props.config,
    (newVal) => {
        if (newVal) {
            form.company_id = newVal.company_id || '';
            form.branch_id = newVal.branch_id || '';
            form.outlet_id = newVal.outlet_id || '';
            form.customer_id = newVal.customer_id || '';
            assignmentType.value = newVal.customer_id ? 'customer' : 'outlet';
            form.payment_account_id = newVal.payment_account_id || '';
            form.name = newVal.name || '';
            form.shortcode = newVal.shortcode || '';
            form.environment = newVal.environment || 'sandbox';
            form.is_active = newVal.is_active ?? true;

            // Fill keys if available
            form.consumer_key = newVal.consumer_key || '';
            form.consumer_secret = newVal.consumer_secret || '';
            form.passkey = newVal.passkey || '';
            form.initiator_name = newVal.initiator_name || '';
            form.initiator_password = newVal.initiator_password || '';
        } else {
            form.reset();
            assignmentType.value = 'outlet';
            form.environment = 'live';
            form.is_active = true;
        }
    },
    { immediate: true },
);

watch(assignmentType, (val) => {
    if (val === 'outlet') {
        form.customer_id = '';
    } else {
        form.outlet_id = '';
    }
});

const filteredBranches = computed(() => {
    if (!form.company_id) return [];
    return props.branches.filter((b) => b.company_id == form.company_id);
});

const filteredOutlets = computed(() => {
    if (!form.branch_id) return [];
    return props.outlets.filter((o) => o.branch_id == form.branch_id);
});

const selectedOutlet = computed(() => {
    if (!form.outlet_id) return null;
    return props.outlets.find((o) => o.id == form.outlet_id);
});

const filteredCustomers = computed(() => {
    if (!form.branch_id) return [];
    return props.retailCustomers.filter((c) => c.branch_id == form.branch_id);
});

const selectedCustomer = computed(() => {
    if (!form.customer_id) return null;
    return props.retailCustomers.find((c) => c.id == form.customer_id);
});

const isCustomerConflict = computed(() => {
    if (!selectedCustomer.value) return false;
    return (
        selectedCustomer.value.is_linked &&
        (!props.config || selectedCustomer.value.id != props.config.customer_id)
    );
});

const isOutletConflict = computed(() => {
    if (!selectedOutlet.value) return false;
    // Conflict if it's linked AND it's not the one we are currently editing
    return (
        selectedOutlet.value.is_linked &&
        (!props.config || selectedOutlet.value.id != props.config.outlet_id)
    );
});

const filteredPaymentAccounts = computed(() => {
    if (!form.branch_id) return [];
    return props.paymentAccounts.filter((pa) => {
        const isCurrentBranch = pa.branch_id == form.branch_id;
        // If the account is linked and not the one currently being edited, hide it
        const isAvailable =
            !pa.is_linked ||
            (props.config && pa.id == props.config.payment_account_id);
        return isCurrentBranch && isAvailable;
    });
});

// Watch payment account selection to sync shortcode
watch(
    () => form.payment_account_id,
    (newAccountId) => {
        if (newAccountId) {
            const account = props.paymentAccounts.find(
                (pa) => pa.id == newAccountId,
            );
            if (account) {
                form.shortcode = account.account_number || '';
                // If name is empty, suggest one
                if (!form.name) {
                    form.name = `M-Pesa: ${account.name}`;
                }
            }
        }
    },
);

const submit = () => {
    if (isOutletConflict.value || isCustomerConflict.value) return;

    const url = props.config
        ? `/organization/financial-profiles/mpesa-configs/${props.config.id}`
        : '/organization/financial-profiles/mpesa-configs';

    form.post(url, {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
            form.reset();
            showSecrets.value = false;
        },
    });
};

const handleClose = () => {
    form.reset();
    showSecrets.value = false;
    emit('close');
};
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/60 p-4 sm:p-6"
    >
        <div
            class="flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-lg border border-border bg-card/95 shadow-xl"
        >
            <!-- Header -->
            <div
                class="flex shrink-0 items-center justify-between border-b border-border px-4 py-3"
            >
                <div class="flex items-center gap-2">
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-500/20 bg-emerald-500/10 text-emerald-500"
                    >
                        <Key class="h-4 w-4" />
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-foreground">
                            {{
                                config
                                    ? 'Edit M-Pesa Integration'
                                    : 'New M-Pesa Integration'
                            }}
                        </h2>
                        <p
                            class="text-[9px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Configure API Credentials
                        </p>
                    </div>
                </div>
                <button
                    @click="handleClose"
                    class="rounded-lg p-2 text-muted-foreground transition-colors hover:bg-accent/10 hover:text-foreground"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <!-- Form Body -->
            <form
                @submit.prevent="submit"
                class="flex flex-1 flex-col gap-4 overflow-y-auto p-4"
            >
                <!-- Selection Filters & Target Outlet -->
                <div
                    class="grid grid-cols-1 gap-3 rounded-xl border border-border/50 bg-accent/5 p-4 md:grid-cols-2"
                >
                    <div
                        class="col-span-1 mb-0.5 flex items-center gap-2 md:col-span-2"
                    >
                        <Building2 class="h-3.5 w-3.5 text-primary" />
                        <span
                            class="text-xs font-black tracking-tight text-foreground uppercase"
                            >Integration Scope & Target</span
                        >
                    </div>

                    <div class="flex flex-col gap-1">
                        <label
                            class="block pl-1 text-[10px] font-bold text-muted-foreground uppercase"
                            >Primary Company
                            <span class="text-red-500">*</span></label
                        >
                        <SearchableSelect
                            v-model="form.company_id"
                            :options="companies"
                            label="name"
                            value-key="id"
                            placeholder="Select Company"
                        />
                        <span
                            v-if="form.errors.company_id"
                            class="px-1 text-[10px] text-red-400"
                            >{{ form.errors.company_id }}</span
                        >
                    </div>

                    <div class="flex flex-col gap-1">
                        <label
                            class="block pl-1 text-[10px] font-bold text-muted-foreground uppercase"
                            >Target Branch
                            <span class="text-red-500">*</span></label
                        >
                        <SearchableSelect
                            v-model="form.branch_id"
                            :options="filteredBranches"
                            label="name"
                            value-key="id"
                            placeholder="Select Branch"
                            :disabled="!form.company_id"
                        />
                        <span
                            v-if="form.errors.branch_id"
                            class="px-1 text-[10px] text-red-400"
                            >{{ form.errors.branch_id }}</span
                        >
                    </div>

                    <div
                        class="mt-1 flex flex-col gap-2 border-t border-border/40 pt-3 md:col-span-2"
                    >
                        <div class="flex items-center gap-4">
                            <label
                                class="group flex cursor-pointer items-center gap-2"
                            >
                                <input
                                    type="radio"
                                    v-model="assignmentType"
                                    value="outlet"
                                    class="h-3.5 w-3.5 border-border bg-background text-primary focus:ring-primary"
                                />
                                <span
                                    :class="
                                        assignmentType === 'outlet'
                                            ? 'font-bold text-foreground'
                                            : 'text-muted-foreground'
                                    "
                                    class="text-[10px] font-black uppercase transition-colors"
                                    >Assign to Outlet</span
                                >
                            </label>
                            <label
                                class="group flex cursor-pointer items-center gap-2"
                            >
                                <input
                                    type="radio"
                                    v-model="assignmentType"
                                    value="customer"
                                    class="h-3.5 w-3.5 border-border bg-background text-primary focus:ring-primary"
                                />
                                <span
                                    :class="
                                        assignmentType === 'customer'
                                            ? 'font-bold text-foreground'
                                            : 'text-muted-foreground'
                                    "
                                    class="text-[10px] font-black uppercase transition-colors"
                                    >Assign to Retail Customer</span
                                >
                            </label>
                        </div>

                        <div
                            v-if="assignmentType === 'outlet'"
                            class="flex animate-in flex-col gap-1 duration-200 fade-in slide-in-from-top-1"
                        >
                            <SearchableSelect
                                v-model="form.outlet_id"
                                :options="filteredOutlets"
                                label="name"
                                value-key="id"
                                placeholder="Select Target Outlet (Optional)"
                                :disabled="!form.branch_id"
                            />
                            <span
                                v-if="form.errors.outlet_id"
                                class="px-1 text-[10px] text-red-400"
                                >{{ form.errors.outlet_id }}</span
                            >
                            <p
                                v-if="isOutletConflict"
                                class="mt-1 flex animate-pulse items-center gap-1 pl-1 text-[9px] font-bold text-amber-500"
                            >
                                <ShieldAlert class="h-2.5 w-2.5" />
                                Warning: Already assigned to another paybill!
                            </p>
                        </div>

                        <div
                            v-else
                            class="flex animate-in flex-col gap-1 duration-200 fade-in slide-in-from-top-1"
                        >
                            <SearchableSelect
                                v-model="form.customer_id"
                                :options="filteredCustomers"
                                label="company_name"
                                value-key="id"
                                placeholder="Select Retail Customer (Optional)"
                                :disabled="!form.branch_id"
                            />
                            <span
                                v-if="form.errors.customer_id"
                                class="px-1 text-[10px] text-red-400"
                                >{{ form.errors.customer_id }}</span
                            >
                            <p
                                v-if="isCustomerConflict"
                                class="mt-1 flex animate-pulse items-center gap-1 pl-1 text-[9px] font-bold text-amber-500"
                            >
                                <ShieldAlert class="h-2.5 w-2.5" />
                                Warning: Already assigned to another paybill!
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Account & Identity -->
                <div
                    class="grid grid-cols-1 gap-3 rounded-xl border border-primary/10 bg-primary/5 p-4 md:grid-cols-2"
                >
                    <div
                        class="col-span-1 mb-0.5 flex items-center gap-2 md:col-span-2"
                    >
                        <CreditCard class="h-3.5 w-3.5 text-primary" />
                        <span
                            class="text-xs font-black tracking-tight text-foreground uppercase"
                            >Account Linkage</span
                        >
                    </div>

                    <div class="flex flex-col gap-1">
                        <label
                            class="block pl-1 text-[10px] font-bold text-muted-foreground uppercase"
                            >Payment Account
                            <span class="text-red-500">*</span></label
                        >
                        <SearchableSelect
                            v-model="form.payment_account_id"
                            :options="filteredPaymentAccounts"
                            label="name"
                            value-key="id"
                            placeholder="Link to Bank Account"
                            :disabled="!form.branch_id"
                        />
                        <span
                            v-if="form.errors.payment_account_id"
                            class="px-1 text-[10px] text-red-400"
                            >{{ form.errors.payment_account_id }}</span
                        >
                    </div>

                    <div class="flex flex-col gap-1">
                        <label
                            class="block pl-1 text-[10px] font-bold text-muted-foreground uppercase"
                            >Display Name
                            <span class="text-red-500">*</span></label
                        >
                        <input
                            v-model="form.name"
                            type="text"
                            placeholder="e.g. Main POS Paybill"
                            required
                            class="w-full rounded-lg border border-border bg-card/40 px-3 py-1.5 text-xs font-medium text-foreground transition-all placeholder:text-muted-foreground focus:border-primary focus:ring-2 focus:ring-accent/20 focus:outline-none"
                        />
                        <span
                            v-if="form.errors.name"
                            class="px-1 text-[10px] text-red-400"
                            >{{ form.errors.name }}</span
                        >
                    </div>

                    <div class="flex flex-col gap-1 md:col-span-2">
                        <div class="group relative">
                            <input
                                :value="form.shortcode"
                                type="text"
                                readonly
                                class="w-full cursor-not-allowed rounded-lg border border-primary/20 bg-background/50 px-3 py-1.5 text-xs font-black text-primary transition-all"
                                placeholder="Derived Paybill Number"
                            />
                            <div
                                class="absolute inset-y-0 right-3 flex items-center gap-2"
                            >
                                <span
                                    class="text-[9px] text-muted-foreground italic"
                                    >Auto-derived Shortcode</span
                                >
                                <ShieldCheck
                                    v-if="form.shortcode"
                                    class="h-3.5 w-3.5 text-primary/40"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- API Credentials Section -->
                <div
                    class="flex flex-col gap-3 rounded-xl border border-border bg-card/50 p-4 shadow-sm"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Globe class="h-3.5 w-3.5 text-primary" />
                            <span
                                class="text-[11px] font-black tracking-widest text-foreground uppercase"
                                >API Credentials</span
                            >
                        </div>
                        <div
                            class="flex scale-90 rounded-lg border border-border bg-background p-0.5"
                        >
                            <button
                                type="button"
                                @click="form.environment = 'live'"
                                :class="
                                    form.environment === 'live'
                                        ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-500'
                                        : 'text-muted-foreground hover:bg-accent/10'
                                "
                                class="rounded-md px-3 py-1 text-[9px] font-black tracking-widest uppercase transition-all"
                            >
                                Live
                            </button>
                            <button
                                type="button"
                                @click="form.environment = 'sandbox'"
                                :class="
                                    form.environment === 'sandbox'
                                        ? 'border-amber-500/20 bg-amber-500/10 text-amber-500'
                                        : 'text-muted-foreground hover:bg-accent/10'
                                "
                                class="rounded-md px-3 py-1 text-[9px] font-black tracking-widest uppercase transition-all"
                            >
                                Sandbox
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div class="flex flex-col gap-1">
                            <label
                                class="block pl-1 text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                                >Consumer Key</label
                            >
                            <input
                                v-model="form.consumer_key"
                                type="text"
                                placeholder="Consumer Key"
                                class="w-full rounded-lg border-border bg-card/40 px-3 py-1.5 font-mono text-xs text-foreground shadow-sm transition-all focus:border-primary focus:outline-none"
                            />
                            <span
                                v-if="form.errors.consumer_key"
                                class="px-1 text-[10px] text-red-400"
                                >{{ form.errors.consumer_key }}</span
                            >
                        </div>
                        <div class="flex flex-col gap-1">
                            <label
                                class="block pl-1 text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                                >Consumer Secret</label
                            >
                            <input
                                v-model="form.consumer_secret"
                                type="text"
                                placeholder="Consumer Secret"
                                class="w-full rounded-lg border-border bg-card/40 px-3 py-1.5 font-mono text-xs text-foreground shadow-sm transition-all focus:border-primary focus:outline-none"
                            />
                            <span
                                v-if="form.errors.consumer_secret"
                                class="px-1 text-[10px] text-red-400"
                                >{{ form.errors.consumer_secret }}</span
                            >
                        </div>
                    </div>
                </div>

                <!-- Advanced Section -->
                <div
                    class="grid grid-cols-1 gap-3 rounded-xl border border-dashed border-border/60 bg-accent/5 p-4 md:grid-cols-2"
                >
                    <div
                        class="col-span-1 mb-0.5 flex items-center gap-2 md:col-span-2"
                    >
                        <span
                            class="text-[10px] font-black tracking-[0.2em] text-muted-foreground uppercase"
                            >Advanced Endpoints (STK/B2C)</span
                        >
                        <span class="h-px flex-1 bg-border/40"></span>
                    </div>
                    <div class="flex flex-col gap-1 md:col-span-2">
                        <label
                            class="block pl-1 text-[9px] font-black text-muted-foreground uppercase"
                            >LNDM Passkey (Online Payments)</label
                        >
                        <input
                            v-model="form.passkey"
                            type="text"
                            placeholder="Enter Passkey"
                            class="w-full rounded-lg border-border bg-card/40 px-3 py-1.5 font-mono text-xs text-foreground shadow-sm focus:border-primary focus:outline-none"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label
                            class="block pl-1 text-[9px] font-black text-muted-foreground uppercase"
                            >Initiator Name</label
                        >
                        <input
                            v-model="form.initiator_name"
                            type="text"
                            placeholder="e.g. mpesa_api_user"
                            class="w-full rounded-lg border-border bg-card/40 px-3 py-1.5 text-xs font-medium text-foreground focus:border-primary focus:outline-none"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label
                            class="block pl-1 text-[9px] font-black text-muted-foreground uppercase"
                            >Initiator Password</label
                        >
                        <input
                            v-model="form.initiator_password"
                            type="text"
                            placeholder="Enter Password"
                            class="w-full rounded-lg border-border bg-card/40 px-3 py-1.5 font-mono text-xs text-foreground shadow-sm focus:border-primary focus:outline-none"
                        />
                    </div>
                </div>

                <!-- Footer Integration -->
                <div
                    class="mt-2 flex shrink-0 items-center justify-between border-t border-border pt-4"
                >
                    <div class="flex items-center gap-2">
                        <div
                            v-if="config"
                            class="flex items-center gap-1.5 rounded-md border border-emerald-500/20 bg-emerald-500/10 px-2 py-1 text-emerald-500"
                        >
                            <ShieldCheck class="h-3.5 w-3.5" />
                            <span
                                class="text-[9px] font-black tracking-widest uppercase"
                                >Active</span
                            >
                        </div>
                        <div
                            v-else
                            class="flex items-center gap-1.5 rounded-md border border-amber-500/20 bg-amber-500/10 px-2 py-1 text-amber-500"
                        >
                            <ShieldAlert class="h-3.5 w-3.5" />
                            <span
                                class="text-[9px] font-black tracking-widest uppercase"
                                >New</span
                            >
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="handleClose"
                            class="rounded-lg px-4 py-1.5 text-[10px] font-black tracking-widest text-muted-foreground uppercase transition-colors hover:bg-accent/10"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="
                                form.processing ||
                                isOutletConflict ||
                                isCustomerConflict
                            "
                            class="rounded-lg bg-white px-6 py-1.5 text-[10px] font-black tracking-widest text-black uppercase shadow-lg transition-all hover:bg-gray-200 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{
                                form.processing
                                    ? 'Saving...'
                                    : config
                                      ? 'Update Integration'
                                      : 'Save Integration'
                            }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>
