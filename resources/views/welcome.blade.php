{{--
    resources/views/welcome.blade.php  —  TeachDesk Landing Page
    ─────────────────────────────────────────────────────────────
    Stack: Tailwind CSS (CDN), Google Fonts (Plus Jakarta Sans + JetBrains Mono)
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

<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Plus Jakarta Sans"','sans-serif'],
        mono: ['"JetBrains Mono"','monospace'],
      },
      colors: {
        navy: {
          DEFAULT: '#0f2d52',
          light:   '#1a4278',
          dark:    '#091e36',
          soft:    '#163660',
          subtle:  '#e8f0f9',
        },
        gold: {
          DEFAULT: '#f59e0b',
          light:   '#fbbf24',
          pale:    '#fffbeb',
          dark:    '#d97706',
        },
        sky: {
          DEFAULT: '#0284c7',
          light:   '#e0f2fe',
        },
        mist: {
          DEFAULT: '#f1f5f9',
          border:  '#e2e8f0',
          text:    '#64748b',
          soft:    '#94a3b8',
        },
      },
      keyframes: {
        'hero-up': { '0%':{ opacity:'0', transform:'translateY(24px)' }, '100%':{ opacity:'1', transform:'translateY(0)' } },
        'float':   { '0%,100%':{ transform:'translateY(0px)' }, '50%':{ transform:'translateY(-10px)' } },
        'ticker':  { '0%':{ transform:'translateX(0)' }, '100%':{ transform:'translateX(-50%)' } },
        'ring':    { '0%,100%':{ transform:'scale(1)', opacity:'1' }, '50%':{ transform:'scale(1.7)', opacity:'0.2' } },
        'underline':{ '0%':{ transform:'scaleX(0)' }, '100%':{ transform:'scaleX(1)' } },
      },
      animation: {
        'hero-up':  'hero-up .75s cubic-bezier(.22,1,.36,1) both',
        'float':    'float 6s ease-in-out infinite',
        'ticker':   'ticker 32s linear infinite',
        'ring':     'ring 2s ease-in-out infinite',
        'underline':'underline .85s .7s cubic-bezier(.22,1,.36,1) forwards',
      },
    }
  }
}
</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<style>
  body { font-family:'Plus Jakarta Sans',sans-serif; background:#ffffff; color:#0f2d52; }

  /* scroll reveal */
  .reveal { opacity:0; transform:translateY(20px); transition:opacity .6s cubic-bezier(.22,1,.36,1),transform .6s cubic-bezier(.22,1,.36,1); }
  .reveal.in { opacity:1; transform:none; }
  .d1{transition-delay:.1s} .d2{transition-delay:.2s} .d3{transition-delay:.3s}
  .d4{transition-delay:.4s} .d5{transition-delay:.5s} .d6{transition-delay:.6s}

  /* hero items */
  @keyframes hero-up {
    from { opacity:0; transform:translateY(24px); }
    to   { opacity:1; transform:translateY(0); }
  }
  .h-item { opacity:0; animation:hero-up .75s cubic-bezier(.22,1,.36,1) both; }

  /* hero card */
  .hero-card-in { opacity:0; animation:hero-up .9s .45s cubic-bezier(.22,1,.36,1) both; }

  @keyframes card-float {
    0%,100% { transform:translateY(0px); }
    50%      { transform:translateY(-10px); }
  }
  .card-float { animation:card-float 6s ease-in-out infinite; }

  /* gold underline on hero headline */
  @keyframes underline {
    from { transform:scaleX(0); }
    to   { transform:scaleX(1); }
  }
  .u-line { position:relative; display:inline-block; }
  .u-line::after {
    content:''; position:absolute; bottom:2px; left:0; right:0; height:3px;
    background:#f59e0b; border-radius:2px;
    transform:scaleX(0); transform-origin:left;
    animation:underline .85s .7s cubic-bezier(.22,1,.36,1) forwards;
  }

  /* nav */
  #nav { background:rgba(255,255,255,0.96); }
  #nav.scrolled { box-shadow:0 2px 20px rgba(15,45,82,.1); }

  /* feature card hover */
  .feat-card { transition:box-shadow .25s, transform .25s; }
  .feat-card:hover { box-shadow:0 12px 40px rgba(15,45,82,.12); transform:translateY(-3px); }
  .feat-card:hover .feat-icon { background:#0f2d52; color:#ffffff; }
  .feat-card:hover .feat-title { color:#0f2d52; }

  /* bento hover */
  .bento { transition:transform .3s cubic-bezier(.22,1,.36,1),box-shadow .3s; }
  .bento:hover { transform:translateY(-4px); box-shadow:0 20px 50px rgba(15,45,82,.14); }

  /* ticker */
  .ticker-track { display:flex; gap:3rem; white-space:nowrap; animation:ticker 32s linear infinite; }

  /* scrollbar */
  ::-webkit-scrollbar { width:6px; }
  ::-webkit-scrollbar-track { background:#f1f5f9; }
  ::-webkit-scrollbar-thumb { background:#1a4278; border-radius:3px; }
</style>
</head>

<body class="overflow-x-hidden">

{{-- ════════════════════════════
     NAV
════════════════════════════ --}}
<nav id="nav" class="fixed inset-x-0 top-0 z-50 backdrop-blur-md border-b border-mist-border transition-shadow duration-300">
  <div class="max-w-7xl mx-auto px-6 lg:px-12 flex items-center justify-between h-16">

    <a href="{{ url('/') }}" class="flex items-center gap-2.5 no-underline group">
      <div class="w-8 h-8 rounded-lg bg-navy flex items-center justify-center shrink-0 group-hover:bg-navy-light transition-colors duration-200">
        <span class="font-bold text-white text-[13px] leading-none tracking-tight">TD</span>
      </div>
      <span class="font-bold text-navy text-lg tracking-tight">TeachDesk</span>
    </a>

    <ul class="hidden md:flex items-center gap-8 list-none m-0 p-0">
      <li><a href="#features" class="text-[13px] font-medium text-mist-text hover:text-navy transition-colors no-underline">Features</a></li>
      <li><a href="#modules"  class="text-[13px] font-medium text-mist-text hover:text-navy transition-colors no-underline">Modules</a></li>
      <li><a href="#pricing"  class="text-[13px] font-medium text-mist-text hover:text-navy transition-colors no-underline">Pricing</a></li>
      <li><a href="{{ route('login') }}" class="text-[13px] font-medium text-mist-text hover:text-navy transition-colors no-underline">Sign in</a></li>
      <li>
        <a href="{{ route('register') }}" class="inline-flex items-center gap-1.5 bg-gold hover:bg-gold-light text-white font-semibold text-[13px] px-4 py-2 rounded-lg transition-all hover:-translate-y-px shadow-md shadow-gold/30 no-underline">
          Get Started Free
          <svg class="w-3.5 h-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 8h14M9 2l6 6-6 6"/></svg>
        </a>
      </li>
    </ul>

    <button class="md:hidden p-2 rounded-lg text-mist-text hover:bg-mist transition" onclick="document.getElementById('mob').classList.toggle('hidden')">
      <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
  </div>

  <div id="mob" class="hidden md:hidden bg-white border-t border-mist-border px-6 py-4 space-y-3">
    <a href="#features" class="block text-sm text-mist-text hover:text-navy py-1 no-underline">Features</a>
    <a href="#modules"  class="block text-sm text-mist-text hover:text-navy py-1 no-underline">Modules</a>
    <a href="#pricing"  class="block text-sm text-mist-text hover:text-navy py-1 no-underline">Pricing</a>
    <a href="{{ route('login') }}" class="block text-sm text-mist-text hover:text-navy py-1 no-underline">Sign in</a>
    <a href="{{ route('register') }}" class="block text-center bg-gold text-white font-semibold text-sm py-2.5 rounded-lg no-underline mt-2">Get Started Free</a>
  </div>
</nav>


{{-- ════════════════════════════
     HERO
════════════════════════════ --}}
<section class="min-h-screen pt-16 flex items-center relative overflow-hidden" style="background:linear-gradient(145deg, #091e36 0%, #0f2d52 55%, #163660 100%)">

  {{-- BG decorations --}}
  <div class="absolute inset-0 pointer-events-none select-none">
    {{-- Soft glow orbs --}}
    <div class="absolute top-1/4 right-1/4 w-[480px] h-[480px] rounded-full opacity-20 blur-3xl" style="background:radial-gradient(circle, #0284c7, transparent)"></div>
    <div class="absolute bottom-1/3 left-1/6  w-[360px] h-[360px] rounded-full opacity-15 blur-3xl" style="background:radial-gradient(circle, #f59e0b, transparent)"></div>
    {{-- Grid overlay --}}
    <svg class="absolute inset-0 w-full h-full opacity-[.04]" xmlns="http://www.w3.org/2000/svg">
      <defs><pattern id="g" x="0" y="0" width="48" height="48" patternUnits="userSpaceOnUse">
        <path d="M48 0L0 0 0 48" fill="none" stroke="#ffffff" stroke-width=".5"/>
      </pattern></defs>
      <rect width="100%" height="100%" fill="url(#g)"/>
    </svg>
    {{-- Diagonal accent panel --}}
    <div class="absolute top-0 right-0 w-[45%] h-full opacity-10" style="background:linear-gradient(to bottom left, #0284c7, transparent); clip-path:polygon(25% 0,100% 0,100% 100%)"></div>
  </div>

  <div class="max-w-7xl mx-auto px-6 lg:px-12 w-full grid lg:grid-cols-2 gap-16 items-center py-24 relative z-10">

    {{-- Left --}}
    <div>

      <span style="animation-delay:.1s" class="h-item inline-flex items-center gap-2 bg-white/10 border border-white/20 text-white/75 rounded-full px-3.5 py-1.5 font-mono text-[10px] tracking-[.18em] uppercase mb-7">
        <span class="w-1.5 h-1.5 rounded-full bg-gold animate-ring"></span>
        Built for Zambian Schools
      </span>

      <h1 style="animation-delay:.24s" class="h-item text-[clamp(2.6rem,5vw,4.4rem)] font-extrabold leading-[1.08] tracking-tight text-white mb-6">
        Every class.<br>
        <span class="text-gold">Every student.</span><br>
        <span class="u-line">Every result.</span>
      </h1>

      <p style="animation-delay:.40s" class="h-item text-[17px] text-white/55 font-light leading-relaxed max-w-[460px] mb-10">
        TeachDesk brings lesson plans, attendance, assessments, behaviour logs,
        and ECZ-aligned report cards into one seamless platform — designed around
        how Zambian teachers actually work.
      </p>

      <div style="animation-delay:.54s" class="h-item flex flex-wrap gap-4">
        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 bg-gold hover:bg-gold-light text-white font-bold px-6 py-3.5 rounded-xl shadow-xl shadow-gold/25 hover:shadow-gold/40 hover:-translate-y-0.5 transition-all no-underline">
          <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"/></svg>
          Start for Free
        </a>
        <a href="#features" class="inline-flex items-center gap-2 text-white/65 hover:text-white border border-white/20 hover:border-white/40 font-medium px-5 py-3.5 rounded-xl transition-all no-underline">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polygon points="10,8 16,12 10,16" fill="currentColor" stroke="none"/></svg>
          See how it works
        </a>
      </div>

      <div style="animation-delay:.66s" class="h-item flex flex-wrap items-center gap-6 mt-10">
        @foreach (['ECZ grading built-in', 'No setup fee', 'Free plan available'] as $t)
        <span class="flex items-center gap-1.5 text-[12px] text-white/35">
          <svg class="w-3.5 h-3.5 text-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          {{ $t }}
        </span>
        @endforeach
      </div>

    </div>

    {{-- Right — demo card --}}
    <div class="flex justify-center lg:justify-end">
      <div class="w-full max-w-[420px] hero-card-in">
        <div class="card-float bg-white rounded-2xl overflow-hidden border border-navy/10 shadow-2xl" style="box-shadow:0 32px 80px rgba(9,30,54,.5)">

          <div class="bg-navy px-4 py-3 flex items-center gap-2 border-b border-white/10">
            <span class="w-3 h-3 rounded-full bg-red-400/70"></span>
            <span class="w-3 h-3 rounded-full bg-yellow-400/70"></span>
            <span class="w-3 h-3 rounded-full bg-green-400/70"></span>
            <span class="ml-3 font-mono text-[11px] text-white/40">Grade 8A — Term 2 Report</span>
          </div>

          <div class="p-5">
            @php
              $demo = [
                ['Mwila Banda',  'Mathematics', 82, 'A', 'bg-emerald-100 text-emerald-700'],
                ['Chanda Phiri', 'English',     74, 'B', 'bg-blue-100 text-blue-700'],
                ['Mutale Tembo', 'Science',     63, 'C', 'bg-sky-100 text-sky-700'],
                ['Bupe Nkonde',  'Social Std',  55, 'D', 'bg-amber-100 text-amber-700'],
              ];
            @endphp
            <table class="w-full text-xs mb-4">
              <thead>
                <tr>
                  <th class="text-left px-2 py-1.5 font-mono text-[9px] text-mist-soft uppercase tracking-wider">Student</th>
                  <th class="text-left px-2 py-1.5 font-mono text-[9px] text-mist-soft uppercase tracking-wider">Subject</th>
                  <th class="px-2 py-1.5 font-mono text-[9px] text-mist-soft uppercase tracking-wider">Avg</th>
                  <th class="text-center px-2 py-1.5 font-mono text-[9px] text-mist-soft uppercase tracking-wider">Grade</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-mist">
                @foreach ($demo as $r)
                <tr>
                  <td class="px-2 py-2 font-semibold text-navy text-[12px]">{{ $r[0] }}</td>
                  <td class="px-2 py-2 text-mist-text text-[12px]">{{ $r[1] }}</td>
                  <td class="px-2 py-2">
                    <div class="w-14 h-1.5 bg-mist rounded-full overflow-hidden">
                      <div class="h-full rounded-full bg-navy" style="width:{{ $r[2] }}%"></div>
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
              @foreach ([['94%','Attendance','text-emerald-600'],['32','Students','text-navy'],['B+','Class Avg','text-gold-dark']] as [$v,$l,$c])
              <div class="bg-navy-subtle rounded-xl p-3 text-center">
                <p class="text-lg font-bold {{ $c }}">{{ $v }}</p>
                <p class="font-mono text-[9px] text-mist-soft uppercase tracking-wide mt-0.5">{{ $l }}</p>
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
<div class="bg-navy-light border-y border-white/10 overflow-hidden">
  <div class="ticker-track py-3.5">
    @php $tks=[['500+','Teachers using TeachDesk'],['12,000+','Students tracked'],['ECZ Aligned','A–F grading built-in'],['3 Terms','Full academic year'],['PDF Reports','Generated in seconds'],['Multi-teacher','Class collaboration'],['Zambia-built','For Zambian schools']]; @endphp
    @foreach ([1,2] as $_)
      @foreach ($tks as [$v,$l])
        <span class="inline-flex items-center gap-2 shrink-0 text-[13px] text-white/40 font-light">
          <span class="text-gold font-semibold">{{ $v }}</span>{{ $l }}
        </span>
        <span class="shrink-0 w-1 h-1 rounded-full bg-white/20"></span>
      @endforeach
    @endforeach
  </div>
</div>


{{-- ════════════════════════════
     FEATURES
════════════════════════════ --}}
<section id="features" class="py-28 px-6 lg:px-12 xl:px-24 bg-mist">
  <div class="max-w-7xl mx-auto">

    <div class="grid lg:grid-cols-2 gap-12 items-end mb-16">
      <div class="reveal">
        <p class="font-mono text-[10px] text-navy/45 uppercase tracking-[.2em] mb-3">Why TeachDesk</p>
        <h2 class="text-4xl xl:text-5xl font-extrabold leading-tight tracking-tight text-navy">Designed around<br>how teachers work</h2>
      </div>
      <p class="text-lg text-mist-text font-light leading-relaxed reveal d1">
        We built TeachDesk by talking to Zambian teachers. Every feature solves a real problem — from
        marking daily registers to generating term-end PDFs for 40 students at once.
      </p>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
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
      <div class="feat-card bg-white rounded-2xl p-8 border border-mist-border relative overflow-hidden reveal d{{ ($i % 3) + 1 }}">
        <span class="feat-ghost absolute top-3 right-5 font-extrabold text-[5rem] text-navy/[.04] leading-none select-none pointer-events-none">{{ $num }}</span>
        <div class="feat-icon w-11 h-11 rounded-xl bg-navy-subtle flex items-center justify-center text-navy mb-5 transition-all duration-300">
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">{!! $icon !!}</svg>
        </div>
        <h3 class="feat-title font-bold text-xl text-navy mb-3 transition-colors duration-300">{{ $title }}</h3>
        <p class="text-[13.5px] leading-relaxed text-mist-text font-light">{{ $desc }}</p>
      </div>
      @endforeach
    </div>

  </div>
</section>


{{-- ════════════════════════════
     HOW IT WORKS
════════════════════════════ --}}
<section class="py-28 px-6 lg:px-12 xl:px-24 bg-white relative overflow-hidden">
  {{-- decorative right panel --}}
  <div class="absolute top-0 right-0 h-full w-1/3 bg-navy-subtle/40 pointer-events-none" style="clip-path:polygon(30% 0,100% 0,100% 100%)"></div>
  <div class="max-w-7xl mx-auto relative z-10">
    <p class="font-mono text-[10px] text-navy/45 uppercase tracking-[.2em] mb-3 reveal">The Process</p>
    <h2 class="text-4xl xl:text-5xl font-extrabold text-navy leading-tight tracking-tight mb-16 reveal d1">Up and running<br>in four steps</h2>
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
      @php
        $steps = [
          ['01','Create your class','Set up your classroom, subject, and academic year. Import students from a spreadsheet or add them manually.'],
          ['02','Invite your team','Send email invites to subject teachers. They accept and immediately enter marks for their subject — no spreadsheet chasing.'],
          ['03','Track all term','Log attendance daily, enter assessment marks, plan lessons, and record behaviour as the term unfolds.'],
          ['04','Generate reports','Add comments and conduct grades, then produce PDF report cards for every student in one click.'],
        ];
      @endphp
      @foreach ($steps as $i => [$num,$title,$desc])
      <div class="bg-white rounded-2xl p-8 border border-mist-border hover:border-navy/20 hover:shadow-xl transition-all duration-300 reveal d{{ $i+1 }}" style="transition:border-color .25s, box-shadow .25s">
        <div class="w-12 h-12 rounded-xl bg-navy flex items-center justify-center mb-6 font-bold text-white text-sm font-mono">{{ $num }}</div>
        <h3 class="font-bold text-xl text-navy mb-3">{{ $title }}</h3>
        <p class="text-[13.5px] leading-relaxed text-mist-text font-light">{{ $desc }}</p>
      </div>
      @endforeach
    </div>
  </div>
</section>


{{-- ════════════════════════════
     MODULES BENTO
════════════════════════════ --}}
<section id="modules" class="py-28 px-6 lg:px-12 xl:px-24 bg-mist">
  <div class="max-w-7xl mx-auto">
    <p class="font-mono text-[10px] text-navy/45 uppercase tracking-[.2em] mb-3 reveal">What's Included</p>
    <h2 class="text-4xl xl:text-5xl font-extrabold text-navy leading-tight tracking-tight mb-16 reveal d1">Seven modules.<br>One platform.</h2>

    <div class="grid sm:grid-cols-2 lg:grid-cols-6 gap-4">

      {{-- Assessments --}}
      <div class="bento bg-white rounded-2xl p-7 border border-mist-border lg:col-span-2 reveal">
        <span class="font-mono text-[10px] text-navy border border-navy/20 bg-navy-subtle px-2.5 py-1 rounded-full inline-block mb-4 uppercase tracking-widest">Assessments</span>
        <h3 class="font-bold text-xl text-navy mb-2">Bulk mark entry</h3>
        <p class="text-[13px] text-mist-text font-light leading-relaxed mb-5">Spreadsheet-style entry for tests, exams, assignments, and CA. Live ECZ grade preview as you type.</p>
        <div class="flex gap-1.5">
          @foreach (['A'=>'bg-emerald-100 text-emerald-700','B'=>'bg-blue-100 text-blue-700','C'=>'bg-sky-100 text-sky-700','D'=>'bg-amber-100 text-amber-700','E'=>'bg-orange-100 text-orange-700','F'=>'bg-red-100 text-red-700'] as $g=>$c)
          <span class="flex-1 text-center py-2 rounded-lg text-sm font-bold {{ $c }}">{{ $g }}</span>
          @endforeach
        </div>
      </div>

      {{-- Attendance --}}
      <div class="bento bg-white rounded-2xl p-7 border border-mist-border lg:col-span-2 reveal d1">
        <span class="font-mono text-[10px] text-sky border border-sky/25 bg-sky-light/50 px-2.5 py-1 rounded-full inline-block mb-4 uppercase tracking-widest">Attendance</span>
        <h3 class="font-bold text-xl text-navy mb-2">Daily register</h3>
        <p class="text-[13px] text-mist-text font-light leading-relaxed mb-5">Mark P / A / L from any device. Monthly grid and yearly summary always up to date.</p>
        <div class="flex flex-wrap gap-1">
          @php $ds=['p','p','p','p','a','p','p','l','p','p','p','p','p','a','p','p','l','p','p','p','p','p','p','p','a']; @endphp
          @foreach ($ds as $d)
          <div @class(['w-3 h-3 rounded-sm', 'bg-emerald-500'=>$d==='p', 'bg-red-400'=>$d==='a', 'bg-amber-400'=>$d==='l'])></div>
          @endforeach
        </div>
      </div>

      {{-- Lesson Plans --}}
      <div class="bento bg-white rounded-2xl p-7 border border-mist-border lg:col-span-2 reveal d2">
        <span class="font-mono text-[10px] text-mist-text border border-mist-border bg-mist px-2.5 py-1 rounded-full inline-block mb-4 uppercase tracking-widest">Lesson Plans</span>
        <h3 class="font-bold text-xl text-navy mb-2">Plan by week</h3>
        <p class="text-[13px] text-mist-text font-light leading-relaxed mb-5">Term-by-term planning. Duplicate across years in seconds.</p>
        <div class="space-y-2.5">
          @foreach ([['Wk 1',88],['Wk 2',70],['Wk 3',54]] as [$wk,$p])
          <div class="flex items-center gap-3">
            <span class="font-mono text-[10px] text-mist-soft w-8 shrink-0">{{ $wk }}</span>
            <div class="flex-1 h-1.5 bg-mist rounded-full overflow-hidden">
              <div class="h-full bg-navy rounded-full" style="width:{{ $p }}%"></div>
            </div>
            <span class="font-mono text-[10px] text-mist-soft w-8 text-right">{{ $p }}%</span>
          </div>
          @endforeach
        </div>
      </div>

      {{-- Multi-teacher — navy card --}}
      <div class="bento rounded-2xl p-7 bg-navy border border-navy lg:col-span-3 reveal">
        <span class="font-mono text-[10px] text-white/55 uppercase tracking-widest border border-white/20 bg-white/10 px-2.5 py-1 rounded-full inline-block mb-4">Collaboration</span>
        <h3 class="font-bold text-2xl text-white mb-3">Multi-teacher classes</h3>
        <p class="text-[13.5px] text-white/55 font-light leading-relaxed">
          Invite subject teachers by email. They see only their subject. The form teacher gets the full
          picture. Report cards pull marks from every teacher — zero manual collation needed.
        </p>
        <div class="flex items-center gap-3 mt-6">
          @foreach (['MT','CN','BM','RP'] as $init)
          <div class="w-9 h-9 rounded-full bg-white/15 border-2 border-white/25 flex items-center justify-center text-[11px] font-bold text-white -ml-2 first:ml-0">{{ $init }}</div>
          @endforeach
          <span class="text-[12px] text-white/35 ml-1">4 subject teachers</span>
        </div>
      </div>

      {{-- Report Cards — gold card --}}
      <div class="bento rounded-2xl p-7 lg:col-span-3 reveal d1" style="background:linear-gradient(135deg, #d97706 0%, #f59e0b 100%)">
        <span class="font-mono text-[10px] text-white/75 border border-white/30 bg-white/15 px-2.5 py-1 rounded-full inline-block mb-4 uppercase tracking-widest">Report Cards</span>
        <h3 class="font-bold text-2xl text-white mb-3">PDF reports in one click</h3>
        <p class="text-[13.5px] text-white/65 font-light leading-relaxed">
          Beautiful A4 PDFs — all subjects, attendance, conduct grade, form teacher and head teacher
          comments. Bulk-generate every student in seconds.
        </p>
        <div class="flex items-center gap-2.5 mt-6">
          <svg class="w-5 h-5 text-white/55" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
          <span class="text-[12px] font-medium text-white/55">38 PDFs ready to download</span>
        </div>
      </div>

    </div>
  </div>
</section>


{{-- ════════════════════════════
     TESTIMONIALS
════════════════════════════ --}}
<section class="py-28 px-6 lg:px-12 xl:px-24 bg-white">
  <div class="max-w-7xl mx-auto">
    <p class="font-mono text-[10px] text-navy/45 uppercase tracking-[.2em] mb-3 reveal">From Teachers</p>
    <h2 class="text-4xl xl:text-5xl font-extrabold text-navy leading-tight tracking-tight mb-16 reveal d1">What Zambian teachers say</h2>

    <div class="grid md:grid-cols-3 gap-5">
      @php
        $quotes = [
          ['End of term used to take me three days to collate marks from four subject teachers and type up 38 report cards. Now I do it in a morning. The PDF quality is better than what we were producing manually.','Mr. M. Phiri','Form Teacher, Grade 8 — Lusaka','MP','bg-navy-subtle text-navy'],
          ["I teach Science across three classes. Being invited as a subject teacher means I enter my marks and I'm done. Nobody sees data they shouldn't see.",'Ms. C. Nkosi','Science Teacher — Ndola','CN','bg-sky-light text-sky'],
          ['The ECZ grading is exactly what I needed. I was converting percentages manually every term. Now I enter a score and the system handles the rest — the scale even prints on the report card.','Mr. B. Mutale','Mathematics Teacher — Kitwe','BM','bg-gold-pale text-gold-dark'],
        ];
      @endphp
      @foreach ($quotes as $i => [$text,$name,$role,$init,$av])
      <div class="bg-mist rounded-2xl p-8 border border-mist-border hover:border-navy/20 hover:shadow-lg transition-all duration-300 reveal d{{ $i+1 }}">
        <div class="flex mb-4">
          @for ($s=0;$s<5;$s++)
          <svg class="w-3.5 h-3.5 text-gold" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
          @endfor
        </div>
        <p class="text-[14.5px] leading-relaxed text-mist-text italic mb-7">"{{ $text }}"</p>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full {{ $av }} flex items-center justify-center font-bold text-xs shrink-0">{{ $init }}</div>
          <div>
            <p class="text-sm font-semibold text-navy">{{ $name }}</p>
            <p class="text-[11px] text-mist-soft">{{ $role }}</p>
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
<section id="pricing" class="py-28 px-6 lg:px-12 xl:px-24 bg-mist">
  <div class="max-w-7xl mx-auto">

    <div class="text-center mb-16">
      <p class="font-mono text-[10px] text-navy/45 uppercase tracking-[.2em] mb-3 reveal">Pricing</p>
      <h2 class="text-4xl xl:text-5xl font-extrabold text-navy leading-tight tracking-tight reveal d1">Simple, honest pricing</h2>
      <p class="text-lg text-mist-text font-light mt-4 max-w-md mx-auto reveal d2">Pay per term. No annual lock-in. Cancel any time.</p>
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
     */

    // Human-readable labels for feature keys
    $featureLabels = [
        'attendance'     => 'Daily attendance register',
        'assessments'    => 'Assessments & ECZ grading',
        'lesson_plans'   => 'Lesson planning',
        'report_cards'   => 'PDF report cards',
        'behaviour_logs' => 'Behaviour logs',
        'pdf_attendance' => 'PDF attendance export',
        'max_classes'    => null,
    ];

    // Fallback demo data
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
                'max_classes'    => null,
            ],
            'is_active'  => true,
            'sort_order' => 3,
        ],
    ]);

    $planList    = $plans->values();
    $middleIndex = (int) floor(($planList->count() - 1) / 2);

    $cycleLabels = [
        'termly'  => 'per term',
        'monthly' => 'per month',
        'yearly'  => 'per year',
        'once'    => 'one-time',
    ];

    $taglines = [
        'starter'      => 'Perfect for a single classroom teacher just getting started.',
        'professional' => 'For teachers managing multiple classes and subjects.',
        'school'       => 'Whole-school visibility for head teachers.',
    ];

    $ctaLabels = [
        'starter'      => 'Get Started Free',
        'professional' => 'Start Free Trial',
        'school'       => 'Contact Us',
    ];

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

        try { $ctaHref = route($ctaRoute); }
        catch (\Exception $e) { $ctaHref = url('/' . $ctaRoute); }

        $maxClasses  = $features['max_classes'] ?? null;
        $classesLine = match(true) {
            $maxClasses === null => 'Unlimited classrooms',
            $maxClasses === 1    => '1 classroom',
            default              => "Up to {$maxClasses} classrooms",
        };
      @endphp

      <div @class([
        'rounded-2xl border-2 transition-all duration-300 reveal overflow-hidden',
        'd' . ($i + 1),
        'border-navy -translate-y-3 shadow-2xl shadow-navy/20' => $featured,
        'border-mist-border bg-white hover:border-navy/25 hover:shadow-lg' => ! $featured,
      ])>

        {{-- Featured top bar --}}
        @if ($featured)
        <div class="bg-navy px-8 py-2.5 flex items-center justify-center gap-2">
          <svg class="w-3.5 h-3.5 text-gold" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
          <span class="font-mono text-[10px] font-bold uppercase tracking-widest text-white">Most Popular</span>
        </div>
        @endif

        <div @class(['p-8', 'bg-navy' => $featured, 'bg-white' => !$featured])>

          {{-- Plan name --}}
          <p @class(['font-mono text-[10px] uppercase tracking-[.18em] mb-3', 'text-white/50' => $featured, 'text-mist-soft' => ! $featured])>
            {{ $plan->name }}
          </p>

          {{-- Price --}}
          <div class="flex items-end gap-1 mb-1">
            @if ($isFree)
              <span @class(['font-extrabold text-5xl leading-none', 'text-white' => $featured, 'text-navy' => !$featured])>Free</span>
            @else
              <span @class(['font-bold text-2xl leading-none pb-1.5', 'text-white/50' => $featured, 'text-mist-text' => ! $featured])>K</span>
              <span @class(['font-extrabold text-5xl leading-none', 'text-white' => $featured, 'text-navy' => !$featured])>{{ number_format((float) $plan->price_zmw) }}</span>
            @endif
          </div>

          {{-- Billing cycle --}}
          <p @class(['text-sm mb-2', 'text-white/40' => $featured, 'text-mist-soft' => ! $featured])>
            {{ $isFree ? 'Forever free' : $cycle }}
          </p>

          {{-- Tagline --}}
          @if ($tagline)
          <p @class(['text-[13px] font-light leading-relaxed mb-6', 'text-white/45' => $featured, 'text-mist-text' => !$featured])>{{ $tagline }}</p>
          @endif

          {{-- Divider --}}
          <div @class(['h-px mb-6', 'bg-white/10' => $featured, 'bg-mist-border' => ! $featured])></div>

          {{-- Max classes line --}}
          <div class="flex items-start gap-2.5 mb-3">
            <span @class(['w-5 h-5 rounded-full inline-flex items-center justify-center text-[10px] font-bold shrink-0 mt-px', 'bg-gold text-white' => $featured, 'bg-navy-subtle text-navy' => ! $featured])>✓</span>
            <span @class(['text-[13.5px] font-light', 'text-white/65' => $featured, 'text-mist-text' => !$featured])>{{ $classesLine }}</span>
          </div>

          {{-- Feature rows --}}
          <ul class="space-y-3 mb-8">
            @foreach ($featureLabels as $key => $label)
              @if ($key === 'max_classes') @continue @endif
              @php $enabled = (bool) ($features[$key] ?? false); @endphp
              <li class="flex items-start gap-2.5">
                @if ($enabled)
                  <span @class(['w-5 h-5 rounded-full inline-flex items-center justify-center text-[10px] font-bold shrink-0 mt-px', 'bg-gold text-white' => $featured, 'bg-navy-subtle text-navy' => ! $featured])>✓</span>
                  <span @class(['text-[13.5px] font-light', 'text-white/65' => $featured, 'text-mist-text' => !$featured])>{{ $label }}</span>
                @else
                  <span @class(['w-5 h-5 rounded-full inline-flex items-center justify-center text-[10px] shrink-0 mt-px', 'bg-white/8 text-white/20' => $featured, 'bg-mist text-mist-soft' => !$featured])>–</span>
                  <span @class(['text-[13.5px] font-light line-through', 'text-white/20' => $featured, 'text-mist-soft' => !$featured])>{{ $label }}</span>
                @endif
              </li>
            @endforeach
          </ul>

          {{-- Formatted price helper --}}
          @if (! $isFree)
          <p @class(['text-[11px] font-mono mb-4', 'text-white/30' => $featured, 'text-mist-soft' => ! $featured])>
            {{ method_exists($plan, 'getFormattedPriceAttribute') ? $plan->formatted_price : 'K' . number_format((float) $plan->price_zmw, 2) }} / {{ $cycle }}
          </p>
          @endif

          {{-- CTA --}}
          <a href="{{ $ctaHref }}" @class([
            'block text-center py-3.5 rounded-xl font-semibold text-sm transition-all no-underline',
            'bg-gold hover:bg-gold-light text-white shadow-lg shadow-gold/30 hover:shadow-gold/45 hover:-translate-y-0.5' => $featured,
            'border-2 border-navy/20 text-navy hover:bg-navy hover:text-white hover:border-navy font-semibold' => ! $featured,
          ])>
            {{ $ctaLabel }}
          </a>
        </div>

      </div>
      @endforeach

    </div>

    <p class="text-center font-mono text-[11px] text-mist-soft mt-10 reveal">
      All prices in Zambian Kwacha (ZMW) · Billed {{ $cycleLabels[$planList->first()->billing_cycle ?? 'termly'] ?? 'per term' }} · No hidden fees
    </p>

  </div>
</section>


{{-- ════════════════════════════
     CTA BANNER
════════════════════════════ --}}
<section class="py-28 px-6 lg:px-12 xl:px-24 relative overflow-hidden text-center" style="background:linear-gradient(145deg, #091e36 0%, #0f2d52 60%, #163660 100%)">
  <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-white/10 to-transparent"></div>
  <div class="absolute -top-40 -left-40 w-[480px] h-[480px] rounded-full opacity-10 blur-3xl pointer-events-none" style="background:radial-gradient(circle, #0284c7, transparent)"></div>
  <div class="absolute -bottom-40 -right-40 w-[480px] h-[480px] rounded-full opacity-10 blur-3xl pointer-events-none" style="background:radial-gradient(circle, #f59e0b, transparent)"></div>

  <div class="max-w-3xl mx-auto relative z-10">
    <p class="font-mono text-[10px] text-white/40 uppercase tracking-[.2em] mb-5 reveal">Ready to start?</p>
    <h2 class="text-5xl xl:text-6xl font-extrabold text-white leading-tight tracking-tight mb-6 reveal d1">
      Less paperwork.<br><span class="text-gold">More teaching.</span>
    </h2>
    <p class="text-lg text-white/45 font-light leading-relaxed max-w-lg mx-auto mb-10 reveal d2">
      Join hundreds of Zambian teachers already saving hours every term with TeachDesk.
    </p>
    <div class="flex flex-wrap items-center justify-center gap-4 reveal d3">
      <a href="{{ route('register') }}" class="inline-flex items-center gap-2 bg-gold hover:bg-gold-light text-white font-bold px-8 py-4 rounded-xl shadow-xl shadow-gold/25 hover:shadow-gold/40 hover:-translate-y-0.5 transition-all no-underline">
        Create your free account
        <svg class="w-4 h-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 8h14M9 2l6 6-6 6"/></svg>
      </a>
      <a href="#" class="inline-flex items-center gap-2 text-white/55 hover:text-white border border-white/15 hover:border-white/35 font-medium px-6 py-4 rounded-xl transition-all no-underline">
        Schedule a demo
      </a>
    </div>
  </div>
</section>


{{-- ════════════════════════════
     FOOTER
════════════════════════════ --}}
<footer class="bg-navy-dark pt-16 pb-8 px-6 lg:px-12 xl:px-24">
  <div class="max-w-7xl mx-auto">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-12 pb-12 border-b border-white/[.07] mb-8">
      <div class="col-span-2 lg:col-span-1">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5 no-underline mb-4">
          <div class="w-8 h-8 rounded-lg bg-navy-light flex items-center justify-center">
            <span class="font-bold text-white text-[13px]">TD</span>
          </div>
          <span class="font-bold text-white/60 text-lg">TeachDesk</span>
        </a>
        <p class="text-[13px] text-white/25 font-light leading-relaxed max-w-xs mt-1">Classroom management for Zambian teachers. ECZ-aligned, proudly local.</p>
        <div class="flex gap-3 mt-5">
          {{-- Social placeholder icons --}}
          @foreach (['M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84','M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6zM2 9h4v12H2z M4 6a2 2 0 100-4 2 2 0 000 4z'] as $p)
          <a href="#" class="w-8 h-8 rounded-lg bg-white/8 hover:bg-white/15 flex items-center justify-center transition-colors no-underline">
            <svg class="w-3.5 h-3.5 text-white/40" viewBox="0 0 24 24" fill="currentColor"><path d="{{ $p }}"/></svg>
          </a>
          @endforeach
        </div>
      </div>
      @foreach (['Product'=>['Features','Modules','Pricing','Changelog'],'Support'=>['Documentation','WhatsApp Support','Video Tutorials','Contact'],'Legal'=>['Privacy Policy','Terms of Service','Data Security']] as $col=>$links)
      <div>
        <p class="font-mono text-[9px] text-white/20 uppercase tracking-widest mb-4">{{ $col }}</p>
        <ul class="space-y-2.5 list-none p-0 m-0">
          @foreach ($links as $link)
          <li><a href="#" class="text-[13px] text-white/30 hover:text-white/70 transition-colors font-light no-underline">{{ $link }}</a></li>
          @endforeach
        </ul>
      </div>
      @endforeach
    </div>
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
      <p class="font-mono text-[11px] text-white/18">© {{ date('Y') }} TeachDesk · Built in Zambia 🇿🇲</p>
      <p class="text-[12px] italic text-white/12">Every classroom. Every student. Every result.</p>
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
