<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    AtSign,
    Building2,
    Camera,
    Globe2,
    MapPin,
    Moon,
    Phone,
    Save,
    Sun,
    Trash2,
} from '@lucide/vue';
import { computed, onUnmounted, ref } from 'vue';

type Venue = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    address: string | null;
    phone: string | null;
    website_url: string | null;
    instagram_url: string | null;
    public_theme: 'dark' | 'light';
    logo_url: string | null;
};

const props = defineProps<{
    venue: Venue;
}>();

const form = useForm({
    _method: 'put',
    name: props.venue.name,
    description: props.venue.description ?? '',
    address: props.venue.address ?? '',
    phone: props.venue.phone ?? '',
    website_url: props.venue.website_url ?? '',
    instagram_url: props.venue.instagram_url ?? '',
    public_theme: props.venue.public_theme,
    logo: null as File | null,
    remove_logo: false,
});

const localLogoUrl = ref<string | null>(null);
const logoInput = ref<HTMLInputElement | null>(null);

const logoPreviewUrl = computed(() => {
    if (form.remove_logo) {
        return null;
    }

    return localLogoUrl.value ?? props.venue.logo_url;
});

const venueInitials = computed(() =>
    form.name
        .split(/\s+/)
        .slice(0, 2)
        .map((word) => word.charAt(0))
        .join('')
        .toUpperCase(),
);

const clearLocalLogoUrl = () => {
    if (localLogoUrl.value) {
        URL.revokeObjectURL(localLogoUrl.value);
        localLogoUrl.value = null;
    }
};

const selectLogo = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;

    clearLocalLogoUrl();
    form.logo = file;
    form.remove_logo = false;

    if (file) {
        localLogoUrl.value = URL.createObjectURL(file);
    }
};

const removeLogo = () => {
    clearLocalLogoUrl();
    form.logo = null;
    form.remove_logo = true;

    if (logoInput.value) {
        logoInput.value.value = '';
    }
};

const submit = () => {
    form.post(`/venues/${props.venue.slug}/settings`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            clearLocalLogoUrl();
            form.logo = null;
            form.remove_logo = false;
        },
    });
};

