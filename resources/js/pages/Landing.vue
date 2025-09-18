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
        </nav>
        <a href="#waitlist" class="rounded-xl bg-slate-900 px-4 py-2 text-white text-sm font-semibold hover:bg-slate-800">Join waitlist</a>
      </div>
    </header>

    <!-- Hero -->
    <section class="relative isolate overflow-hidden">
      <div class="mx-auto grid max-w-7xl grid-cols-1 items-center gap-8 px-4 py-12 sm:gap-10 sm:px-6 sm:py-16 md:grid-cols-2 md:py-24">
        <div class="text-center md:text-left">
          <Badge>New • AI Project Focus Coach and Planner</Badge>
          <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl md:text-5xl">Seal your focus.</h1>
          <p class="mt-4 text-base leading-6 text-slate-600 sm:text-lg sm:leading-7">
            Foca turns a single goal into a plan you can ship. Leverage the power of AI to create milestones, size work,
            and re-order priorities using Pareto (80/20), cost/value, and ship your product.
          </p>
          <div class="mt-6 flex flex-col gap-3 sm:mt-8 sm:flex-row sm:items-center">
            <a href="#waitlist" class="rounded-xl bg-cyan-600 px-5 py-3 font-semibold text-white hover:bg-cyan-500 text-center">Get early access</a>
            <a href="#features" class="rounded-xl border px-5 py-3 font-semibold text-slate-700 hover:bg-slate-50 text-center">Explore features</a>
          </div>
          <p class="mt-3 text-xs text-slate-500">It takes ~30 seconds. No spam, ever.</p>
        </div>

        <div class="relative mx-auto flex w-full max-w-sm items-center justify-center rounded-2xl border bg-white p-6 shadow-lg sm:max-w-md sm:p-8 md:p-10">
          <div class="absolute -right-4 -top-4 -z-10 h-16 w-16 rounded-full bg-cyan-100 sm:-right-6 sm:-top-6 sm:h-24 sm:w-24" />
          <div class="absolute -left-6 -bottom-6 -z-10 h-20 w-20 rounded-full bg-teal-100 sm:-left-8 sm:-bottom-8 sm:h-28 sm:w-28" />
          <SealLogo class="h-32 w-32 sm:h-36 sm:w-36 md:h-40 md:w-40" />
        </div>
      </div>
    </section>

    <!-- Social proof -->
    <section class="px-4 sm:px-6">
      <div class="mx-auto max-w-7xl rounded-2xl border bg-white p-4 sm:p-6 md:p-8">
        <p class="text-center text-sm text-slate-600">Built for indie makers and lean teams shipping fast with limited time.</p>
        <div class="mt-4 flex flex-wrap items-center justify-center gap-4 text-xs text-slate-500 sm:gap-6">
          <span>Less planning drag</span>
          <span class="hidden sm:inline">•</span>
          <span>More progress</span>
          <span class="hidden sm:inline">•</span>
          <span>Fewer meetings</span>
        </div>
      </div>
    </section>

    <!-- Features -->
    <section id="features" class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20">
      <div class="mx-auto max-w-2xl text-center">
        <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">What makes Foca different</h2>
        <p class="mt-3 text-sm text-slate-600 sm:text-base">Decision-first project management. Not another static board.</p>
      </div>
      <div class="mt-8 grid grid-cols-1 gap-4 sm:mt-10 sm:gap-6 md:grid-cols-2 lg:grid-cols-3">
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
    <section id="how" class="bg-white/60 py-16 sm:py-20">
      <div class="mx-auto max-w-7xl px-4 sm:px-6">
        <div class="mx-auto max-w-2xl text-center">
          <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">The AI-Human Synergy Cycle</h2>
          <p class="mt-3 text-sm text-slate-600 sm:text-base">From idea → shipped, through continuous AI collaboration.</p>
        </div>

        <!-- Process Flow -->
        <div class="mt-16 relative sm:mt-20">
          <!-- Mobile: Vertical stack -->
          <div class="block lg:hidden">
            <div class="grid grid-cols-1 gap-8">
              <div v-for="(s, i) in steps" :key="i" class="relative">
                <!-- Card -->
                <div
                  :ref="(el) => setCardRef(el as HTMLElement, i)"
                  class="relative rounded-2xl border bg-white p-6 shadow-sm hover:shadow-md transition-shadow duration-200 w-full max-w-sm mx-auto text-center flex flex-col items-center justify-center"
                  :style="cardDimensions.width > 0 ? {
                    width: `${cardDimensions.width}px`,
                    height: `${cardDimensions.height}px`,
                    minHeight: `${cardDimensions.height}px`
                  } : {}"
                >
                  <!-- Step number badge straddling the top border -->
                  <div class="absolute -top-6 left-1/2 transform -translate-x-1/2 z-10">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-cyan-500 to-teal-500 text-white font-bold text-lg shadow-lg border-4 border-white">
                      {{ i + 1 }}
                    </div>
                  </div>

                  <div class="flex flex-col items-center justify-center h-full">
                    <h3 class="text-lg font-semibold text-slate-800 mb-2 mt-2">{{ s.t }}</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">{{ s.d }}</p>

                    <!-- AI interaction indicator for steps 2 & 3 -->
                    <div v-if="i === 1 || i === 2" class="mt-4 flex items-center justify-center">
                      <div class="flex items-center gap-2 px-3 py-1 rounded-full bg-gradient-to-r from-cyan-50 to-teal-50 border border-cyan-200">
                        <div class="w-2 h-2 rounded-full bg-gradient-to-r from-cyan-400 to-teal-400 animate-pulse"></div>
                        <span class="text-xs font-medium text-cyan-700">AI Conversation</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Desktop: Circular arrangement -->
          <div class="hidden lg:block relative mx-auto w-[44rem] h-[44rem] xl:w-[48rem] xl:h-[48rem]">
            <!-- Center point for reference -->
            <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-4 h-4 bg-cyan-500 rounded-full opacity-20"></div>

            <!-- Step cards positioned in a circle -->
            <div v-for="(s, i) in steps" :key="i" class="absolute" :style="getCardPosition(i)">
              <!-- Card -->
              <div
                :ref="(el) => setCardRef(el as HTMLElement, i)"
                class="relative rounded-2xl border bg-white p-6 shadow-sm hover:shadow-md transition-shadow duration-200 w-64 text-center flex flex-col items-center justify-center"
                :style="cardDimensions.width > 0 ? {
                  width: `${cardDimensions.width}px`,
                  height: `${cardDimensions.height}px`,
                  minHeight: `${cardDimensions.height}px`
                } : {}"
              >
                <!-- Step number badge straddling the top border -->
                <div class="absolute -top-6 left-1/2 transform -translate-x-1/2 z-10">
                  <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-cyan-500 to-teal-500 text-white font-bold text-lg shadow-lg border-4 border-white">
                    {{ i + 1 }}
                  </div>
                </div>

                <div class="flex flex-col items-center justify-center h-full">
                  <h3 class="text-lg font-semibold text-slate-800 mb-2 mt-2">{{ s.t }}</h3>
                  <p class="text-sm text-slate-600 leading-relaxed">{{ s.d }}</p>

                  <!-- AI interaction indicator for steps 2 & 3 -->
                  <div v-if="i === 1 || i === 2" class="mt-4 flex items-center justify-center">
                    <div class="flex items-center gap-2 px-3 py-1 rounded-full bg-gradient-to-r from-cyan-50 to-teal-50 border border-cyan-200">
                      <div class="w-2 h-2 rounded-full bg-gradient-to-r from-cyan-400 to-teal-400 animate-pulse"></div>
                      <span class="text-xs font-medium text-cyan-700">AI Conversation</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Bottom CTA -->
        <div class="mt-16 text-center sm:mt-20">
          <p class="text-sm text-slate-600 mb-4 sm:text-base sm:mb-6">Ready to experience seamless AI collaboration?</p>
          <a href="#waitlist" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-cyan-600 to-teal-600 px-4 py-2.5 font-semibold text-white hover:from-cyan-500 hover:to-teal-500 transition-all duration-200 shadow-lg hover:shadow-xl text-sm sm:px-6 sm:py-3 sm:text-base">
            <SealLogo class="w-4 h-4 sm:w-5 sm:h-5" />
            Start Your Journey
          </a>
        </div>
      </div>
    </section>


    <!-- Waitlist (Inertia form) -->
    <section id="waitlist" class="bg-white/60 py-16 sm:py-20">
      <div class="mx-auto max-w-3xl px-4 text-center sm:px-6">
        <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Get early access</h2>
        <p class="mt-3 text-sm text-slate-600 sm:text-base">Be first to try Foca. We'll invite a small group as we roll out features.</p>
        <form @submit.prevent="submit" class="mx-auto mt-6 flex max-w-xl flex-col items-center gap-3 sm:flex-row">
          <input type="email" v-model="form.email" required placeholder="you@company.com"
                 class="w-full flex-1 rounded-xl border px-4 py-3 text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500 text-sm sm:text-base" />
          <button type="submit" :disabled="form.processing" class="w-full rounded-xl bg-slate-900 px-5 py-3 font-semibold text-white hover:bg-slate-800 disabled:opacity-60 text-sm sm:w-auto sm:text-base">
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
      <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 py-6 sm:px-6 sm:py-8 md:flex-row">
        <div class="flex items-center gap-2 text-slate-600 sm:gap-3">
          <SealLogo class="w-5 h-5 sm:w-6 sm:h-6" />
          <span class="font-semibold text-sm sm:text-base">Foca</span>
          <span class="text-slate-400 hidden sm:inline">·</span>
          <span class="text-xs sm:text-sm">AI Project Focus Coach and Planner</span>
        </div>
        <div class="text-xs text-slate-500">© {{ year }} Foca. All rights reserved.</div>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3'
