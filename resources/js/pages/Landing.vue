<template>
  <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-slate-50 text-slate-900">
    <!-- Nav -->
    <header class="sticky top-0 z-20 backdrop-blur supports-[backdrop-filter]:bg-white/70">
      <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
        <div class="flex items-center gap-3">
          <SealLogo class="w-10 h-10" />
          <span class="text-xl font-bold tracking-tight">Foca</span>
        </div>
        <nav class="hidden md:flex items-center gap-6 text-sm text-slate-600">
          <a href="#features" class="hover:text-slate-900">Features</a>
          <a href="#how" class="hover:text-slate-900">How it works</a>
          <a href="#pricing" class="hover:text-slate-900">Pricing</a>
        </nav>
        <a href="#waitlist" class="rounded-xl bg-slate-900 px-4 py-2 text-white text-sm font-semibold hover:bg-slate-800">Join waitlist</a>
      </div>
    </header>

    <!-- Hero -->
    <section class="relative isolate overflow-hidden">
      <div class="mx-auto grid max-w-7xl grid-cols-1 items-center gap-10 px-6 py-16 md:grid-cols-2 md:py-24">
        <div>
          <Badge>New • AI Project Focus Coach</Badge>
          <h1 class="mt-4 text-4xl font-black tracking-tight sm:text-5xl">Seal your focus.</h1>
          <p class="mt-4 text-lg leading-7 text-slate-600">
            Foca turns a single goal into a plan you can ship. Generate milestones, size work, and re-order priorities
            using Pareto (80/20), cost/value, and your real delivery trends.
          </p>
          <div class="mt-8 flex flex-wrap items-center gap-3">
            <a href="#waitlist" class="rounded-xl bg-cyan-600 px-5 py-3 font-semibold text-white hover:bg-cyan-500">Get early access</a>
            <a href="#features" class="rounded-xl border px-5 py-3 font-semibold text-slate-700 hover:bg-slate-50">Explore features</a>
          </div>
          <p class="mt-3 text-xs text-slate-500">It takes ~30 seconds. No spam, ever.</p>
        </div>

        <div class="relative mx-auto flex w-full max-w-md items-center justify-center rounded-3xl border bg-white p-10 shadow-lg">
          <div class="absolute -right-6 -top-6 -z-10 h-24 w-24 rounded-full bg-cyan-100" />
          <div class="absolute -left-8 -bottom-8 -z-10 h-28 w-28 rounded-full bg-teal-100" />
          <SealLogo class="h-40 w-40" />
        </div>
      </div>
    </section>

    <!-- Social proof -->
    <section class="px-6">
      <div class="mx-auto max-w-7xl rounded-2xl border bg-white p-6 md:p-8">
        <p class="text-center text-sm text-slate-600">Built for indie makers and lean teams shipping fast with limited time.</p>
        <div class="mt-4 flex flex-wrap items-center justify-center gap-6 text-xs text-slate-500">
          <span>Less planning drag</span>
          <span>•</span>
          <span>More progress</span>
          <span>•</span>
          <span>Fewer meetings</span>
        </div>
      </div>
    </section>

    <!-- Features -->
    <section id="features" class="mx-auto max-w-7xl px-6 py-20">
      <div class="mx-auto max-w-2xl text-center">
        <h2 class="text-3xl font-bold tracking-tight">What makes Foca different</h2>
        <p class="mt-3 text-slate-600">Decision-first project management. Not another static board.</p>
      </div>
      <div class="mt-10 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
        <FeatureCard
          title="Kickoff Wizard"
          desc="Start from an outcome. AI expands it into milestones and tasks tailored to your context."/>
        <FeatureCard
          title="80/20 Prioritization"
          desc="Highlight the 20% of tasks that unlock 80% of your result. Focus mode shows only what matters."/>
        <FeatureCard
          title="Dynamic Reordering"
          desc="As you complete work, Foca reprioritizes based on delivery trends, deadlines, and blocked items."/>
        <FeatureCard
          title="Cost / Value Sizing"
          desc="Lightweight effort and impact inputs produce smart sequencing and tradeoff suggestions."/>
        <FeatureCard
          title="Auto-Splitting"
          desc="Oversized tasks get split into ship-ready pieces to keep velocity steady."/>
        <FeatureCard
          title="Weekly Insights"
          desc="Receive a concise report on where time went and what to do next to hit your target date."/>
      </div>
    </section>

    <!-- How it works -->
    <section id="how" class="bg-white/60 py-20">
      <div class="mx-auto max-w-7xl px-6">
        <div class="mx-auto max-w-2xl text-center">
          <h2 class="text-3xl font-bold tracking-tight">How it works</h2>
          <p class="mt-3 text-slate-600">From idea → shipped, in four steps.</p>
        </div>
        <div class="mt-10 grid grid-cols-1 gap-6 md:grid-cols-4">
          <div v-for="(s, i) in steps" :key="i" class="rounded-2xl border bg-white p-6 text-center">
            <SealLogo class="mx-auto h-10 w-10" />
            <h3 class="mt-3 font-semibold">{{ s.t }}</h3>
            <p class="mt-1 text-sm text-slate-600">{{ s.d }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Pricing -->
    <section id="pricing" class="mx-auto max-w-7xl px-6 py-20">
      <div class="mx-auto max-w-2xl text-center">
        <h2 class="text-3xl font-bold tracking-tight">Simple pricing</h2>
        <p class="mt-3 text-slate-600">Start free. Upgrade when you're ready.</p>
      </div>
      <div class="mx-auto mt-10 grid max-w-4xl grid-cols-1 gap-6 md:grid-cols-2">
        <div class="rounded-2xl border bg-white p-8">
          <h3 class="text-xl font-semibold">Free</h3>
          <p class="mt-2 text-slate-600">For individuals validating projects</p>
          <p class="mt-6 text-4xl font-extrabold">$0</p>
          <ul class="mt-6 space-y-2 text-sm text-slate-700">
            <li>1 project, up to 50 tasks</li>
            <li>Kickoff wizard</li>
            <li>80/20 prioritization</li>
          </ul>
          <a href="#waitlist" class="mt-8 inline-block rounded-xl border px-4 py-2 font-semibold hover:bg-slate-50">Get started</a>
        </div>
        <div class="rounded-2xl border-2 border-cyan-500 bg-white p-8 shadow-md">
          <div class="flex items-center gap-2">
            <h3 class="text-xl font-semibold">Pro</h3>
            <Badge>Coming soon</Badge>
          </div>
          <p class="mt-2 text-slate-600">For makers & teams who ship weekly</p>
          <p class="mt-6 text-4xl font-extrabold">$12<span class="text-base font-medium text-slate-500">/mo</span></p>
          <ul class="mt-6 space-y-2 text-sm text-slate-700">
            <li>Unlimited projects & tasks</li>
            <li>Cost/value sizing</li>
            <li>Dynamic reordering</li>
            <li>Weekly insights reports</li>
            <li>Basic integrations (GitHub, Slack)</li>
          </ul>
          <a href="#waitlist" class="mt-8 inline-block rounded-xl bg-cyan-600 px-4 py-2 font-semibold text-white hover:bg-cyan-500">Join waitlist</a>
        </div>
      </div>
    </section>

    <!-- Waitlist (Inertia form) -->
    <section id="waitlist" class="bg-white/60 py-20">
      <div class="mx-auto max-w-3xl px-6 text-center">
        <h2 class="text-3xl font-bold tracking-tight">Get early access</h2>
        <p class="mt-3 text-slate-600">Be first to try Foca. We'll invite a small group as we roll out features.</p>
        <form @submit.prevent="submit" class="mx-auto mt-6 flex max-w-xl flex-col items-center gap-3 sm:flex-row">
          <input type="email" v-model="form.email" required placeholder="you@company.com"
                 class="w-full flex-1 rounded-xl border px-4 py-3 text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500" />
          <button type="submit" :disabled="form.processing" class="w-full rounded-xl bg-slate-900 px-5 py-3 font-semibold text-white hover:bg-slate-800 disabled:opacity-60 sm:w-auto">
            <span v-if="!form.processing">Join waitlist</span>
            <span v-else>Sending…</span>
          </button>
        </form>
        <p v-if="form.errors.email" class="mt-2 text-sm text-rose-600">{{ form.errors.email }}</p>
        <p v-if="flash.success" class="mt-2 text-sm text-emerald-600">{{ flash.success }}</p>
        <p class="mt-2 text-xs text-slate-500">By subscribing you agree to our updates. Unsubscribe anytime.</p>
      </div>
    </section>

    <!-- Footer -->
    <footer class="border-t bg-white/70">
      <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-6 py-8 md:flex-row">
        <div class="flex items-center gap-3 text-slate-600">
          <SealLogo class="w-6 h-6" />
          <span class="font-semibold">Foca</span>
          <span class="text-slate-400">·</span>
          <span class="text-sm">AI Project Focus Coach</span>
        </div>
        <div class="text-xs text-slate-500">© {{ year }} Foca. All rights reserved.</div>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { useForm, usePage, Head } from '@inertiajs/vue3'
import { computed, reactive, defineComponent, h } from 'vue'

const form = useForm({ email: '' })
const page = usePage()
const flash = computed(() => (page.props.flash as { success?: string }) || {})
const year = new Date().getFullYear()

const steps = [
  { t: '1 · Idea', d: 'Describe the outcome you want.' },
  { t: '2 · Plan', d: 'Foca drafts milestones & tasks.' },
  { t: '3 · Focus', d: 'Prioritize with 80/20 + cost/value.' },
  { t: '4 · Ship', d: 'Execute with dynamic reordering.' },
]

function submit() {
  form.post('/waitlist', {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('email')
    },
  })
}

