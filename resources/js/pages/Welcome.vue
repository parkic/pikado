<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Radio, Trophy, Users } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { dashboard, login } from '@/routes';
</script>

<template>
    <Head title="Turniri uživo" />

    <div class="min-h-screen bg-[#0f1110] text-white">
        <div
            class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_20%_10%,rgba(249,115,22,0.18),transparent_34%),radial-gradient(circle_at_85%_70%,rgba(16,185,129,0.12),transparent_32%)]"
        />

        <div
            class="relative mx-auto flex min-h-screen max-w-7xl flex-col px-5 sm:px-8"
        >
            <header class="flex items-center justify-between py-6">
                <Link href="/" class="flex items-center gap-3">
                    <span
                        class="flex size-10 items-center justify-center rounded-xl bg-orange-500 text-white shadow-lg shadow-orange-500/20"
                    >
                        <AppLogoIcon class="size-7" />
                    </span>

                    <span>
                        <span class="block text-lg leading-none font-semibold">
                            Pikado
                        </span>
                        <span class="mt-1 block text-xs text-white/50">
                            Turniri uživo
                        </span>
                    </span>
                </Link>

                <Link
                    :href="$page.props.auth.user ? dashboard() : login()"
                    class="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/5 px-4 py-2.5 text-sm font-medium transition hover:bg-white/10"
                >
                    {{
                        $page.props.auth.user
                            ? 'Otvori aplikaciju'
                            : 'Prijavi se'
                    }}
                    <ArrowRight class="size-4" />
                </Link>
            </header>

            <main class="flex flex-1 items-center py-16 lg:py-24">
                <div
                    class="grid w-full items-center gap-14 lg:grid-cols-[1.1fr_0.9fr]"
                >
                    <section>
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1.5 text-xs font-medium text-emerald-300"
                        >
                            <Radio class="size-3.5" />
                            Rezultati se osvežavaju uživo
                        </div>

                        <h1
                            class="mt-7 max-w-3xl text-5xl font-semibold tracking-[-0.04em] sm:text-6xl lg:text-7xl"
                        >
                            Turnir vodiš.
                            <span class="text-orange-400">Publika prati.</span>
                        </h1>

                        <p
                            class="mt-6 max-w-2xl text-lg leading-8 text-white/60"
                        >
                            Grupe, raspored, rezultati i nokaut na jednom mestu.
                            Organizator unosi rezultat, a TV i telefoni ga vide
                            odmah.
                        </p>

                        <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                            <Link
                                :href="
                                    $page.props.auth.user
                                        ? dashboard()
                                        : login()
                                "
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-orange-500 px-5 py-3.5 font-semibold text-white shadow-xl shadow-orange-500/20 transition hover:bg-orange-400"
                            >
                                {{
                                    $page.props.auth.user
                                        ? 'Nastavi turnir'
                                        : 'Uđi u aplikaciju'
                                }}
                                <ArrowRight class="size-4" />
                            </Link>

                            <span
                                class="inline-flex items-center justify-center rounded-xl border border-white/10 px-5 py-3.5 text-sm text-white/55"
                            >
                                Naloge otvara administrator lokala
                            </span>
                        </div>
                    </section>

                    <section
                        class="relative overflow-hidden rounded-3xl border border-white/10 bg-white/[0.045] p-5 shadow-2xl shadow-black/30 sm:p-7"
                    >
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p
                                    class="text-xs font-medium tracking-[0.2em] text-orange-400 uppercase"
                                >
                                    Primer uživo
                                </p>
                                <h2 class="mt-2 text-2xl font-semibold">
                                    301 Single Out
                                </h2>
                            </div>

                            <span
                                class="rounded-full bg-emerald-400/10 px-3 py-1.5 text-xs font-medium text-emerald-300"
                            >
                                Grupna faza
                            </span>
                        </div>

                        <div class="mt-7 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl bg-black/20 p-4">
                                <div
                                    class="flex items-center gap-2 text-sm text-white/50"
                                >
                                    <Users class="size-4" />
                                    Učesnici
                                </div>
                                <p class="mt-3 text-3xl font-semibold">
                                    38 / 40
                                </p>
                            </div>

                            <div class="rounded-2xl bg-black/20 p-4">
                                <div
                                    class="flex items-center gap-2 text-sm text-white/50"
                                >
                                    <Trophy class="size-4" />
                                    Odigrano
                                </div>
                                <p class="mt-3 text-3xl font-semibold">
                                    42 / 76
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 rounded-2xl bg-black/20 p-4">
                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="text-white/50">Sledeći meč</span>
                                <span class="text-orange-300">Levi pikado</span>
                            </div>

                            <div
                                class="mt-4 flex items-center justify-between gap-4"
                            >
                                <span class="font-medium">Vlada</span>
                                <span class="text-xs text-white/35">VS</span>
                                <span class="font-medium">Nikola</span>
                            </div>
                        </div>

                        <div
                            class="mt-5 h-2 overflow-hidden rounded-full bg-white/10"
                        >
                            <div
                                class="h-full w-[55%] rounded-full bg-gradient-to-r from-orange-500 to-amber-300"
                            />
                        </div>
                        <p class="mt-2 text-right text-xs text-white/40">
                            55% grupne faze završeno
                        </p>
                    </section>
                </div>
            </main>

            <footer class="border-t border-white/10 py-6 text-sm text-white/35">
                Pikado · alat za vođenje turnira u lokalu
            </footer>
        </div>
    </div>
</template>
