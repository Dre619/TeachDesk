{{--
    resources/views/welcome.blade.php  —  TeachDesk Landing Page
    ─────────────────────────────────────────────────────────────
    Stack: Tailwind CSS (CDN), Google Fonts (Playfair Display + Plus Jakarta Sans + JetBrains Mono)
    Pricing section driven by App\Models\SubscriptionPlan

    Actual SubscriptionPlan columns (from model):
      name           string    "Starter" | "Professional" | "School"
      slug           string    "starter" | "professional" | "school"
      price_zmw      decimal   0.00 | 150.00 | 800.00
      billing_cycle  string    "monthly" | "termly" | "yearly" etc.
      features       json/array keyed object — e.g.:
                               {
                                 "attendance": true,
                                 "assessments": true,
                                 "lesson_plans": true,
                                 "report_cards": false,
                                 "behaviour_logs": false,
                                 "pdf_attendance": true,
                                 "max_classes": 3
                               }
      is_active      boolean   scope: ->active()
      sort_order     integer

    Wire up in your controller or route:
      $plans = \App\Models\SubscriptionPlan::active()->get();
      return view('welcome', compact('plans'));

    Falls back to hard-coded demo data when $plans is not passed.
--}}
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>TeachDesk — Classroom Management for Zambian Schools</title>
<meta name="description" content="TeachDesk gives Zambian teachers lesson planning, attendance, assessments, behaviour logs and ECZ-aligned PDF report cards — all in one place."/>

{{-- Tailwind CDN --}}
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      fontFamily: {
        display: ['"Playfair Display"','Georgia','serif'],
        sans:    ['"Plus Jakarta Sans"','sans-serif'],
        mono:    ['"JetBrains Mono"','monospace'],
      },
      colors: {
        ink:   { DEFAULT:'#0e1117', soft:'#161d28', muted:'#1e2736' },
        chalk: { DEFAULT:'#f0ebe0', dim:'#b8b0a0', faint:'#706860' },
        gold:  { DEFAULT:'#c9852c', light:'#e8a84e', pale:'#fdf3e3', dark:'#8f5910' },
        pine:  { DEFAULT:'#1b6e5f', light:'#e4f2ef', dark:'#0e4338' },
      },
      keyframes: {
        'slide-up': { '0%':{ opacity:'0', transform:'translateY(30px)' }, '100%':{ opacity:'1', transform:'translateY(0)' } },
        'float':    { '0%,100%':{ transform:'translateY(0px) rotate(1.5deg)' }, '50%':{ transform:'translateY(-12px) rotate(1.5deg)' } },
        'ticker':   { '0%':{ transform:'translateX(0)' }, '100%':{ transform:'translateX(-50%)' } },
        'underline':{ '0%':{ transform:'scaleX(0)' }, '100%':{ transform:'scaleX(1)' } },
        'ring':     { '0%,100%':{ transform:'scale(1)', opacity:'1' }, '50%':{ transform:'scale(1.7)', opacity:'0.25' } },
      },
      animation: {
        'slide-up': 'slide-up .75s cubic-bezier(.22,1,.36,1) forwards',
        'float':    'float 7s ease-in-out infinite',
        'ticker':   'ticker 32s linear infinite',
        'underline':'underline .85s .6s cubic-bezier(.22,1,.36,1) forwards',
        'ring':     'ring 2s ease-in-out infinite',
      },
    }
  }
}
</script>

