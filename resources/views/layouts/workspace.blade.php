<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#faf9fd">
    <title>@yield('title', 'Workspace') — RisenGrowth</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { surface:'#fbf8ff', 'surface-container-lowest':'#fff', 'surface-container-low':'#f4f2fd', 'surface-container':'#eeedf7', 'surface-container-high':'#e8e7f1', 'on-surface':'#1a1b22', 'on-surface-variant':'#64748b', 'on-background':'#1a1b22', secondary:'#0051d5', 'secondary-fixed':'#dbe1ff', 'on-secondary-fixed-variant':'#003ea8', primary:'#000', 'on-primary':'#fff' }, fontFamily:{sans:['Inter','ui-sans-serif','system-ui']} } } };</script>
    <style>
      :root{color-scheme:light}body{font-family:Inter,ui-sans-serif,system-ui;background:#fbf8ff;color:#1a1b22}
      .material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 450,'GRAD' 0,'opsz' 22;line-height:1}
      .rg-nav{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:12px;font-size:13px;font-weight:550;color:#525866;transition:background .15s,color .15s}
      .rg-nav:hover{background:#f4f2fd;color:#171923}.rg-nav[aria-current=page]{background:#e8e7f1;color:#171923;font-weight:700}
      .rg-nav .material-symbols-outlined{font-size:19px}
      .rg-shadow{box-shadow:0 1px 8px rgba(0,0,0,.035)}
      @media(min-width:1024px){.rg-content{padding-left:264px}}
      [x-cloak]{display:none!important}
    </style>
    @stack('styles')
</head>
<body class="antialiased min-h-screen">
@php
    $navItems = [
      ['overview', 'Ikhtisar', 'dashboard'],
      ['leads.index', 'Kotak Masuk Prospek', 'inbox'],
      ['discovery.index', 'Eksplorasi', 'travel_explore'],
      ['keywords.index', 'Kata Kunci', 'tag'],
      ['comments.index', 'Generator Komentar', 'auto_awesome'],
      ['reviews.index', 'Antrean Review', 'fact_check'],
      ['activity.index', 'Aktivitas', 'show_chart'],
    ];
@endphp
<div class="lg:hidden sticky top-0 z-50 border-b border-slate-200 bg-white/95 backdrop-blur rg-shadow px-4 py-3 flex items-center justify-between">
  <a href="{{ route('overview') }}" class="font-extrabold tracking-tight">RisenGrowth <span class="text-[10px] font-semibold bg-slate-100 rounded px-1.5 py-1">Pro AI</span></a>
  <button type="button" id="rg-menu-toggle" aria-controls="rg-sidebar" aria-expanded="false" aria-label="Buka navigasi" class="inline-flex items-center justify-center rounded-xl border border-slate-200 w-10 h-10"><span class="material-symbols-outlined">menu</span></button>
</div>
<div id="rg-overlay" class="hidden fixed inset-0 bg-slate-950/30 z-40 lg:hidden" aria-hidden="true"></div>
<aside id="rg-sidebar" class="fixed z-50 inset-y-0 left-0 w-[264px] max-w-[88vw] bg-white border-r border-slate-100 rg-shadow flex flex-col -translate-x-full lg:translate-x-0 transition-transform duration-200" aria-label="Navigasi utama">
  <div class="px-5 h-[72px] flex items-center gap-2.5 border-b border-slate-50">
    <a href="{{ route('overview') }}" class="flex gap-2.5 items-center" aria-label="RisenGrowth Beranda"><span class="w-9 h-9 bg-[#111525] rounded-xl flex items-center justify-center text-white"><span class="material-symbols-outlined">monitoring</span></span><span class="font-bold tracking-tight text-[16px]">RisenGrowth</span></a>
    <span class="text-[10px] font-semibold rounded bg-[#f1effa] px-1.5 py-1 text-slate-500">Pro AI</span>
  </div>
  <div class="px-4 py-4 flex-1 overflow-y-auto">
    <div class="text-[11px] uppercase tracking-wider font-bold text-slate-400 px-3 mb-2">Workspace</div>
    <nav aria-label="Menu workspace" class="space-y-0.5">
       @foreach($navItems as [$routeName, $label, $icon])
          <a href="{{ route($routeName) }}" class="rg-nav" @if(request()->routeIs($routeName)) aria-current="page" @endif>
             <span class="material-symbols-outlined" aria-hidden="true">{{ $icon }}</span>
             <span class="flex-1">{{ $label }}</span>
             @if($routeName==='leads.index' && ($workspaceNewLeadsCount ?? 0)>0)<span class="rounded-full bg-blue-50 text-blue-700 px-2 py-0.5 text-[10px] font-bold">{{ $workspaceNewLeadsCount }}</span>@endif
             @if($routeName==='reviews.index' && ($workspacePendingReviewsCount ?? 0)>0)<span class="rounded-full bg-slate-100 text-slate-700 px-2 py-0.5 text-[10px] font-bold">{{ $workspacePendingReviewsCount }}</span>@endif
          </a>
       @endforeach
    </nav>
    @if(auth()->user()?->role === 'admin')
       <div class="text-[11px] uppercase tracking-wider font-bold text-slate-400 px-3 mt-7 mb-2">Administrasi</div>
       <a href="{{ route('admin.users.index') }}" class="rg-nav" @if(request()->routeIs('admin.users.*')) aria-current="page" @endif><span class="material-symbols-outlined">group</span><span>User Management</span></a>
    @endif
  </div>
  <div class="px-4 pb-4 pt-3 border-t border-slate-100 space-y-1">
    <a href="{{ route('settings.index') }}" class="rg-nav" @if(request()->routeIs('settings.*')) aria-current="page" @endif><span class="material-symbols-outlined">settings</span><span>Pengaturan</span></a>
    <div class="flex items-center gap-2.5 px-3 py-3 bg-[#f4f2fd] rounded-xl mt-2">
        <span class="w-9 h-9 rounded-full bg-slate-950 text-white inline-flex items-center justify-center"><span class="material-symbols-outlined">person</span></span>
        <div class="min-w-0 flex-1"><div class="truncate text-xs font-bold">{{ auth()->user()?->name }}</div><div class="text-[11px] truncate text-slate-500">{{ auth()->user()?->email }}</div></div>
    </div>
    <form method="POST" action="{{ route('logout') }}">@csrf <button type="submit" class="rg-nav w-full text-left"><span class="material-symbols-outlined">logout</span>Keluar</button></form>
  </div>
</aside>
<div class="rg-content min-h-screen min-w-0">
  <header class="hidden lg:flex sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-slate-100 h-[72px] items-center justify-between px-6 xl:px-10">
    <div class="flex gap-2 items-center text-sm text-slate-500"><span>Workspace</span><span class="text-slate-300">/</span><strong class="text-slate-900 font-semibold">@yield('page_heading','Ikhtisar')</strong></div>
    <div class="flex items-center gap-3">
      <a href="{{ route('leads.index') }}" class="hidden xl:inline-flex text-sm text-slate-500 hover:text-slate-950 items-center gap-1.5"><span class="material-symbols-outlined text-[18px]">search</span>Cari prospek</a>
      <a href="{{ route('discovery.index') }}" class="bg-slate-950 text-white text-xs font-semibold px-4 py-2.5 rounded-xl hover:bg-slate-800 inline-flex gap-1.5 items-center"><span class="material-symbols-outlined text-[17px]">travel_explore</span>Eksplorasi</a>
    </div>
  </header>
  <main id="main-content" class="w-full min-w-0 pb-12">
    @if(session('success'))<div role="status" class="mx-5 md:mx-8 xl:mx-10 mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div role="alert" class="mx-5 md:mx-8 xl:mx-10 mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>@endif
    @yield('content')
  </main>
</div>
<script>
(function(){const b=document.getElementById('rg-menu-toggle'),n=document.getElementById('rg-sidebar'),o=document.getElementById('rg-overlay');function set(open){n.classList.toggle('-translate-x-full',!open);o.classList.toggle('hidden',!open);b.setAttribute('aria-expanded',String(open));}b.addEventListener('click',()=>set(b.getAttribute('aria-expanded')!=='true'));o.addEventListener('click',()=>set(false));document.addEventListener('keydown',e=>{if(e.key==='Escape')set(false)});})();
</script>
@stack('scripts')
</body>
</html>