onUnmounted(clearLocalLogoUrl);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Podešavanja lokala',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <Head :title="`Podešavanja lokala - ${venue.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
            <p class="text-sm font-medium text-primary">
                Brending i javni prikaz
            </p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight">
                Podešavanja lokala
            </h1>
            <p class="mt-2 max-w-3xl text-sm text-muted-foreground">
                Ovi podaci se prikazuju publici na TV/live stranama svakog
                turnira ovog lokala.
            </p>
        </div>

        <form
            class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(360px,0.8fr)]"
            @submit.prevent="submit"
        >
            <div class="space-y-6">
                <section
                    class="rounded-2xl border border-sidebar-border/70 bg-card p-5 shadow-sm dark:border-sidebar-border"
                >
                    <div class="flex items-center gap-3">
                        <Building2 class="size-5 text-primary" />
                        <div>
                            <h2 class="font-semibold">Podaci o lokalu</h2>
                            <p class="text-sm text-muted-foreground">
                                Osnovni podaci koje gosti vide na javnom ekranu.
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label for="venue-name" class="text-sm font-medium">
                                Naziv lokala
                            </label>
                            <input
                                id="venue-name"
                                v-model="form.name"
                                type="text"
                                class="mt-2 w-full rounded-xl border border-sidebar-border/70 bg-background px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-ring dark:border-sidebar-border"
                            />
                            <p
                                v-if="form.errors.name"
                                class="mt-2 text-sm text-red-500"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div class="md:col-span-2">
                            <label
                                for="venue-description"
                                class="text-sm font-medium"
                            >
                                Kratak opis
                            </label>
                            <textarea
                                id="venue-description"
                                v-model="form.description"
                                rows="3"
                                class="mt-2 w-full resize-none rounded-xl border border-sidebar-border/70 bg-background px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-ring dark:border-sidebar-border"
                                placeholder="Pub, sportski bar ili klub..."
                            />
                            <p
                                v-if="form.errors.description"
                                class="mt-2 text-sm text-red-500"
                            >
                                {{ form.errors.description }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="venue-address"
                                class="text-sm font-medium"
                            >
                                Adresa
                            </label>
                            <div class="relative mt-2">
                                <MapPin
                                    class="absolute top-3 left-3 size-4 text-muted-foreground"
                                />
                                <input
                                    id="venue-address"
                                    v-model="form.address"
                                    type="text"
                                    class="w-full rounded-xl border border-sidebar-border/70 bg-background py-2.5 pr-3 pl-9 text-sm outline-none focus:ring-2 focus:ring-ring dark:border-sidebar-border"
                                    placeholder="Bulevar oslobođenja 10, Novi Sad"
                                />
                            </div>
                            <p
                                v-if="form.errors.address"
                                class="mt-2 text-sm text-red-500"
                            >
                                {{ form.errors.address }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="venue-phone"
                                class="text-sm font-medium"
                            >
                                Telefon
                            </label>
                            <div class="relative mt-2">
                                <Phone
                                    class="absolute top-3 left-3 size-4 text-muted-foreground"
                                />
                                <input
                                    id="venue-phone"
                                    v-model="form.phone"
                                    type="tel"
                                    class="w-full rounded-xl border border-sidebar-border/70 bg-background py-2.5 pr-3 pl-9 text-sm outline-none focus:ring-2 focus:ring-ring dark:border-sidebar-border"
                                    placeholder="+381 60 123 4567"
                                />
                            </div>
                            <p
                                v-if="form.errors.phone"
                                class="mt-2 text-sm text-red-500"
                            >
                                {{ form.errors.phone }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="venue-instagram"
                                class="text-sm font-medium"
                            >
                                Instagram profil
                            </label>
                            <div class="relative mt-2">
                                <AtSign
                                    class="absolute top-3 left-3 size-4 text-muted-foreground"
                                />
                                <input
                                    id="venue-instagram"
                                    v-model="form.instagram_url"
                                    type="url"
                                    class="w-full rounded-xl border border-sidebar-border/70 bg-background py-2.5 pr-3 pl-9 text-sm outline-none focus:ring-2 focus:ring-ring dark:border-sidebar-border"
                                    placeholder="https://instagram.com/nazivlokala"
                                />
                            </div>
                            <p
                                v-if="form.errors.instagram_url"
                                class="mt-2 text-sm text-red-500"
                            >
                                {{ form.errors.instagram_url }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="venue-website"
                                class="text-sm font-medium"
                            >
                                Web sajt
                            </label>
                            <div class="relative mt-2">
                                <Globe2
                                    class="absolute top-3 left-3 size-4 text-muted-foreground"
                                />
                                <input
                                    id="venue-website"
                                    v-model="form.website_url"
                                    type="url"
                                    class="w-full rounded-xl border border-sidebar-border/70 bg-background py-2.5 pr-3 pl-9 text-sm outline-none focus:ring-2 focus:ring-ring dark:border-sidebar-border"
                                    placeholder="https://nazivlokala.rs"
                                />
                            </div>
                            <p
                                v-if="form.errors.website_url"
                                class="mt-2 text-sm text-red-500"
                            >
                                {{ form.errors.website_url }}
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-2xl border border-sidebar-border/70 bg-card p-5 shadow-sm dark:border-sidebar-border"
                >
                    <h2 class="font-semibold">Logo lokala</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Najbolje izgleda kvadratni PNG ili WebP sa prozirnom
                        pozadinom, do 5 MB.
                    </p>

                    <div class="mt-5 flex flex-wrap items-center gap-4">
                        <div
                            class="flex size-24 items-center justify-center overflow-hidden rounded-2xl border border-sidebar-border/70 bg-muted dark:border-sidebar-border"
                        >
                            <img
                                v-if="logoPreviewUrl"
                                :src="logoPreviewUrl"
                                alt="Pregled logoa"
                                class="size-full object-contain p-2"
                            />
                            <span
                                v-else
                                class="text-2xl font-black text-muted-foreground"
                            >
                                {{ venueInitials || 'L' }}
                            </span>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <label
                                for="venue-logo"
                                class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                            >
                                <Camera class="size-4" />
                                Izaberi logo
                            </label>
                            <input
                                id="venue-logo"
                                ref="logoInput"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="sr-only"
                                @change="selectLogo"
                            />

                            <button
                                v-if="logoPreviewUrl"
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl border border-red-500/25 px-4 py-2.5 text-sm font-medium text-red-600 transition hover:bg-red-500/10 dark:text-red-400"
                                @click="removeLogo"
                            >
                                <Trash2 class="size-4" />
                                Ukloni
                            </button>
                        </div>
                    </div>

                    <p
                        v-if="form.errors.logo"
                        class="mt-3 text-sm text-red-500"
                    >
                        {{ form.errors.logo }}
                    </p>
                </section>

                <section
                    class="rounded-2xl border border-sidebar-border/70 bg-card p-5 shadow-sm dark:border-sidebar-border"
                >
                    <h2 class="font-semibold">Tema javnog prikaza</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Tema se primenjuje na sve četiri live strane ovog
                        lokala.
                    </p>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <button
                            type="button"
                            class="flex items-center gap-3 rounded-2xl border p-4 text-left transition"
                            :class="
                                form.public_theme === 'dark'
                                    ? 'border-primary bg-primary/10 ring-2 ring-primary/20'
                                    : 'border-sidebar-border/70 hover:bg-muted dark:border-sidebar-border'
                            "
                            @click="form.public_theme = 'dark'"
                        >
                            <span
                                class="flex size-11 items-center justify-center rounded-xl bg-zinc-950 text-white"
                            >
                                <Moon class="size-5" />
                            </span>
                            <span>
                                <strong class="block text-sm">Tamna</strong>
                                <span class="text-xs text-muted-foreground">
                                    Najbolja za TV i večernje turnire
                                </span>
                            </span>
                        </button>

                        <button
                            type="button"
                            class="flex items-center gap-3 rounded-2xl border p-4 text-left transition"
                            :class="
                                form.public_theme === 'light'
                                    ? 'border-primary bg-primary/10 ring-2 ring-primary/20'
                                    : 'border-sidebar-border/70 hover:bg-muted dark:border-sidebar-border'
                            "
                            @click="form.public_theme = 'light'"
                        >
                            <span
                                class="flex size-11 items-center justify-center rounded-xl border bg-white text-zinc-900"
                            >
                                <Sun class="size-5" />
                            </span>
                            <span>
                                <strong class="block text-sm">Svetla</strong>
                                <span class="text-xs text-muted-foreground">
                                    Za svetle prostore i dnevne događaje
                                </span>
                            </span>
                        </button>
                    </div>
                </section>
            </div>

            <aside class="space-y-4 xl:sticky xl:top-6">
                <div>
                    <p class="text-sm font-semibold">Pregled TV/live headera</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Pregled se menja odmah, pre čuvanja.
                    </p>
                </div>

                <div
                    class="overflow-hidden rounded-3xl border shadow-xl"
                    :class="
                        form.public_theme === 'dark'
                            ? 'border-white/10 bg-zinc-950 text-zinc-50'
                            : 'border-zinc-200 bg-zinc-100 text-zinc-900'
                    "
                >
                    <div class="p-5">
                        <div class="flex items-center gap-4">
                            <div
                                class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-orange-500 text-xl font-black text-white"
                            >
                                <img
                                    v-if="logoPreviewUrl"
                                    :src="logoPreviewUrl"
                                    alt=""
                                    class="size-full bg-white object-contain p-1"
                                />
                                <span v-else>{{ venueInitials || 'L' }}</span>
                            </div>
                            <div class="min-w-0">
                                <p
                                    class="truncate text-[11px] font-bold tracking-[0.18em] uppercase opacity-60"
                                >
                                    {{ form.name || 'Naziv lokala' }}
                                </p>
                                <p class="mt-1 truncate text-2xl font-black">
                                    Naziv turnira
                                </p>
                                <p class="mt-1 text-xs opacity-60">
                                    Grupna faza · 301 · 1 na 1
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="form.address || form.instagram_url"
                            class="mt-4 flex flex-wrap gap-2 text-[11px] opacity-65"
                        >
                            <span v-if="form.address">
                                {{ form.address }}
                            </span>
                            <span v-if="form.instagram_url">
                                {{ form.instagram_url }}
                            </span>
                        </div>
                    </div>

                    <div
                        class="grid grid-cols-4 gap-1 border-t p-1.5"
                        :class="
                            form.public_theme === 'dark'
                                ? 'border-white/10 bg-white/[0.03]'
                                : 'border-zinc-200 bg-zinc-200/50'
                        "
                    >
                        <span
                            v-for="tab in [
                                'Uživo',
                                'Grupe',
                                'Raspored',
                                'Nokaut',
                            ]"
                            :key="tab"
                            class="rounded-xl px-1 py-2 text-center text-[10px] font-bold"
                            :class="
                                tab === 'Uživo'
                                    ? 'bg-orange-500 text-white'
                                    : 'opacity-60'
                            "
                        >
                            {{ tab }}
                        </span>
                    </div>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-primary-foreground shadow-sm transition hover:opacity-90 disabled:opacity-50"
                >
                    <Save class="size-4" />
                    {{ form.processing ? 'Čuvanje...' : 'Sačuvaj podešavanja' }}
                </button>
            </aside>
        </form>
    </div>
</template>