{{-- Fonts --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=Plus+Jakarta+Sans:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<style>
  body { font-family:'Plus Jakarta Sans',sans-serif; background:#0e1117; color:#f0ebe0; }

  /* scroll reveal */
  .reveal { opacity:0; transform:translateY(28px); transition:opacity .7s cubic-bezier(.22,1,.36,1),transform .7s cubic-bezier(.22,1,.36,1); }
  .reveal.in { opacity:1; transform:none; }
  .d1{transition-delay:.1s} .d2{transition-delay:.2s} .d3{transition-delay:.3s}
  .d4{transition-delay:.4s} .d5{transition-delay:.5s} .d6{transition-delay:.6s}

  /* hero items — each has inline animation-delay */
  .h-item {
    opacity: 0;
    animation: hero-up .8s cubic-bezier(.22,1,.36,1) both;
  }
  @keyframes hero-up {
    from { opacity:0; transform:translateY(28px); }
    to   { opacity:1; transform:translateY(0); }
  }
  /* hero card */
  .hero-card-in {
    opacity: 0;
    animation: hero-up 1s .5s cubic-bezier(.22,1,.36,1) both;
  }
  /* floating card inner wrapper */
  .card-float {
    animation: card-float 7s ease-in-out infinite;
  }
  @keyframes card-float {
    0%,100% { transform: translateY(0px) rotate(1.5deg); }
    50%      { transform: translateY(-12px) rotate(1.5deg); }
  }

  /* gold underline on hero headline */
  .u-line { position:relative; display:inline-block; }
  .u-line::after {
    content:''; position:absolute; bottom:3px; left:0; right:0; height:3px;
    background:#c9852c; border-radius:2px;
    transform:scaleX(0); transform-origin:left;
    animation:underline .85s .7s cubic-bezier(.22,1,.36,1) forwards;
  }

  /* feature card hover */
  .feat-card { transition:background .25s, transform .25s; }
  .feat-card:hover { background:#1e2736; transform:translateY(-3px); }
  .feat-card:hover .feat-icon { background:rgba(201,133,44,.16); color:#e8a84e; }
  .feat-card:hover .feat-title { color:#e8a84e; }
  .feat-card:hover .feat-ghost { color:rgba(255,255,255,.035); }

  /* bento hover */
  .bento { transition:transform .3s cubic-bezier(.22,1,.36,1),box-shadow .3s; }
  .bento:hover { transform:translateY(-5px); box-shadow:0 24px 56px rgba(0,0,0,.45); }

  /* ticker */
  .ticker-track { display:flex; gap:3rem; white-space:nowrap; animation:ticker 32s linear infinite; }

  /* nav scroll shadow */
  #nav.scrolled { box-shadow:0 1px 32px rgba(0,0,0,.6); }

  /* noise overlay */
  body::after {
    content:''; position:fixed; inset:0; pointer-events:none; z-index:9999;
    background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.035'/%3E%3C/svg%3E");
  }
</style>
</head>

<body class="overflow-x-hidden">

{{-- ════════════════════════════
     NAV
════════════════════════════ --}}
<nav id="nav" class="fixed inset-x-0 top-0 z-50 bg-ink/90 backdrop-blur-md border-b border-white/[.07] transition-shadow duration-300">
  <div class="max-w-7xl mx-auto px-6 lg:px-12 flex items-center justify-between h-16">

    <a href="{{ url('/') }}" class="flex items-center gap-2.5 no-underline group">
      <div class="w-8 h-8 rounded-lg bg-gold flex items-center justify-center shrink-0 group-hover:bg-gold-light transition-colors duration-200">
        <span class="font-display font-black text-white text-[13px] leading-none">TD</span>
      </div>
      <span class="font-display font-bold text-chalk text-lg tracking-tight">TeachDesk</span>
    </a>

    <ul class="hidden md:flex items-center gap-8 list-none m-0 p-0">
      <li><a href="#features" class="text-[13px] font-medium text-chalk/55 hover:text-chalk transition-colors no-underline">Features</a></li>
      <li><a href="#modules"  class="text-[13px] font-medium text-chalk/55 hover:text-chalk transition-colors no-underline">Modules</a></li>
      <li><a href="#pricing"  class="text-[13px] font-medium text-chalk/55 hover:text-chalk transition-colors no-underline">Pricing</a></li>
      <li><a href="{{ route('login') }}" class="text-[13px] font-medium text-chalk/55 hover:text-chalk transition-colors no-underline">Sign in</a></li>
      <li>
        <a href="{{ route('register') }}" class="inline-flex items-center gap-1.5 bg-gold hover:bg-gold-light text-white font-semibold text-[13px] px-4 py-2 rounded-lg transition-all hover:-translate-y-px shadow-lg shadow-gold/25 no-underline">
          Get Started Free
          <svg class="w-3.5 h-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 8h14M9 2l6 6-6 6"/></svg>
        </a>
      </li>
    </ul>

    <button class="md:hidden p-2 rounded-lg text-chalk/55 hover:bg-ink-soft transition" onclick="document.getElementById('mob').classList.toggle('hidden')">
      <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
  </div>

  <div id="mob" class="hidden md:hidden bg-ink-soft border-t border-white/[.07] px-6 py-4 space-y-3">
    <a href="#features" class="block text-sm text-chalk/60 py-1 no-underline">Features</a>
    <a href="#modules"  class="block text-sm text-chalk/60 py-1 no-underline">Modules</a>
    <a href="#pricing"  class="block text-sm text-chalk/60 py-1 no-underline">Pricing</a>
    <a href="{{ route('login') }}" class="block text-sm text-chalk/60 py-1 no-underline">Sign in</a>
    <a href="{{ route('register') }}" class="block text-center bg-gold text-white font-semibold text-sm py-2.5 rounded-lg no-underline">Get Started Free</a>
  </div>
</nav>


{{-- ════════════════════════════
     HERO
════════════════════════════ --}}
<section class="min-h-screen pt-16 flex items-center relative overflow-hidden">

  {{-- BG elements --}}
  <div class="absolute inset-0 pointer-events-none select-none">
    <div class="absolute top-0 right-0 w-[55%] h-full bg-ink-soft" style="clip-path:polygon(10% 0,100% 0,100% 100%,0 100%)"></div>
    <div class="absolute top-1/3 left-1/5 w-96 h-96 rounded-full bg-gold/5 blur-3xl"></div>
    <div class="absolute bottom-1/4 right-1/3 w-80 h-80 rounded-full bg-pine/6 blur-3xl"></div>
    <svg class="absolute inset-0 w-full h-full opacity-[.03]" xmlns="http://www.w3.org/2000/svg">
      <defs><pattern id="g" x="0" y="0" width="56" height="56" patternUnits="userSpaceOnUse">
        <path d="M56 0L0 0 0 56" fill="none" stroke="#f0ebe0" stroke-width=".5"/>
      </pattern></defs>
      <rect width="100%" height="100%" fill="url(#g)"/>
    </svg>
  </div>

  <div class="max-w-7xl mx-auto px-6 lg:px-12 w-full grid lg:grid-cols-2 gap-16 items-center py-24 relative z-10">

    {{-- Left — each direct child animates in sequence via h-item nth-child --}}
    <div>

      <span style="animation-delay:.12s" class="h-item inline-flex items-center gap-2 bg-gold/10 border border-gold/30 text-gold-light rounded-full px-3.5 py-1.5 font-mono text-[10px] tracking-[.18em] uppercase mb-7">
        <span class="w-1.5 h-1.5 rounded-full bg-gold animate-ring"></span>
        Built for Zambian Schools
      </span>

      <h1 style="animation-delay:.28s" class="h-item font-display text-[clamp(2.8rem,5.5vw,4.8rem)] font-black leading-[1.05] tracking-tight text-chalk mb-6">
        Every class.<br>
        <em class="text-gold-light not-italic">Every student.</em><br>
        <span class="u-line">Every result.</span>
      </h1>

      <p style="animation-delay:.44s" class="h-item text-[17px] text-chalk/50 font-light leading-relaxed max-w-[460px] mb-10">
        TeachDesk brings lesson plans, attendance, assessments, behaviour logs,
        and ECZ-aligned report cards into one seamless platform — designed around
        how Zambian teachers actually work.
      </p>

      <div style="animation-delay:.60s" class="h-item flex flex-wrap gap-4">
        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 bg-gold hover:bg-gold-light text-white font-semibold px-6 py-3.5 rounded-xl shadow-xl shadow-gold/20 hover:shadow-gold/35 hover:-translate-y-0.5 transition-all no-underline">
          <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"/></svg>
          Start for Free
        </a>
        <a href="#features" class="inline-flex items-center gap-2 text-chalk/55 hover:text-chalk border border-white/[.14] hover:border-white/30 font-medium px-5 py-3.5 rounded-xl transition-all no-underline">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polygon points="10,8 16,12 10,16" fill="currentColor" stroke="none"/></svg>
          See how it works
        </a>
      </div>

      <div style="animation-delay:.74s" class="h-item flex flex-wrap items-center gap-6 mt-10">
        @foreach (['ECZ grading built-in', 'No setup fee', 'Free plan available'] as $t)
        <span class="flex items-center gap-1.5 text-[12px] text-chalk/35">
          <svg class="w-3.5 h-3.5 text-pine" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          {{ $t }}
        </span>
        @endforeach
      </div>

    </div>

    {{-- Right — demo card --}}
    <div class="flex justify-center lg:justify-end">
      <div class="w-full max-w-[420px] hero-card-in">
        <div class="card-float bg-ink-soft rounded-2xl overflow-hidden border border-white/[.08] shadow-2xl">

          <div class="bg-ink px-4 py-3 flex items-center gap-2 border-b border-white/[.06]">
            <span class="w-3 h-3 rounded-full bg-red-400/80"></span>
            <span class="w-3 h-3 rounded-full bg-yellow-400/80"></span>
            <span class="w-3 h-3 rounded-full bg-green-400/80"></span>
            <span class="ml-3 font-mono text-[11px] text-chalk/25">Grade 8A — Term 2 Report</span>
          </div>

          <div class="p-5">
            @php
              $demo = [
                ['Mwila Banda',  'Mathematics', 82, 'A', 'bg-emerald-500/15 text-emerald-400'],
                ['Chanda Phiri', 'English',     74, 'B', 'bg-teal-500/15 text-teal-400'],
                ['Mutale Tembo', 'Science',     63, 'C', 'bg-blue-500/15 text-blue-400'],
                ['Bupe Nkonde',  'Social Std',  55, 'D', 'bg-gold/15 text-gold-light'],
              ];
            @endphp
            <table class="w-full text-xs mb-4">
              <thead>
                <tr>
                  <th class="text-left px-2 py-1.5 font-mono text-[9px] text-chalk/25 uppercase tracking-wider">Student</th>
                  <th class="text-left px-2 py-1.5 font-mono text-[9px] text-chalk/25 uppercase tracking-wider">Subject</th>
                  <th class="px-2 py-1.5 font-mono text-[9px] text-chalk/25 uppercase tracking-wider">Avg</th>
                  <th class="text-center px-2 py-1.5 font-mono text-[9px] text-chalk/25 uppercase tracking-wider">Grade</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-white/[.05]">
                @foreach ($demo as $r)
                <tr>
                  <td class="px-2 py-2 font-medium text-chalk/75">{{ $r[0] }}</td>
                  <td class="px-2 py-2 text-chalk/40">{{ $r[1] }}</td>
                  <td class="px-2 py-2">
                    <div class="w-14 h-1 bg-white/10 rounded-full overflow-hidden">
                      <div class="h-full rounded-full bg-gold" style="width:{{ $r[2] }}%"></div>
                    </div>
                  </td>
                  <td class="px-2 py-2 text-center">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full font-bold text-[10px] {{ $r[4] }}">{{ $r[3] }}</span>
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>

            <div class="grid grid-cols-3 gap-2">
              @foreach ([['94%','Attendance','text-emerald-400'],['32','Students','text-chalk/65'],['B+','Class Avg','text-gold-light']] as [$v,$l,$c])
              <div class="bg-ink rounded-xl p-3 text-center border border-white/[.05]">
                <p class="text-lg font-bold {{ $c }}">{{ $v }}</p>
                <p class="font-mono text-[9px] text-chalk/25 uppercase tracking-wide mt-0.5">{{ $l }}</p>
              </div>
              @endforeach
            </div>
          </div>

        </div>
      </div>
    </div>

  </div>
</section>


{{-- ════════════════════════════
     TICKER
════════════════════════════ --}}
<div class="bg-ink-soft border-y border-white/[.07] overflow-hidden">
  <div class="ticker-track py-3.5">
    @php $tks=[['500+','Teachers using TeachDesk'],['12,000+','Students tracked'],['ECZ Aligned','A–F grading built-in'],['3 Terms','Full academic year'],['PDF Reports','Generated in seconds'],['Multi-teacher','Class collaboration'],['Zambia-built','For Zambian schools']]; @endphp
    @foreach ([1,2] as $_)
      @foreach ($tks as [$v,$l])
        <span class="inline-flex items-center gap-2 shrink-0 text-[13px] text-chalk/30 font-light">
          <span class="text-gold font-semibold">{{ $v }}</span>{{ $l }}
        </span>
        <span class="shrink-0 w-1 h-1 rounded-full bg-chalk/12"></span>
      @endforeach
    @endforeach
  </div>
</div>


{{-- ════════════════════════════
     FEATURES
════════════════════════════ --}}
<section id="features" class="py-28 px-6 lg:px-12 xl:px-24 bg-ink">
  <div class="max-w-7xl mx-auto">

    <div class="grid lg:grid-cols-2 gap-12 items-end mb-16">
      <div class="reveal">
        <p class="font-mono text-[10px] text-gold uppercase tracking-[.2em] mb-3">Why TeachDesk</p>
        <h2 class="font-display text-4xl xl:text-5xl font-bold leading-tight tracking-tight text-chalk">Designed around<br>how teachers work</h2>
      </div>
      <p class="text-lg text-chalk/45 font-light leading-relaxed reveal d1">
        We built TeachDesk by talking to Zambian teachers. Every feature solves a real problem — from
        marking daily registers to generating term-end PDFs for 40 students at once.
      </p>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-px bg-white/[.06] rounded-2xl overflow-hidden border border-white/[.06]">
      @php
        $feats = [
          ['<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>','01','ECZ Grading','A–F grades calculated automatically from every mark entered. Fully aligned with the Examinations Council of Zambia. The scale prints on every report card.'],
          ['<path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>','02','Multi-teacher','Invite subject teachers by email. They see only their subject. The form teacher sees everything. PDF reports pull marks from all teachers — no collation.'],
          ['<path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>','03','PDF Report Cards','A4 PDFs per student with all subjects, attendance, conduct grade, and form & head teacher comments — generated in one click.'],
          ['<path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>','04','Attendance Register','Mark P / A / L from any device. Monthly grid, log view, per-student rate and full-year summary — all updated in real time.'],
          ['<path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>','05','Lesson Planning','Week-by-week planning across all three terms. Duplicate into a new academic year in seconds. Grid and list views.'],
          ['<path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>','06','Behaviour Logs','Record positive and negative incidents with dates and notes. Per-student timeline. Conduct grades feed straight into report cards.'],
        ];
      @endphp
      @foreach ($feats as $i => [$icon,$num,$title,$desc])
      <div class="feat-card bg-ink p-8 relative overflow-hidden reveal d{{ ($i % 3) + 1 }}">
        <span class="feat-ghost absolute top-3 right-5 font-display text-[5.5rem] font-black text-white/[.035] leading-none select-none pointer-events-none">{{ $num }}</span>
        <div class="feat-icon w-11 h-11 rounded-xl bg-gold/10 flex items-center justify-center text-gold mb-5 transition-all duration-300">
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">{!! $icon !!}</svg>
        </div>
        <h3 class="feat-title font-display text-xl font-bold text-chalk mb-3 transition-colors duration-300">{{ $title }}</h3>
        <p class="text-[13.5px] leading-relaxed text-chalk/40 font-light">{{ $desc }}</p>
      </div>
      @endforeach
    </div>

  </div>
</section>


{{-- ════════════════════════════
     HOW IT WORKS
════════════════════════════ --}}
<section class="py-28 px-6 lg:px-12 xl:px-24 bg-ink-soft relative overflow-hidden">
  <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 font-display font-black text-[clamp(6rem,15vw,15rem)] text-white/[.025] pointer-events-none select-none tracking-tight whitespace-nowrap">PROCESS</span>
  <div class="max-w-7xl mx-auto relative z-10">
    <p class="font-mono text-[10px] text-gold uppercase tracking-[.2em] mb-3 reveal">The Process</p>
    <h2 class="font-display text-4xl xl:text-5xl font-bold text-chalk leading-tight tracking-tight mb-16 reveal d1">Up and running<br>in four steps</h2>
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-px bg-white/[.05] rounded-2xl overflow-hidden border border-white/[.06]">
      @php
        $steps = [
          ['01','Create your class','Set up your classroom, subject, and academic year. Import students from a spreadsheet or add them manually.'],
          ['02','Invite your team','Send email invites to subject teachers. They accept and immediately enter marks for their subject — no spreadsheet chasing.'],
          ['03','Track all term','Log attendance daily, enter assessment marks, plan lessons, and record behaviour as the term unfolds.'],
          ['04','Generate reports','Add comments and conduct grades, then produce PDF report cards for every student in one click.'],
        ];
      @endphp
      @foreach ($steps as $i => [$num,$title,$desc])
      <div class="bg-ink/60 hover:bg-gold/5 transition-colors duration-300 p-9 reveal d{{ $i+1 }}">
        <div class="font-display text-5xl font-black text-gold/30 leading-none mb-7">{{ $num }}</div>
        <h3 class="font-display text-xl font-bold text-chalk mb-3">{{ $title }}</h3>
        <p class="text-[13.5px] leading-relaxed text-chalk/38 font-light">{{ $desc }}</p>
      </div>
      @endforeach
    </div>
  </div>
</section>


{{-- ════════════════════════════
     MODULES BENTO
════════════════════════════ --}}
<section id="modules" class="py-28 px-6 lg:px-12 xl:px-24 bg-ink">
  <div class="max-w-7xl mx-auto">
    <p class="font-mono text-[10px] text-gold uppercase tracking-[.2em] mb-3 reveal">What's Included</p>
    <h2 class="font-display text-4xl xl:text-5xl font-bold text-chalk leading-tight tracking-tight mb-16 reveal d1">Seven modules.<br>One platform.</h2>

    <div class="grid sm:grid-cols-2 lg:grid-cols-6 gap-3">

      {{-- Assessments --}}
      <div class="bento bg-ink-soft rounded-2xl p-7 border border-white/[.07] lg:col-span-2 reveal">
        <span class="font-mono text-[10px] text-gold/75 border border-gold/25 bg-gold/8 px-2.5 py-1 rounded-full inline-block mb-4 uppercase tracking-widest">Assessments</span>
        <h3 class="font-display text-xl font-bold text-chalk mb-2">Bulk mark entry</h3>
        <p class="text-[13px] text-chalk/38 font-light leading-relaxed mb-5">Spreadsheet-style entry for tests, exams, assignments, and CA. Live ECZ grade preview as you type.</p>
        <div class="flex gap-1.5">
          @foreach (['A'=>'bg-emerald-500/15 text-emerald-400','B'=>'bg-teal-500/15 text-teal-400','C'=>'bg-blue-500/15 text-blue-400','D'=>'bg-gold/15 text-gold-light','E'=>'bg-orange-500/15 text-orange-400','F'=>'bg-red-500/15 text-red-400'] as $g=>$c)
          <span class="flex-1 text-center py-2 rounded-lg text-sm font-bold {{ $c }}">{{ $g }}</span>
          @endforeach
        </div>
      </div>

      {{-- Attendance --}}
      <div class="bento bg-ink-soft rounded-2xl p-7 border border-white/[.07] lg:col-span-2 reveal d1">
        <span class="font-mono text-[10px] text-pine-light/70 border border-pine/25 bg-pine/10 px-2.5 py-1 rounded-full inline-block mb-4 uppercase tracking-widest">Attendance</span>
        <h3 class="font-display text-xl font-bold text-chalk mb-2">Daily register</h3>
        <p class="text-[13px] text-chalk/38 font-light leading-relaxed mb-5">Mark P / A / L from any device. Monthly grid and yearly summary always up to date.</p>
        <div class="flex flex-wrap gap-1">
          @php $ds=['p','p','p','p','a','p','p','l','p','p','p','p','p','a','p','p','l','p','p','p','p','p','p','p','a']; @endphp
          @foreach ($ds as $d)
          <div @class(['w-3 h-3 rounded-sm', 'bg-emerald-500/70'=>$d==='p', 'bg-red-500/50'=>$d==='a', 'bg-gold/60'=>$d==='l'])></div>
          @endforeach
        </div>
      </div>

      {{-- Lesson Plans --}}
      <div class="bento bg-ink-muted rounded-2xl p-7 border border-white/[.07] lg:col-span-2 reveal d2">
        <span class="font-mono text-[10px] text-chalk/45 border border-white/10 bg-white/5 px-2.5 py-1 rounded-full inline-block mb-4 uppercase tracking-widest">Lesson Plans</span>
        <h3 class="font-display text-xl font-bold text-chalk mb-2">Plan by week</h3>
        <p class="text-[13px] text-chalk/38 font-light leading-relaxed mb-5">Term-by-term planning. Duplicate across years in seconds.</p>
        <div class="space-y-2.5">
          @foreach ([['Wk 1',88],['Wk 2',70],['Wk 3',54]] as [$wk,$p])
          <div class="flex items-center gap-3">
            <span class="font-mono text-[10px] text-chalk/28 w-8 shrink-0">{{ $wk }}</span>
            <div class="flex-1 h-1.5 bg-white/10 rounded-full overflow-hidden">
              <div class="h-full bg-gold/55 rounded-full" style="width:{{ $p }}%"></div>
            </div>
          </div>
          @endforeach
        </div>
      </div>

      {{-- Multi-teacher --}}
      <div class="bento rounded-2xl p-7 border border-gold/18 lg:col-span-3 reveal" style="background:rgba(201,133,44,.06)">
        <span class="font-mono text-[10px] text-gold uppercase tracking-widest border border-gold/25 bg-gold/10 px-2.5 py-1 rounded-full inline-block mb-4">Collaboration</span>
        <h3 class="font-display text-2xl font-bold text-chalk mb-3">Multi-teacher classes</h3>
        <p class="text-[13.5px] text-chalk/50 font-light leading-relaxed">
          Invite subject teachers by email. They see only their subject. The form teacher gets the full
          picture. Report cards pull marks from every teacher — zero manual collation needed.
        </p>
        <div class="flex items-center gap-3 mt-6">
          @foreach (['MT','CN','BM','RP'] as $init)
          <div class="w-9 h-9 rounded-full bg-gold/20 border-2 border-gold/30 flex items-center justify-center text-[11px] font-bold text-gold-light -ml-2 first:ml-0">{{ $init }}</div>
          @endforeach
          <span class="text-[12px] text-chalk/30 ml-1">4 subject teachers</span>
        </div>
      </div>

      {{-- Report Cards --}}
      <div class="bento bg-pine rounded-2xl p-7 border border-pine-dark lg:col-span-3 reveal d1">
        <span class="font-mono text-[10px] text-pine-light/70 border border-white/18 bg-white/10 px-2.5 py-1 rounded-full inline-block mb-4 uppercase tracking-widest">Report Cards</span>
        <h3 class="font-display text-2xl font-bold text-white mb-3">PDF reports in one click</h3>
        <p class="text-[13.5px] text-white/60 font-light leading-relaxed">
          Beautiful A4 PDFs — all subjects, attendance, conduct grade, form teacher and head teacher
          comments. Bulk-generate every student in seconds.
        </p>
        <div class="flex items-center gap-2.5 mt-6">
          <svg class="w-5 h-5 text-white/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
          <span class="text-[12px] font-medium text-white/50">38 PDFs ready to download</span>
        </div>
      </div>

    </div>
  </div>
</section>


{{-- ════════════════════════════
     TESTIMONIALS
════════════════════════════ --}}
<section class="py-28 px-6 lg:px-12 xl:px-24 bg-ink-soft">
  <div class="max-w-7xl mx-auto">
    <p class="font-mono text-[10px] text-gold uppercase tracking-[.2em] mb-3 reveal">From Teachers</p>
    <h2 class="font-display text-4xl xl:text-5xl font-bold text-chalk leading-tight tracking-tight mb-16 reveal d1">What Zambian teachers say</h2>

    <div class="grid md:grid-cols-3 gap-5">
      @php
        $quotes = [
          ['End of term used to take me three days to collate marks from four subject teachers and type up 38 report cards. Now I do it in a morning. The PDF quality is better than what we were producing manually.','Mr. M. Phiri','Form Teacher, Grade 8 — Lusaka','MP','bg-gold/20 text-gold-light'],
          ["I teach Science across three classes. Being invited as a subject teacher means I enter my marks and I'm done. Nobody sees data they shouldn't see.",'Ms. C. Nkosi','Science Teacher — Ndola','CN','bg-pine/30 text-pine-light'],
          ['The ECZ grading is exactly what I needed. I was converting percentages manually every term. Now I enter a score and the system handles the rest — the scale even prints on the report card.','Mr. B. Mutale','Mathematics Teacher — Kitwe','BM','bg-chalk/10 text-chalk/60'],
        ];
      @endphp
      @foreach ($quotes as $i => [$text,$name,$role,$init,$av])
      <div class="bg-ink/60 rounded-2xl p-8 border border-white/[.07] hover:border-gold/20 hover:bg-ink/80 transition-all duration-300 reveal d{{ $i+1 }}">
        <div class="flex mb-3">
          @for ($s=0;$s<5;$s++)
          <svg class="w-3.5 h-3.5 text-gold" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
          @endfor
        </div>
        <p class="text-[14.5px] leading-relaxed text-chalk/50 font-light italic mb-7">"{{ $text }}"</p>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full {{ $av }} flex items-center justify-center font-bold text-xs shrink-0">{{ $init }}</div>
          <div>
            <p class="text-sm font-semibold text-chalk/75">{{ $name }}</p>
            <p class="text-[11px] text-chalk/30">{{ $role }}</p>
          </div>
        </div>
      </div>
      @endforeach
    </div>
  </div>
</section>


{{-- ════════════════════════════
     PRICING
     Driven by App\Models\SubscriptionPlan
     Real columns: name, slug, price_zmw, billing_cycle,
                   features (keyed JSON object), is_active, sort_order
════════════════════════════ --}}
<section id="pricing" class="py-28 px-6 lg:px-12 xl:px-24 bg-ink">
  <div class="max-w-7xl mx-auto">

    <div class="text-center mb-16">
      <p class="font-mono text-[10px] text-gold uppercase tracking-[.2em] mb-3 reveal">Pricing</p>
      <h2 class="font-display text-4xl xl:text-5xl font-bold text-chalk leading-tight tracking-tight reveal d1">Simple, honest pricing</h2>
      <p class="text-lg text-chalk/40 font-light mt-4 max-w-md mx-auto reveal d2">Pay per term. No annual lock-in. Cancel any time.</p>
    </div>

    @php
    /*
     * ── SubscriptionPlan integration ─────────────────────────────────────
     *
     * Pass from your controller:
     *   $plans = \App\Models\SubscriptionPlan::active()->get();
     *   return view('welcome', compact('plans'));
     *
     * The model's features column is a keyed JSON object:
     *   { "attendance": true, "assessments": true, "max_classes": 3,
     *     "lesson_plans": true, "report_cards": false,
     *     "behaviour_logs": false, "pdf_attendance": true }
     *
     * Human-readable labels + icons for every known feature key:
     * ─────────────────────────────────────────────────────────────────────
     */

    // Human-readable labels for feature keys
    $featureLabels = [
        'attendance'     => 'Daily attendance register',
        'assessments'    => 'Assessments & ECZ grading',
        'lesson_plans'   => 'Lesson planning',
        'report_cards'   => 'PDF report cards',
        'behaviour_logs' => 'Behaviour logs',
        'pdf_attendance' => 'PDF attendance export',
        'max_classes'    => null,  // handled separately below (numeric value)
    ];

    /*
     * Fallback demo data — activates only when $plans is not passed.
     * Mirrors the real SubscriptionPlan model exactly.
     */
    $plans ??= collect([
        (object)[
            'name'          => 'Starter',
            'slug'          => 'starter',
            'price_zmw'     => '0.00',
            'billing_cycle' => 'termly',
            'features'      => [
                'attendance'     => true,
                'assessments'    => true,
                'lesson_plans'   => false,
                'report_cards'   => false,
                'behaviour_logs' => false,
                'pdf_attendance' => false,
                'max_classes'    => 1,
            ],
            'is_active'  => true,
            'sort_order' => 1,
        ],
        (object)[
            'name'          => 'Professional',
            'slug'          => 'professional',
            'price_zmw'     => '150.00',
            'billing_cycle' => 'termly',
            'features'      => [
                'attendance'     => true,
                'assessments'    => true,
                'lesson_plans'   => true,
                'report_cards'   => true,
                'behaviour_logs' => true,
                'pdf_attendance' => true,
                'max_classes'    => 5,
            ],
            'is_active'  => true,
            'sort_order' => 2,
        ],
        (object)[
            'name'          => 'School',
            'slug'          => 'school',
            'price_zmw'     => '800.00',
            'billing_cycle' => 'termly',
            'features'      => [
                'attendance'     => true,
                'assessments'    => true,
                'lesson_plans'   => true,
                'report_cards'   => true,
                'behaviour_logs' => true,
                'pdf_attendance' => true,
                'max_classes'    => null, // unlimited
            ],
            'is_active'  => true,
            'sort_order' => 3,
        ],
    ]);

    // Derive which plan to highlight as "featured" (middle one by sort_order)
    $planList    = $plans->values();
    $middleIndex = (int) floor(($planList->count() - 1) / 2);

    // Billing cycle → readable label
    $cycleLabels = [
        'termly'  => 'per term',
        'monthly' => 'per month',
        'yearly'  => 'per year',
        'once'    => 'one-time',
    ];

    // Taglines per slug (you can move these to a DB column if you prefer)
    $taglines = [
        'starter'      => 'Perfect for a single classroom teacher just getting started.',
        'professional' => 'For teachers managing multiple classes and subjects.',
        'school'       => 'Whole-school visibility for head teachers.',
    ];

    // CTA labels per slug
    $ctaLabels = [
        'starter'      => 'Get Started Free',
        'professional' => 'Start Free Trial',
        'school'       => 'Contact Us',
    ];

    // CTA routes per slug
    $ctaRoutes = [
        'starter'      => 'register',
        'professional' => 'register',
        'school'       => 'contact',
    ];
    @endphp

    <div class="grid md:grid-cols-{{ $planList->count() }} gap-5 items-start">

      @foreach ($planList as $i => $plan)
      @php
        $featured  = ($i === $middleIndex);
        $isFree    = (float) $plan->price_zmw === 0.0;
        $features  = is_array($plan->features) ? $plan->features : (array) $plan->features;
        $cycle     = $cycleLabels[$plan->billing_cycle] ?? $plan->billing_cycle;
        $tagline   = $taglines[$plan->slug]   ?? '';
        $ctaLabel  = $ctaLabels[$plan->slug]  ?? 'Get Started';
        $ctaRoute  = $ctaRoutes[$plan->slug]  ?? 'register';

        // Resolve CTA URL safely
        try { $ctaHref = route($ctaRoute); }
        catch (\Exception $e) { $ctaHref = url('/' . $ctaRoute); }

        // Max classes line
        $maxClasses = $features['max_classes'] ?? null;
        $classesLine = match(true) {
            $maxClasses === null => 'Unlimited classrooms',
            $maxClasses === 1    => '1 classroom',
            default              => "Up to {$maxClasses} classrooms",
        };
      @endphp

      <div @class([
        'rounded-2xl border-2 transition-all duration-300 reveal overflow-hidden',
        'd' . ($i + 1),
        'border-gold bg-gold/8 -translate-y-3 shadow-2xl shadow-gold/12' => $featured,
        'border-white/[.08] bg-ink-soft hover:border-white/[.18]'        => ! $featured,
      ])>

        {{-- Featured top bar --}}
        @if ($featured)
        <div class="bg-gold px-8 py-2.5 flex items-center justify-center gap-2">
          <svg class="w-3.5 h-3.5 text-white" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
          <span class="font-mono text-[10px] font-bold uppercase tracking-widest text-white">Most Popular</span>
        </div>
        @endif

        <div class="p-8">
          {{-- Plan name --}}
          <p @class(['font-mono text-[10px] uppercase tracking-[.18em] mb-3', 'text-gold' => $featured, 'text-chalk/30' => ! $featured])>
            {{ $plan->name }}
          </p>

          {{-- Price --}}
          <div class="flex items-end gap-1 mb-1">
            @if ($isFree)
              <span class="font-display text-5xl font-black text-chalk leading-none">Free</span>
            @else
              <span @class(['font-display text-2xl font-bold leading-none pb-1.5', 'text-gold' => $featured, 'text-chalk/38' => ! $featured])>K</span>
              <span class="font-display text-5xl font-black text-chalk leading-none">{{ number_format((float) $plan->price_zmw) }}</span>
            @endif
          </div>

          {{-- Billing cycle --}}
          <p @class(['text-sm mb-2', 'text-gold/65' => $featured, 'text-chalk/28' => ! $featured])>
            {{ $isFree ? 'Forever free' : $cycle }}
          </p>

          {{-- Tagline --}}
          @if ($tagline)
          <p class="text-[13px] text-chalk/40 font-light leading-relaxed mb-6">{{ $tagline }}</p>
          @endif

          {{-- Divider --}}
          <div @class(['h-px mb-6', 'bg-gold/20' => $featured, 'bg-white/[.07]' => ! $featured])></div>

          {{-- Max classes line --}}
          <div class="flex items-start gap-2.5 mb-3">
            <span @class(['w-5 h-5 rounded-full inline-flex items-center justify-center text-[10px] font-bold shrink-0 mt-px', 'bg-gold/20 text-gold' => $featured, 'bg-white/8 text-chalk/38' => ! $featured])>✓</span>
            <span class="text-[13.5px] text-chalk/55 font-light">{{ $classesLine }}</span>
          </div>

          {{-- Feature rows from keyed JSON --}}
          <ul class="space-y-3 mb-8">
            @foreach ($featureLabels as $key => $label)
              @if ($key === 'max_classes') @continue @endif
              @php $enabled = (bool) ($features[$key] ?? false); @endphp
              <li class="flex items-start gap-2.5">
                @if ($enabled)
                  <span @class(['w-5 h-5 rounded-full inline-flex items-center justify-center text-[10px] font-bold shrink-0 mt-px', 'bg-gold/20 text-gold' => $featured, 'bg-white/8 text-chalk/38' => ! $featured])>✓</span>
                  <span class="text-[13.5px] text-chalk/55 font-light">{{ $label }}</span>
                @else
                  <span class="w-5 h-5 rounded-full inline-flex items-center justify-center text-[10px] shrink-0 mt-px bg-white/4 text-chalk/18">–</span>
                  <span class="text-[13.5px] text-chalk/22 font-light line-through decoration-chalk/15">{{ $label }}</span>
                @endif
              </li>
            @endforeach
          </ul>

          {{-- Formatted price helper (uses model accessor if available) --}}
          @if (! $isFree)
          <p @class(['text-[11px] font-mono mb-4', 'text-gold/50' => $featured, 'text-chalk/20' => ! $featured])>
            {{ method_exists($plan, 'getFormattedPriceAttribute') ? $plan->formatted_price : 'K' . number_format((float) $plan->price_zmw, 2) }} / {{ $cycle }}
          </p>
          @endif

          {{-- CTA --}}
          <a href="{{ $ctaHref }}" @class([
            'block text-center py-3.5 rounded-xl font-semibold text-sm transition-all no-underline',
            'bg-gold hover:bg-gold-light text-white shadow-lg shadow-gold/20 hover:shadow-gold/30 hover:-translate-y-0.5' => $featured,
            'border border-white/12 text-chalk/55 hover:border-white/28 hover:text-chalk hover:bg-white/4' => ! $featured,
          ])>
            {{ $ctaLabel }}
          </a>
        </div>

      </div>
      @endforeach

    </div>

    <p class="text-center font-mono text-[11px] text-chalk/20 mt-10 reveal">
      All prices in Zambian Kwacha (ZMW) · Billed {{ $cycleLabels[$planList->first()->billing_cycle ?? 'termly'] ?? 'per term' }} · No hidden fees
    </p>

  </div>
</section>


{{-- ════════════════════════════
     CTA BANNER
════════════════════════════ --}}
<section class="py-28 px-6 lg:px-12 xl:px-24 bg-ink-soft text-center relative overflow-hidden">
  <div class="absolute -top-48 -left-48 w-[560px] h-[560px] rounded-full bg-gold/4 blur-3xl pointer-events-none"></div>
  <div class="absolute -bottom-48 -right-48 w-[560px] h-[560px] rounded-full bg-pine/5 blur-3xl pointer-events-none"></div>

  <div class="max-w-3xl mx-auto relative z-10">
    <p class="font-mono text-[10px] text-gold uppercase tracking-[.2em] mb-5 reveal">Ready to start?</p>
    <h2 class="font-display text-5xl xl:text-6xl font-black text-chalk leading-tight tracking-tight mb-6 reveal d1">
      Less paperwork.<br><em class="text-gold-light not-italic">More teaching.</em>
    </h2>
    <p class="text-lg text-chalk/38 font-light leading-relaxed max-w-lg mx-auto mb-10 reveal d2">
      Join hundreds of Zambian teachers already saving hours every term with TeachDesk.
    </p>
    <div class="flex flex-wrap items-center justify-center gap-4 reveal d3">
      <a href="{{ route('register') }}" class="inline-flex items-center gap-2 bg-gold hover:bg-gold-light text-white font-bold px-8 py-4 rounded-xl shadow-xl shadow-gold/20 hover:shadow-gold/32 hover:-translate-y-0.5 transition-all no-underline">
        Create your free account
        <svg class="w-4 h-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 8h14M9 2l6 6-6 6"/></svg>
      </a>
      <a href="#" class="inline-flex items-center gap-2 text-chalk/48 hover:text-chalk border border-white/10 hover:border-white/28 font-medium px-6 py-4 rounded-xl transition-all no-underline">
        Schedule a demo
      </a>
    </div>
  </div>
</section>


{{-- ════════════════════════════
     FOOTER
════════════════════════════ --}}
<footer class="bg-[#08100c] pt-16 pb-8 px-6 lg:px-12 xl:px-24">
  <div class="max-w-7xl mx-auto">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-12 pb-12 border-b border-white/[.05] mb-8">
      <div class="col-span-2 lg:col-span-1">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5 no-underline mb-4">
          <div class="w-8 h-8 rounded-lg bg-gold flex items-center justify-center"><span class="font-display font-black text-white text-[13px]">TD</span></div>
          <span class="font-display font-bold text-chalk/65 text-lg">TeachDesk</span>
        </a>
        <p class="text-[13px] text-chalk/22 font-light leading-relaxed max-w-xs mt-1">Classroom management for Zambian teachers. ECZ-aligned, proudly local.</p>
      </div>
      @foreach (['Product'=>['Features','Modules','Pricing','Changelog'],'Support'=>['Documentation','WhatsApp Support','Video Tutorials','Contact'],'Legal'=>['Privacy Policy','Terms of Service','Data Security']] as $col=>$links)
      <div>
        <p class="font-mono text-[9px] text-chalk/18 uppercase tracking-widest mb-4">{{ $col }}</p>
        <ul class="space-y-2.5 list-none p-0 m-0">
          @foreach ($links as $link)
          <li><a href="#" class="text-[13px] text-chalk/30 hover:text-gold/75 transition-colors font-light no-underline">{{ $link }}</a></li>
          @endforeach
        </ul>
      </div>
      @endforeach
    </div>
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
      <p class="font-mono text-[11px] text-chalk/14">© {{ date('Y') }} TeachDesk · Built in Zambia 🇿🇲</p>
      <p class="font-display text-[12px] italic text-chalk/10">Every classroom. Every student. Every result.</p>
    </div>
  </div>
</footer>


{{-- ════════════════════════════
     JS
════════════════════════════ --}}
<script>
  // Scroll reveal
  const io = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('in'); });
  }, { threshold: 0.08 });
  document.querySelectorAll('.reveal').forEach(el => io.observe(el));

  // Nav scroll shadow
  const nav = document.getElementById('nav');
  window.addEventListener('scroll', () => nav.classList.toggle('scrolled', scrollY > 20), { passive: true });

  // Mobile menu close on link click
  document.querySelectorAll('#mob a').forEach(a =>
    a.addEventListener('click', () => document.getElementById('mob').classList.add('hidden'))
  );
</script>

</body>
</html>
