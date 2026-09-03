<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Izgled',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Izgled" />

    <h1 class="sr-only">Izgled</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Izgled aplikacije"
            description="Izaberi svetlu, tamnu ili sistemsku temu"
        />
        <AppearanceTabs />
    </div>
</template>