import { computed, defineComponent, h, ref, onMounted, onUnmounted, nextTick } from 'vue'

const form = useForm({ email: '' })
const page = usePage()
const flash = computed(() => (page.props.flash as { success?: string }) || {})
const year = new Date().getFullYear()

// Card dimensions state
const cardDimensions = ref({ width: 0, height: 0 })
const cardsRef = ref<HTMLElement[]>([])

// Function to set card dimensions
const setCardDimensions = () => {
  nextTick(() => {
    if (cardsRef.value.length === 0) return

    let maxWidth = 0
    let maxHeight = 0

    // Find the largest card dimensions
    cardsRef.value.forEach(card => {
      if (card) {
        const rect = card.getBoundingClientRect()
        maxWidth = Math.max(maxWidth, rect.width)
        maxHeight = Math.max(maxHeight, rect.height)
      }
    })

    // Update dimensions if we found valid measurements
    if (maxWidth > 0 && maxHeight > 0) {
      cardDimensions.value = { width: maxWidth, height: maxHeight }
    }
  })
}

// Set up card refs
const setCardRef = (el: HTMLElement | null, index: number) => {
  if (el) {
    cardsRef.value[index] = el
  }
}

onMounted(() => {
  setCardDimensions()
  // Recalculate on window resize
  window.addEventListener('resize', setCardDimensions)
})

