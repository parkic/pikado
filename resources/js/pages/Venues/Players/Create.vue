<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    first_name: '',
    last_name: '',
    nickname: '',
    notes: '',
    is_active: true,
});

const submit = () => {
    form.post('/admin/players');
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Create player',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <Head title="Dodaj igrača" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div
            class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
        >
            <div>
                <p class="text-sm font-medium text-primary">Aplikacija</p>

                <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                    Dodaj igrača
                </h1>

                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    Kreiraj globalni profil koji može da učestvuje na turnirima
                    u bilo kom lokalu.
                </p>
            </div>

            <Link
                href="/admin/players"
                class="inline-flex items-center justify-center rounded-md border border-sidebar-border/70 px-4 py-2 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
            >
                Nazad na igrače
            </Link>
        </div>

        <form
            class="max-w-2xl rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            @submit.prevent="submit"
        >
            <div class="space-y-5">
                <div>
                    <label for="first_name" class="text-sm font-medium">
                        Ime
                    </label>

                    <input
                        id="first_name"
                        v-model="form.first_name"
                        type="text"
                        class="mt-2 w-full rounded-md border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring dark:border-sidebar-border"
                        placeholder="Primer: Marko"
                    />

                    <p
                        v-if="form.errors.first_name"
                        class="mt-2 text-sm text-red-500"
                    >
                        {{ form.errors.first_name }}
                    </p>
                </div>

                <div>
                    <label for="last_name" class="text-sm font-medium">
                        Prezime
                    </label>

                    <input
                        id="last_name"
                        v-model="form.last_name"
                        type="text"
                        class="mt-2 w-full rounded-md border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring dark:border-sidebar-border"
                        placeholder="Primer: Marković"
                    />

                    <p
                        v-if="form.errors.last_name"
                        class="mt-2 text-sm text-red-500"
                    >
                        {{ form.errors.last_name }}
                    </p>
                </div>

                <div>
                    <label for="nickname" class="text-sm font-medium">
                        Nadimak
                    </label>

                    <input
                        id="nickname"
                        v-model="form.nickname"
                        type="text"
                        class="mt-2 w-full rounded-md border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring dark:border-sidebar-border"
                        placeholder="Primer: Mare"
                    />

                    <p
                        v-if="form.errors.nickname"
                        class="mt-2 text-sm text-red-500"
                    >
                        {{ form.errors.nickname }}
                    </p>
                </div>

                <div>
                    <label for="notes" class="text-sm font-medium">
                        Napomena
                    </label>

                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="4"
                        class="mt-2 w-full rounded-md border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring dark:border-sidebar-border"
                        placeholder="Opciona interna napomena"
                    />

                    <p
                        v-if="form.errors.notes"
                        class="mt-2 text-sm text-red-500"
                    >
                        {{ form.errors.notes }}
                    </p>
                </div>

                <label class="flex items-center gap-3">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="rounded border-sidebar-border/70"
                    />

                    <span class="text-sm font-medium"> Aktivan igrač </span>
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
                    Sačuvaj igrača
                </button>

                <Link
                    href="/admin/players"
                    class="inline-flex items-center justify-center rounded-md border border-sidebar-border/70 px-4 py-2 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
                >
                    Otkaži
                </Link>
            </div>
        </form>
    </div>
</template>