// Local components
const SealLogo = defineComponent({
  name: 'SealLogo',
  props: { class: { type: String, default: '' } },
  setup(props) {
    return () => h('img', {
      src: '/storage/foca-icon.png',
      alt: 'Foca seal mascot balancing a checkmark',
      class: props.class,
    })
  }
})

const Badge = defineComponent({
  name: 'Badge',
  setup(_, { slots }) {
    return () => h('span', { class: 'inline-flex items-center rounded-full bg-cyan-50 px-3 py-1 text-xs font-medium text-cyan-700 ring-1 ring-inset ring-cyan-200' }, slots.default?.())
  }
})

const FeatureCard = defineComponent({
  name: 'FeatureCard',
  props: { title: String, desc: String },
  setup(props) {
    return () => h('div', { class: 'rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm hover:shadow-md' }, [
      h('div', { class: 'mb-3 flex items-center gap-3' }, [
        h(SealLogo, { class: 'w-8 h-8' }),
        h('h3', { class: 'text-lg font-semibold text-slate-800' }, props.title),
      ]),
      h('p', { class: 'text-slate-600 leading-relaxed' }, props.desc)
    ])
  }
})
</script>

<script lang="ts">
export default {
  layout: undefined,
}
</script>

<style scoped>
/* Optional: any page-scoped styles */
</style>
