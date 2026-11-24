<script setup lang="ts">
interface BreadcrumbItem {
    title: string;
    href: string;
}

interface Props {
    breadcrumbs?: BreadcrumbItem[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <div class="min-h-screen bg-background text-foreground flex flex-col font-sans antialiased">
        <header v-if="breadcrumbs.length" class="border-b border-border bg-card/50 px-6 py-3 backdrop-blur-md">
            <nav class="flex items-center space-x-2 text-xs text-muted-foreground">
                <template v-for="(item, index) in breadcrumbs" :key="index">
                    <span v-if="index > 0" class="text-muted-foreground/40">/</span>
                    <a v-if="item.href !== '#'" :href="item.href" class="hover:text-foreground transition-colors font-medium">
                        {{ item.title }}
                    </a>
                    <span v-else class="text-foreground font-semibold">{{ item.title }}</span>
                </template>
            </nav>
        </header>
        <main class="flex-1 p-6">
            <slot />
        </main>
    </div>
</template>
