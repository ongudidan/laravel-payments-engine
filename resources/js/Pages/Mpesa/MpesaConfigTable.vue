<script setup lang="ts">
import {
    Building2,
    CheckCircle2,
    Globe,
    Link2,
    Pencil,
    Power,
    ShieldAlert,
    ShieldCheck,
    Store,
    Trash2,
    User,
} from 'lucide-vue-next';

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
    urls_registered_at: string | null;
    last_registration_response: any | null;
    company?: { id: number; name: string };
    branch?: { id: number; name: string };
    outlet?: { id: number; name: string };
    customer?: {
        id: number;
        company_name: string;
        contact_person_names: string;
        name: string;
        mpesa_name: string;
    };
    payment_account?: { id: number; name: string; account_number: string };
    consumer_key?: string;
    consumer_secret?: string;
    passkey?: string;
    initiator_name?: string;
    initiator_password?: string;
}

interface Props {
    configs: MpesaConfig[];
}

defineProps<Props>();

const emit = defineEmits(['edit', 'delete', 'toggle', 'register', 'verify']);
</script>

<template>
    <div class="flex-1 overflow-auto">
        <table
            class="w-full border-collapse border-separate border-spacing-0 text-left"
        >
            <thead
                class="sticky top-0 z-10 border-b border-border bg-card/95 shadow-sm backdrop-blur-md"
            >
                <tr>
                    <th
                        class="border-b border-border p-3 pl-6 text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                    >
                        Configuration / Paybill
                    </th>
                    <th
                        class="border-b border-border p-3 text-center text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                    >
                        Environment
                    </th>
                    <th
                        class="border-b border-border p-3 text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                    >
                        Assigned Target
                    </th>
                    <th
                        class="border-b border-border p-3 text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                    >
                        URL Registration
                    </th>
                    <th
                        class="border-b border-border p-3 pr-12 text-right text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                    >
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border/30">
                <tr
                    v-for="config in configs"
                    :key="config.id"
                    class="group transition-all hover:bg-accent/5"
                >
                    <td class="p-4 pl-6">
                        <div class="flex items-center gap-4">
                            <div
                                :class="
                                    config.is_active
                                        ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-500'
                                        : 'border-rose-500/20 bg-rose-500/10 text-rose-500'
                                "
                                class="flex h-10 w-10 items-center justify-center rounded-xl border transition-transform group-hover:scale-110"
                            >
                                <Globe
                                    v-if="config.is_active"
                                    class="h-5 w-5"
                                />
                                <Power v-else class="h-5 w-5" />
                            </div>
                            <div>
                                <h3
                                    class="text-sm leading-tight font-bold text-foreground"
                                >
                                    {{ config.name }}
                                </h3>
                                <div class="mt-1 flex items-center gap-2">
                                    <span
                                        class="rounded border border-primary/10 bg-primary/5 px-2 py-0.5 text-[10px] font-black tracking-tighter text-primary uppercase"
                                    >
                                        PAYBILL: {{ config.shortcode }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="p-4 text-center">
                        <div
                            :class="
                                config.environment === 'live'
                                    ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-500'
                                    : 'border-amber-500/20 bg-amber-500/10 text-amber-500'
                            "
                            class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[9px] font-black tracking-widest uppercase"
                        >
                            <ShieldCheck
                                v-if="config.environment === 'live'"
                                class="h-3 w-3"
                            />
                            <ShieldAlert v-else class="h-3 w-3" />
                            {{ config.environment }}
                        </div>
                    </td>
                    <td class="p-4">
                        <div class="flex flex-col gap-1">
                            <div
                                v-if="config.outlet"
                                class="flex items-center gap-2 text-xs font-bold text-foreground"
                            >
                                <Store class="h-3.5 w-3.5 text-primary" />
                                {{ config.outlet.name }}
                            </div>
                            <div
                                v-else-if="config.customer"
                                class="flex items-center gap-2 text-xs font-bold text-foreground"
                            >
                                <User class="h-3.5 w-3.5 text-primary" />
                                <div>
                                    <p>
                                        {{
                                            config.customer.company_name ||
                                            config.customer.mpesa_name
                                        }}
                                    </p>
                                    <p
                                        v-if="
                                            config.customer.contact_person_names
                                        "
                                        class="text-[9px] font-medium text-muted-foreground opacity-70"
                                    >
                                        {{
                                            config.customer.contact_person_names
                                        }}
                                    </p>
                                </div>
                            </div>
                            <div
                                v-else
                                class="flex w-fit items-center gap-2 rounded border border-amber-500/10 bg-amber-500/5 px-2 py-0.5 text-[10px] font-black tracking-tighter text-amber-500 uppercase"
                            >
                                <Globe class="h-2.5 w-2.5" />
                                BRANCH GLOBAL
                            </div>
                            <div
                                v-if="config.branch"
                                class="flex items-center gap-1.5 pl-5 text-[10px] font-medium tracking-tight text-muted-foreground uppercase"
                            >
                                <Building2 class="h-2.5 w-2.5" />
                                {{ config.branch.name }}
                            </div>
                        </div>
                    </td>
                    <td class="p-4">
                        <div
                            v-if="config.urls_registered_at"
                            class="flex flex-col gap-1"
                        >
                            <div
                                class="flex items-center gap-1.5 text-emerald-500"
                            >
                                <CheckCircle2 class="h-3.5 w-3.5" />
                                <span
                                    class="text-[10px] font-black tracking-widest uppercase"
                                    >Registered</span
                                >
                            </div>
                            <span
                                class="pl-5 text-[9px] text-muted-foreground"
                                >{{
                                    new Date(
                                        config.urls_registered_at,
                                    ).toLocaleDateString()
                                }}</span
                            >
                        </div>
                        <div v-else class="flex flex-col gap-1 text-left">
                            <div
                                class="flex items-center gap-1.5 text-amber-500"
                            >
                                <ShieldAlert class="h-3.5 w-3.5" />
                                <span
                                    class="text-[10px] leading-none font-black tracking-widest uppercase"
                                    >Pending Registration</span
                                >
                            </div>
                            <div class="mt-1 flex flex-col items-start gap-2">
                                <button
                                    @click="emit('register', config)"
                                    class="pl-5 text-[9px] font-black tracking-widest text-primary uppercase transition-all outline-none hover:underline"
                                >
                                    Automatic Register →
                                </button>
                                <button
                                    @click="emit('verify', config)"
                                    class="pl-5 text-[9px] font-black tracking-widest text-emerald-600 uppercase transition-all outline-none hover:underline"
                                >
                                    Manual Verify (Daraja) →
                                </button>
                            </div>
                        </div>
                    </td>
                    <td class="p-4 pr-6 text-right">
                        <div
                            class="flex items-center justify-end gap-1 sm:gap-2"
                        >
                            <button
                                @click="emit('register', config)"
                                class="rounded-lg p-2 text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary"
                                title="Regiser / Update URLs with Safaricom"
                            >
                                <Link2 class="h-4 w-4" />
                            </button>
                            <button
                                @click="emit('toggle', config)"
                                class="rounded-lg p-2 text-muted-foreground transition-colors hover:bg-accent/10 hover:text-foreground"
                                :title="
                                    config.is_active ? 'Deactivate' : 'Activate'
                                "
                            >
                                <Power
                                    class="h-4 w-4"
                                    :class="
                                        config.is_active
                                            ? 'text-rose-500'
                                            : 'text-emerald-500'
                                    "
                                />
                            </button>
                            <button
                                @click="emit('edit', config)"
                                class="rounded-lg p-2 text-muted-foreground transition-colors hover:bg-emerald-500/10 hover:text-emerald-500"
                                title="Edit Configuration"
                            >
                                <Pencil class="h-4 w-4" />
                            </button>
                            <button
                                @click="emit('delete', config)"
                                class="rounded-lg p-2 text-muted-foreground transition-colors hover:bg-rose-500/10 hover:text-rose-500"
                                title="Delete Integration"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <div
            v-if="configs.length === 0"
            class="flex flex-col items-center justify-center p-20 text-center"
        >
            <div
                class="mb-6 flex h-20 w-20 items-center justify-center rounded-3xl border border-dashed border-border bg-accent/5 text-muted-foreground"
            >
                <Globe class="h-10 w-10 opacity-20" />
            </div>
            <h3
                class="text-sm font-black tracking-[0.3em] text-foreground uppercase"
            >
                No M-Pesa Integrations
            </h3>
            <p
                class="mt-2 max-w-xs text-xs font-bold tracking-wider text-muted-foreground/60 uppercase"
            >
                Link your bank-connected payment accounts to enable STK push and
                C2B notifications.
            </p>
        </div>
    </div>
</template>
