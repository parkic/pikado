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
    type_label: string;
    sort_order: number;
};

type Option = {
    value: string | number;
    label: string;
};

type Options = {
    game_types: Option[];
    match_modes: Option[];
    group_rounds: Option[];
    knockout_sizes: Option[];
};

const props = defineProps<{
    venue: Venue;
    resources: Resource[];
    options: Options;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Novi turnir',
                href: '#',
            },
        ],
    },
});

const form = useForm({
    name: '',
    game_type: '301',
    match_mode: 'singles',
    group_rounds: 'single',
    knockout_size: 16 as number | null,
    public_enabled: true,
    resource_ids: props.resources.map((resource) => resource.id),
});

const submit = () => {
    form.post(`/venues/${props.venue.slug}/tournaments`);
};
</script>

<template>
    <Head :title="`Novi turnir - ${venue.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ venue.name }}
                </p>

                <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                    Novi turnir
                </h1>

                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    Kreiraj osnovni draft turnira. Grupe, učesnike i mečeve dodajemo u sledećim koracima.
                </p>
            </div>

            <Link
                :href="`/venues/${venue.slug}/tournaments`"
                class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
            >
                Nazad na turnire
            </Link>
        </div>

        <form
            class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]"
            @submit.prevent="submit"
        >
            <div class="flex flex-col gap-6">
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 class="text-lg font-medium">
                        Osnovne informacije
                    </h2>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Naziv, igra i osnovni format turnira.
                    </p>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="text-sm font-medium">
                                Naziv turnira
                            </label>

                            <input
                                v-model="form.name"
                                type="text"
                                class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                                placeholder="301 Single Out - 24. jun 2026"
                            >

                            <p
                                v-if="form.errors.name"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div>
                            <label class="text-sm font-medium">
                                Igra
                            </label>

                            <select
                                v-model="form.game_type"
                                class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                            >
                                <option
                                    v-for="option in options.game_types"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>

                            <p
                                v-if="form.errors.game_type"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ form.errors.game_type }}
                            </p>
                        </div>

                        <div>
                            <label class="text-sm font-medium">
                                Format
                            </label>

                            <select
                                v-model="form.match_mode"
                                class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                            >
                                <option
                                    v-for="option in options.match_modes"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>

                            <p
                                v-if="form.errors.match_mode"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ form.errors.match_mode }}
                            </p>
                        </div>

                        <div>
                            <label class="text-sm font-medium">
                                Grupna faza
                            </label>

                            <select
                                v-model="form.group_rounds"
                                class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                            >
                                <option
                                    v-for="option in options.group_rounds"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>

                            <p
                                v-if="form.errors.group_rounds"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ form.errors.group_rounds }}
                            </p>
                        </div>

                        <div>
                            <label class="text-sm font-medium">
                                Veličina nokauta
                            </label>

                            <select
                                v-model.number="form.knockout_size"
                                class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                            >
                                <option
                                    v-for="option in options.knockout_sizes"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>

                            <p
                                v-if="form.errors.knockout_size"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ form.errors.knockout_size }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 class="text-lg font-medium">
                        Resources za turnir
                    </h2>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Izabrani resources će biti kopirani iz podešavanja lokala u ovaj konkretan turnir.
                    </p>

                    <div
                        v-if="resources.length"
                        class="mt-4 grid gap-3 md:grid-cols-2"
                    >
                        <label
                            v-for="resource in resources"
                            :key="resource.id"
                            class="flex cursor-pointer items-start gap-3 rounded-lg border border-sidebar-border/70 p-3 transition hover:bg-muted dark:border-sidebar-border"
                        >
                            <input
                                v-model="form.resource_ids"
                                type="checkbox"
                                :value="resource.id"
                                class="mt-1"
                            >

                            <span>
                                <span class="block text-sm font-medium">
                                    {{ resource.name }}
                                </span>

                                <span class="mt-1 block text-xs text-muted-foreground">
                                    {{ resource.type_label }} · redosled {{ resource.sort_order }}
                                </span>
                            </span>
                        </label>
                    </div>

                    <div
                        v-else
                        class="mt-4 rounded-lg border border-dashed border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border"
                    >
                        Ovaj lokal još nema aktivne resources. Turnir možeš napraviti, ali neće imati opremu dok je ne dodaš.
                    </div>

                    <p
                        v-if="form.errors.resource_ids"
                        class="mt-3 text-sm text-red-600"
                    >
                        {{ form.errors.resource_ids }}
                    </p>
                </div>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border xl:sticky xl:top-4 xl:self-start">
                <h2 class="text-lg font-medium">
                    Review
                </h2>

                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Status</span>
                        <span class="font-medium">Draft</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Public link</span>
                        <span class="font-medium">
                            {{ form.public_enabled ? 'Uključen' : 'Isključen' }}
                        </span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Resources</span>
                        <span class="font-medium">
                            {{ form.resource_ids.length }}
                        </span>
                    </div>
                </div>

                <label class="mt-5 flex cursor-pointer items-center gap-3 text-sm">
                    <input
                        v-model="form.public_enabled"
                        type="checkbox"
                    >

                    <span>Public prikaz uključen</span>
                </label>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="mt-6 inline-flex w-full items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
                >
                    {{ form.processing ? 'Čuvam...' : 'Sačuvaj draft turnir' }}
                </button>
            </div>
        </form>
    </div>
</template>
