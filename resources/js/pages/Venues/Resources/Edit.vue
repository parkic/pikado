<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

type Venue = {
    id: number;
    name: string;
    slug: string;
};

type Resource = {
    id: number;
    name: string;
    type: string;
    sort_order: number;
    is_active: boolean;
};

type ResourceType = {
    label: string;
    value: string;
};

const props = defineProps<{
    venue: Venue;
    resource: Resource;
    resourceTypes: ResourceType[];
}>();

const form = useForm({
    name: props.resource.name,
    type: props.resource.type,
    sort_order: props.resource.sort_order,
    is_active: props.resource.is_active,
});

const submit = () => {
    form.put(`/venues/${props.venue.slug}/resources/${props.resource.id}`);
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Izmeni opremu',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <Head :title="`Izmeni opremu - ${venue.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div
            class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
        >
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ venue.name }}
                </p>

                <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                    Izmeni opremu
                </h1>

                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    Izmeni naziv, tip, redosled ili status postojeće opreme
                    lokala.
                </p>
            </div>

            <Link
                :href="`/venues/${venue.slug}/resources`"
                class="inline-flex items-center justify-center rounded-md border border-sidebar-border/70 px-4 py-2 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
            >
                Nazad na opremu
            </Link>
        </div>

        <form
            class="max-w-2xl rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            @submit.prevent="submit"
        >
            <div class="space-y-5">
                <div>
                    <label for="name" class="text-sm font-medium">
                        Naziv
                    </label>

                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="mt-2 w-full rounded-md border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring dark:border-sidebar-border"
                        placeholder="Primer: Levi pikado"
                    />

                    <p
                        v-if="form.errors.name"
                        class="mt-2 text-sm text-red-500"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <div>
                    <label for="type" class="text-sm font-medium"> Tip </label>

                    <select
                        id="type"
                        v-model="form.type"
                        class="mt-2 w-full rounded-md border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring dark:border-sidebar-border"
                    >
                        <option
                            v-for="resourceType in resourceTypes"
                            :key="resourceType.value"
                            :value="resourceType.value"
                        >
                            {{ resourceType.label }}
                        </option>
                    </select>

                    <p
                        v-if="form.errors.type"
                        class="mt-2 text-sm text-red-500"
                    >
                        {{ form.errors.type }}
                    </p>
                </div>

                <div>
                    <label for="sort_order" class="text-sm font-medium">
                        Redosled
                    </label>

                    <input
                        id="sort_order"
                        v-model="form.sort_order"
                        type="number"
                        min="0"
                        class="mt-2 w-full rounded-md border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring dark:border-sidebar-border"
                    />

                    <p
                        v-if="form.errors.sort_order"
                        class="mt-2 text-sm text-red-500"
                    >
                        {{ form.errors.sort_order }}
                    </p>
                </div>

                <label class="flex items-center gap-3">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="rounded border-sidebar-border/70"
                    />

                    <span class="text-sm font-medium"> Aktivna oprema </span>
                </label>

                <p v-if="form.errors.is_active" class="text-sm text-red-500">
                    {{ form.errors.is_active }}
                </p>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                >
                    Sačuvaj izmene
                </button>

                <Link
                    :href="`/venues/${venue.slug}/resources`"
                    class="inline-flex items-center justify-center rounded-md border border-sidebar-border/70 px-4 py-2 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
                >
                    Otkaži
                </Link>
            </div>
        </form>
    </div>
</template>