// Cleanup
onUnmounted(() => {
  window.removeEventListener('resize', setCardDimensions)
})

const steps = [
  { t: '1 · Idea', d: 'Describe the outcome you want.' },
  { t: '2 · Plan', d: 'Foca drafts milestones & tasks. Refine through AI conversation until it feels right.' },
  { t: '3 · Focus', d: 'Iterate on priorities with AI. Adjust scope, timeline, and approach through natural dialogue.' },
  { t: '4 · Ship', d: 'Execute and return to step 1.' },
]

// Function to calculate card positions in a circle
function getCardPosition(index: number) {
  const totalSteps = steps.length
  const angle = (index * 2 * Math.PI) / totalSteps - Math.PI / 2 // Start from top
  const radius = 280 // Distance from center (increased again for more spacing)

  const x = Math.cos(angle) * radius
  const y = Math.sin(angle) * radius

  return {
    left: `calc(50% + ${x}px)`,
    top: `calc(50% + ${y}px)`,
    transform: 'translate(-50%, -50%)'
  }
}

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
    return () => h('div', { class: 'rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm hover:shadow-md transition-shadow duration-200 sm:rounded-2xl sm:p-6' }, [
      h('div', { class: 'mb-2 flex items-center gap-2 sm:mb-3 sm:gap-3' }, [
        h(SealLogo, { class: 'w-6 h-6 sm:w-8 sm:h-8' }),
        h('h3', { class: 'text-base font-semibold text-slate-800 sm:text-lg' }, props.title),
      ]),
      h('p', { class: 'text-sm text-slate-600 leading-relaxed sm:text-base' }, props.desc)
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
/* Ensure perfect circles for step badges */
.step-badge {
  aspect-ratio: 1 / 1;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  min-width: 4rem;
  min-height: 4rem;
}

.step-badge span {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  line-height: 1;
}
</style>
