<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface Props {
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
    from?: number;
    to?: number;
    total?: number;
    only?: string[];
}

defineProps<Props>();
</script>

<template>
    <div
        v-if="links && links.length > 0"
        class="flex shrink-0 items-center justify-between border-t border-border bg-accent/5 px-4 py-2"
    >
        <div
            v-if="total !== undefined"
            class="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
        >
            Showing
            <span class="font-black text-foreground">{{ from }}</span> to
            <span class="font-black text-foreground">{{ to }}</span> of
            {{ total }} Records
        </div>
        <div v-else></div>

        <div class="flex gap-1.5">
            <Link
                v-for="(link, key) in links"
                :key="key"
                :href="link.url || '#'"
                preserve-scroll
                preserve-state
                :only="only"
                class="rounded-lg border border-transparent px-2.5 py-1 text-[10px] font-black whitespace-nowrap uppercase transition-all"
                :class="[
                    link.active
                        ? 'border-foreground bg-foreground text-background shadow-md'
                        : 'border-border bg-white text-muted-foreground hover:bg-accent/10 hover:text-foreground',
                    !link.url && 'pointer-events-none opacity-30',
                ]"
            >
                <span v-html="link.label"></span>
            </Link>
        </div>
    </div>
</template>
