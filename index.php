<?php
/**
 * ReTaste - Halaman pembuka (landing page)
 * Pengunjung melihat penjelasan singkat, lalu diarahkan ke login / daftar.
 * Satu file: PHP + CSS + JS di dalam file ini. Yang dibaca dari luar hanya gambar, video, dan font di assets/.
 */
session_start();

// ---- Pengaturan halaman (ubah di sini bila nama file/folder berbeda) ----
$app = [
    'nama'     => 'ReTaste',
    'tagline'  => 'Good Food. New Chances.',
    'login'    => 'login.php',
    'register' => 'register.php',
    'tahun'    => date('Y'),
];

$dashboardByRole = [
    'pembeli' => 'dashboard_pelanggan.php',
    'penjual' => 'dashboard_penjual.php',
    'admin' => 'dashboard_admin.php',
];
/* Sudah login? Pastikan akunnya benar-benar ada di database sebelum diarahkan ke dashboard. */
if (!empty($_SESSION['user_id'])) {
    $sesiOk = false; $sesiRole = '';
    try {
        require __DIR__ . '/koneksi.php';
        $st = $pdo->prepare('SELECT role FROM users WHERE id_user = ? AND status_akun = "aktif" LIMIT 1');
        $st->execute([(int)$_SESSION['user_id']]);
        $sesiRole = (string)$st->fetchColumn();
        $sesiOk = $sesiRole !== '' && $sesiRole === ($_SESSION['role'] ?? '');
    } catch (Throwable $ex) { $sesiOk = false; }
    if ($sesiOk && isset($dashboardByRole[$sesiRole])) { header('Location: ' . $dashboardByRole[$sesiRole]); exit; }
    foreach (['user_id', 'nama', 'email', 'role', 'baru'] as $k) { unset($_SESSION[$k]); }
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

/** Ikon SVG sederhana (garis), warna mengikuti currentColor */
function ikon(string $nama): string {
    $p = [
        'pin'     => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'bag'     => '<path d="M5 8h14l-1 12H6L5 8z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
        'qr'      => '<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><path d="M14 14h2v2h-2zM18 14h2v2h-2zM14 18h2v2h-2zM18 18h2v2h-2z"/>',
        'star'    => '<path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9 6.8 19.6l1-5.8-4.3-4.1 5.9-.9L12 3.5z"/>',
        'store'   => '<path d="M4 9.5 5.5 4h13L20 9.5"/><path d="M4 9.5a2.7 2.7 0 0 0 5.3 0 2.7 2.7 0 0 0 5.4 0 2.7 2.7 0 0 0 5.3 0"/><path d="M5.5 12.5V20h13v-7.5"/><path d="M10 20v-4.5h4V20"/>',
        'check'   => '<path d="m4.5 12.5 4.5 4.5 10.5-10.5"/>',
        'camera'  => '<path d="M4 8h3l1.5-2h7L17 8h3v11H4V8z"/><circle cx="12" cy="13" r="3.3"/>',
        'wallet'  => '<path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H18v3"/><path d="M4 7.5V18a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1H6.5A2.5 2.5 0 0 1 4 7.5z"/><circle cx="16" cy="13.5" r="1.1"/>',
        'leaf'    => '<path d="M5 19c0-8 5-13 14-14 0 9-5 14-13 14"/><path d="M5 19c2-4 5-7 9-9"/>',
        'cap'     => '<path d="m2.5 9 9.5-4.5L21.5 9 12 13.5 2.5 9z"/><path d="M6.5 11.5V16c1.5 1.5 3.4 2 5.5 2s4-.5 5.5-2v-4.5"/>',
        'chart'   => '<path d="M4 20V4"/><path d="M4 20h16"/><path d="m7.5 14 3.5-4 3 2.5 4.5-6"/>',
        'shield'  => '<path d="M12 3.5 5 6v5.5c0 4.2 2.9 7.4 7 9 4.1-1.6 7-4.8 7-9V6l-7-2.5z"/><path d="m9 12 2.2 2.2L15.5 10"/>',
        'clock'   => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'arrow'   => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
        'pause'   => '<path d="M8 5v14M16 5v14"/>',
        'play'    => '<path d="M7 4.5v15l12-7.5-12-7.5z"/>',
        'heart'   => '<path d="M12 20s-7.5-4.6-7.5-10.2A4.3 4.3 0 0 1 12 7.6a4.3 4.3 0 0 1 7.5 2.2C19.5 15.4 12 20 12 20z"/>',
        'box'     => '<path d="m12 3 8.5 4.5v9L12 21l-8.5-4.5v-9L12 3z"/><path d="m3.5 7.5 8.5 4.5 8.5-4.5M12 12v9"/>',
    ];
    return '<svg class="ikon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$nama] ?? '') . '</svg>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($app['nama']) ?> &ndash; <?= e($app['tagline']) ?></title>
  <meta name="description" content="ReTaste menghubungkan UMKM kuliner dengan pembeli untuk menyelamatkan makanan sisa layak konsumsi dengan harga hemat. Cari, pesan, lalu ambil di toko terdekat.">
  <meta name="theme-color" content="#FFEBBC">
  <meta property="og:title" content="<?= e($app['nama']) ?> - <?= e($app['tagline']) ?>">
  <meta property="og:description" content="Selamatkan makanan sisa layak konsumsi dari UMKM kuliner terdekat dengan harga hemat.">
  <meta property="og:image" content="assets/img/retaste-store.jpg">
  <link rel="icon" type="image/png" href="assets/img/favicon.png">
  <link rel="preload" href="assets/fonts/baloo-2-latin-700-normal.woff2" as="font" type="font/woff2" crossorigin>
  <style>
/* =========================================================
   ReTaste - style.css
   Palet: Amber Flame #F7A503 | Sunny Wheat #FFDA8C | Vanilla Cream #FFEBBC
          Olive Meadow #B3BC53 | Forest Moss #768C3A
   ========================================================= */

@font-face{font-family:"Baloo 2";font-weight:600;font-style:normal;font-display:swap;src:url("assets/fonts/baloo-2-latin-600-normal.woff2") format("woff2")}
@font-face{font-family:"Baloo 2";font-weight:700;font-style:normal;font-display:swap;src:url("assets/fonts/baloo-2-latin-700-normal.woff2") format("woff2")}
@font-face{font-family:"Baloo 2";font-weight:800;font-style:normal;font-display:swap;src:url("assets/fonts/baloo-2-latin-800-normal.woff2") format("woff2")}
@font-face{font-family:"Nunito";font-weight:400;font-style:normal;font-display:swap;src:url("assets/fonts/nunito-latin-400-normal.woff2") format("woff2")}
@font-face{font-family:"Nunito";font-weight:600;font-style:normal;font-display:swap;src:url("assets/fonts/nunito-latin-600-normal.woff2") format("woff2")}
@font-face{font-family:"Nunito";font-weight:700;font-style:normal;font-display:swap;src:url("assets/fonts/nunito-latin-700-normal.woff2") format("woff2")}

:root{
  --amber:#F7A503;
  --wheat:#FFDA8C;
  --cream:#FFEBBC;
  --olive:#B3BC53;
  --moss:#768C3A;

  --ink:#2B3510;          /* teks utama: hijau tua dari palet */
  --ink-soft:#56602F;     /* teks sekunder */
  --amber-deep:#B97800;   /* amber gelap untuk teks kecil (kontras) */
  --paper:#FFF8E6;        /* latar halaman: krem sangat terang */
  --white:#FFFFFF;

  --r:20px;
  --shadow:0 10px 30px -12px rgba(70,80,20,.35);
  --font-head:"Baloo 2","Trebuchet MS",system-ui,sans-serif;
  --font-body:"Nunito",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
}

*,*::before,*::after{box-sizing:border-box}
html{scroll-behavior:smooth;scroll-padding-top:96px}
body{margin:0;overflow-x:hidden;background:var(--paper);color:var(--ink);font-family:var(--font-body);font-size:1.0625rem;line-height:1.65;-webkit-font-smoothing:antialiased}
img,video{max-width:100%;display:block}
a{color:inherit}
h1,h2,h3{font-family:var(--font-head);line-height:1.15;margin:0 0 .5em;font-weight:700}
p{margin:0 0 1em}
ul,ol{margin:0;padding:0;list-style:none}
.wrap{width:min(1120px,100% - 2rem);margin-inline:auto}
.ikon{width:1.25em;height:1.25em;flex:none}

.lewati{position:absolute;left:-999px;top:0;background:var(--ink);color:#fff;padding:.6rem 1rem;z-index:100;border-radius:0 0 10px 0}
.lewati:focus{left:0}
:focus-visible{outline:3px solid var(--amber);outline-offset:3px;border-radius:6px}

/* ---------- Tombol ---------- */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;padding:.85rem 1.5rem;border-radius:999px;font:700 1rem/1 var(--font-body);text-decoration:none;border:2px solid transparent;cursor:pointer;transition:transform .15s,box-shadow .15s,background .15s,color .15s}
.btn:hover{transform:translateY(-2px)}
.btn--primary{background:var(--amber);color:#2B1D00;box-shadow:0 8px 18px -8px rgba(247,165,3,.9)}
.btn--primary:hover{background:#FFB41F}
.btn--ghost{background:transparent;color:var(--ink);border-color:var(--moss)}
.btn--ghost:hover{background:var(--moss);color:#fff}
.btn--light{background:var(--cream);color:var(--ink)}
.btn--light:hover{background:#fff}
.btn--sm{padding:.55rem 1.1rem;font-size:.95rem}

/* ---------- Navbar: pil putih melayang (gaya gend-off) ---------- */
.nav{position:fixed;top:12px;left:0;right:0;z-index:100}
.nav__in{position:relative;display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:8px 12px 8px 18px;border-radius:999px;background:#fff;border:1px solid #fff;box-shadow:0 12px 30px -14px rgba(29,37,9,.5);transition:box-shadow .2s}
.nav__in::before{content:"";position:absolute;inset:-6px -16px;z-index:-1;border-radius:999px;pointer-events:none;background:linear-gradient(100deg,rgba(118,140,58,.55),rgba(247,165,3,.8) 50%,rgba(118,140,58,.55));filter:blur(20px);opacity:.45}
.nav.is-scrolled .nav__in{box-shadow:0 14px 34px -12px rgba(29,37,9,.6)}
.nav__brand{display:flex;align-items:center;gap:.5rem;text-decoration:none;font:800 1.6rem/1 var(--font-head);color:var(--amber)}
.nav__brand b{color:var(--moss);font-weight:800}
.nav__brand img{width:44px;height:44px;object-fit:contain}
.nav__menu{display:flex;align-items:center;gap:.3rem}
.nav__menu a:not(.btn){text-decoration:none;font-weight:700;color:var(--ink);padding:.55rem 1rem;border-radius:999px;transition:background .2s,color .2s}
.nav__menu a:not(.btn):hover{background:rgba(118,140,58,.16);color:var(--moss)}
.nav__menu .btn{margin-left:.35rem}
.nav__toggle{display:none;background:none;border:0;padding:.6rem;cursor:pointer}
.nav__toggle span{display:block;width:24px;height:3px;border-radius:3px;background:var(--moss);margin:5px 0;transition:transform .2s,opacity .2s}
.nav__toggle[aria-expanded="true"] span:nth-child(1){transform:translateY(8px) rotate(45deg)}
.nav__toggle[aria-expanded="true"] span:nth-child(2){opacity:0}
.nav__toggle[aria-expanded="true"] span:nth-child(3){transform:translateY(-8px) rotate(-45deg)}

/* ---------- Hero: satu layar penuh, video latar agak blur, gelap ke sisi kiri ---------- */
.hero{position:relative;margin:10px;height:calc(100vh - 20px);height:calc(100svh - 20px);min-height:600px;display:flex;align-items:center;color:#fff;overflow:hidden;border-radius:36px;background:var(--ink)}
.hero__video{position:absolute;inset:0;z-index:0}
.hero__video video{width:100%;height:100%;object-fit:cover;filter:blur(1.2px);transform:scale(1.03)}
.hero::before{content:"";position:absolute;inset:0;z-index:1;background:linear-gradient(105deg,rgba(24,31,8,.94) 8%,rgba(38,48,14,.72) 52%,rgba(46,58,18,.30) 100%)}
.hero::after{content:"";position:absolute;inset:0;z-index:1;pointer-events:none;background:radial-gradient(700px 420px at 0% 100%,rgba(247,165,3,.22),transparent 70%)}
.hero__text{position:relative;z-index:2;padding:104px 0 44px;text-align:left}
.hero__text>*{max-width:680px}
.tag{display:inline-flex;gap:.55rem;align-items:center;padding:.5rem 1rem;border-radius:999px;background:rgba(20,28,6,.55);border:1px solid rgba(255,255,255,.4);color:#fff!important;font-size:.88rem;font-weight:700;margin:0;text-shadow:0 1px 6px rgba(0,0,0,.5)}
.tag i{width:9px;height:9px;border-radius:50%;background:var(--olive);animation:pulse 1.6s infinite}
@keyframes pulse{0%{box-shadow:0 0 0 0 rgba(179,188,83,.7)}100%{box-shadow:0 0 0 12px transparent}}
.hero__tagline{max-width:none!important;font-size:clamp(2.4rem,min(6.2vw,11.5vh),5.6rem);font-weight:800;line-height:.98;text-transform:uppercase;letter-spacing:.01em;margin:.3em 0 .25em;text-shadow:0 4px 24px rgba(0,0,0,.35)}
.hero__tagline span{display:block}
.tl{display:block;min-height:1em}
.tl.typing::after{content:"";display:inline-block;width:.07em;height:.78em;margin-left:.06em;background:var(--amber);vertical-align:-.02em;animation:blink .8s steps(1) infinite}
@keyframes blink{50%{opacity:0}}
.t-w{color:#fff}
.t-lime{color:var(--olive)}
.hero__lead{max-width:540px;color:rgba(255,255,255,.9);font-size:clamp(1rem,1.6vw,1.1rem);margin:0 0 1.6rem}
.hero__cta{display:flex;flex-wrap:wrap;gap:.8rem;margin:0 0 1.5rem}
.hero .btn--ghost{color:#fff;border-color:rgba(255,255,255,.75)}
.hero .btn--ghost:hover{background:#fff;color:var(--ink);border-color:#fff}
.hero__chips{display:flex;flex-wrap:wrap;gap:.6rem}
.hero__chips li{display:inline-flex;align-items:center;gap:.4rem;padding:.4rem .9rem;border-radius:999px;background:rgba(255,255,255,.13);border:1px solid rgba(255,255,255,.26);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);color:#fff;font-weight:700;font-size:.9rem}
.hero__chips .ikon{color:var(--olive)}
.hero__text>.hero__lead,.hero__text>.hero__cta,.hero__text>.hero__chips{animation:naik .7s both}
.hero__text>.hero__lead{animation-delay:1.9s}.hero__text>.hero__cta{animation-delay:2.1s}.hero__text>.hero__chips{animation-delay:2.3s}
@keyframes naik{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}

.eyebrow{display:inline-block;margin:0 0 .6rem;font-weight:800;font-size:.85rem;letter-spacing:.12em;text-transform:uppercase;color:var(--amber-deep)}

/* ---------- Section umum ---------- */
.sec{padding:clamp(3.5rem,8vw,6rem) 0}
.sec__head{text-align:center;max-width:640px;margin:0 auto 2.5rem}
.sec__head h2{font-size:clamp(1.9rem,4.5vw,2.75rem)}
.sec__sub{color:var(--ink-soft)}

/* ---------- Tentang ---------- */
.sec--tentang{background:var(--paper)}
.tentang{display:grid;grid-template-columns:1.05fr 1fr;gap:clamp(1.5rem,5vw,4rem);align-items:center}
.tentang__foto{margin:0;position:relative}
.tentang__foto::before{content:"";position:absolute;inset:18px -14px -14px 18px;background:var(--olive);border-radius:28px;z-index:0;opacity:.55}
.tentang__foto img{position:relative;z-index:1;width:100%;height:auto;border-radius:28px;box-shadow:var(--shadow)}
.tentang__foto figcaption{position:relative;z-index:1;display:flex;gap:.5rem;align-items:center;margin-top:1rem;padding:0 1rem 1rem calc(18px + 1rem);font-size:.92rem;font-weight:700;color:var(--moss)}
.tentang__isi h2{font-size:clamp(1.9rem,4.2vw,2.6rem)}
.tentang__isi p{color:var(--ink-soft)}
.tentang__isi p strong{color:var(--ink)}
.ticks{display:grid;gap:.8rem;margin-top:1.2rem}
.ticks li{display:flex;gap:.7rem;align-items:flex-start;color:var(--ink-soft)}
.ticks .ikon{margin-top:.2em;padding:3px;border-radius:50%;background:var(--olive);color:#fff;width:1.5em;height:1.5em}
.ticks b{color:var(--ink)}

/* ---------- Alur ---------- */
.sec--alur{background:var(--cream);min-height:100vh;min-height:100svh;display:flex;align-items:center;padding:clamp(84px,13vh,112px) 0 clamp(20px,5vh,48px)}
.sec--alur>.wrap{width:min(1120px,100% - 2rem)}
.sec--alur .sec__head{max-width:none;margin-bottom:clamp(.8rem,2.6vh,1.8rem)}
.sec--alur .sec__head h2{font-size:clamp(1.7rem,min(4vw,6.4vh),2.5rem);margin-bottom:0}
.sec--alur .tabs{margin-bottom:clamp(1.6rem,4.4vh,2.3rem)}
.sec--alur .step{padding:clamp(1.9rem,4vh,2.4rem) 1.2rem clamp(1rem,2.6vh,1.5rem)}
.sec--alur .step__ico{width:clamp(48px,8vh,60px);height:clamp(48px,8vh,60px);margin-bottom:clamp(.5rem,1.6vh,1rem)}
.sec--alur .step h3{font-size:clamp(1.1rem,2.4vh,1.25rem)}
.sec--alur .step p{font-size:clamp(.88rem,2vh,.96rem);line-height:1.55}
.tabs{display:flex;gap:.5rem;justify-content:center;margin-bottom:2.2rem;flex-wrap:wrap}
.tab{display:inline-flex;align-items:center;gap:.5rem;padding:.7rem 1.3rem;border-radius:999px;border:2px solid var(--moss);background:transparent;color:var(--moss);font:700 1rem var(--font-body);cursor:pointer;transition:background .15s,color .15s}
.tab.is-on{background:var(--moss);color:#fff}
.tab:not(.is-on):hover{background:rgba(118,140,58,.12)}
.panel[hidden]{display:none}
.steps{display:grid;grid-template-columns:repeat(4,1fr);gap:1.2rem;counter-reset:s}
.step{position:relative;background:#fff;border-radius:var(--r);padding:2.4rem 1.4rem 1.6rem;box-shadow:var(--shadow);text-align:center}
.step h3{font-size:1.3rem;margin-bottom:.35em}
.step p{font-size:.97rem;color:var(--ink-soft);margin:0}
.step__no{position:absolute;top:-16px;left:50%;transform:translateX(-50%);width:34px;height:34px;display:grid;place-items:center;border-radius:50%;background:var(--amber);color:#2B1D00;font:800 1.05rem var(--font-head);box-shadow:0 0 0 5px var(--cream)}
.step__ico{display:grid;place-items:center;width:64px;height:64px;margin:0 auto 1rem;border-radius:20px;background:var(--wheat);color:var(--ink)}
.step__ico .ikon{width:30px;height:30px}
.step:nth-child(even) .step__ico{background:var(--olive);color:#fff}
/* garis penghubung antar langkah (desktop) */
@media (min-width:901px){
  .step:not(:last-child)::after{content:"";position:absolute;top:50%;right:-1.2rem;width:1.2rem;border-top:3px dotted var(--moss);opacity:.6}
}

/* ---------- Manfaat ---------- */
.sec--manfaat{background:var(--paper)}
.cards{display:grid;grid-template-columns:repeat(3,1fr);gap:1.4rem}
.card{border-radius:26px;padding:2rem 1.7rem;box-shadow:var(--shadow);transition:transform .2s}
.card:hover{transform:translateY(-6px)}
.card h3{font-size:1.5rem}
.card__ico{display:grid;place-items:center;width:56px;height:56px;border-radius:18px;margin-bottom:1rem}
.card__ico .ikon{width:28px;height:28px}
.card ul{display:grid;gap:.7rem}
.card li{display:flex;gap:.6rem;align-items:flex-start;font-weight:600}
.card li .ikon{margin-top:.25em;width:1.1em;height:1.1em;flex:none}
.card--amber{background:var(--wheat);color:var(--ink)}
.card--amber .card__ico{background:var(--amber);color:#2B1D00}
.card--olive{background:var(--olive);color:#1F2809}
.card--olive .card__ico{background:var(--cream);color:var(--moss)}
.card--moss{background:var(--moss);color:#fff}
.card--moss .card__ico{background:var(--cream);color:var(--moss)}

/* ---------- CTA ---------- */
.sec--cta{padding-top:0;background:var(--paper)}
.cta{display:grid;grid-template-columns:auto 1fr;gap:2rem;align-items:center;background:var(--moss);color:#fff;border-radius:32px;padding:clamp(1.8rem,5vw,3rem);position:relative;overflow:hidden}
.cta::before,.cta::after{content:"";position:absolute;border-radius:50%;background:var(--olive);opacity:.35}
.cta::before{width:260px;height:260px;right:-80px;top:-100px}
.cta::after{width:160px;height:160px;right:120px;bottom:-90px;background:var(--amber);opacity:.4}
.cta__logo{position:relative;z-index:1;width:clamp(120px,16vw,180px);height:auto;background:var(--paper);border-radius:28px;padding:1rem}
.cta__isi{position:relative;z-index:1}
.cta h2{font-size:clamp(1.7rem,4vw,2.4rem);color:#fff}
.cta p{color:#EEF2D5;max-width:52ch}
.cta__btn{display:flex;flex-wrap:wrap;gap:.8rem;margin-top:1.2rem}

/* ---------- Footer ---------- */
.foot{background:var(--ink);color:#DDE3BC;padding:2.2rem 0}
.foot__in{display:flex;flex-wrap:wrap;gap:1rem 2rem;align-items:center;justify-content:space-between}
.foot__brand{display:flex;flex-direction:column;line-height:1.2}
.foot__brand strong{font:800 1.5rem var(--font-head);color:var(--amber)}
.foot__brand strong span{color:var(--olive)}
.foot__nav{display:flex;flex-wrap:wrap;gap:.4rem 1.3rem}
.foot__nav a{text-decoration:none;font-weight:700}
.foot__nav a:hover{color:var(--amber)}
.foot small{flex-basis:100%;border-top:1px solid rgba(255,255,255,.14);padding-top:1rem;color:#B9C192}

/* ---------- Animasi muncul saat scroll ---------- */
.js .reveal{opacity:0;transform:translateY(22px);transition:opacity .6s ease,transform .6s ease}
.js .reveal.is-in{opacity:1;transform:none}
.js .steps .reveal:nth-child(2){transition-delay:.08s}
.js .steps .reveal:nth-child(3){transition-delay:.16s}
.js .steps .reveal:nth-child(4){transition-delay:.24s}
.js .cards .reveal:nth-child(2){transition-delay:.1s}
.js .cards .reveal:nth-child(3){transition-delay:.2s}

/* ---------- Responsif ---------- */
@media (max-width:900px){
  .steps{grid-template-columns:repeat(2,1fr);row-gap:2rem}
  .cards{grid-template-columns:1fr}
  .tentang{grid-template-columns:1fr}
  .tentang__foto{margin-right:14px}
}
@media (max-width:760px){
  .nav__toggle{display:block}
  .nav__in{padding:6px 8px 6px 14px}
  .nav__menu{position:absolute;left:0;right:0;top:calc(100% + 10px);flex-direction:column;align-items:stretch;gap:.2rem;background:#fff;border-radius:24px;padding:.6rem 1rem 1.1rem;box-shadow:0 18px 34px -14px rgba(29,37,9,.55);display:none}
  .nav__menu.is-open{display:flex}
  .nav__menu a:not(.btn){padding:.7rem .8rem}
  .nav__menu .btn{margin:.4rem 0 0}
  .hero{margin:8px;height:auto;min-height:calc(100svh - 16px);border-radius:28px}
  .hero::before{background:linear-gradient(180deg,rgba(24,31,8,.78) 0%,rgba(30,40,10,.82) 55%,rgba(24,31,8,.92) 100%)}
  .hero__text{padding:96px 0 36px}
  .cta{grid-template-columns:1fr;text-align:center;justify-items:center}
  .cta__btn{justify-content:center}
}
@media (max-width:520px){
  .steps{grid-template-columns:1fr}
  .hero__cta .btn{width:100%}
}

@media (prefers-reduced-motion:reduce){
  html{scroll-behavior:auto}
  *,*::before,*::after{transition:none!important;animation:none!important}
  .js .reveal{opacity:1;transform:none}
}
  </style>
  <script>document.documentElement.classList.add('js');</script>
</head>
<body>

<a class="lewati" href="#konten">Lewati ke konten</a>

<!-- ============ NAVBAR ============ -->
<header class="nav" id="nav">
  <div class="wrap nav__in">
    <a class="nav__brand" href="#atas" aria-label="<?= e($app['nama']) ?> - beranda">
      <img src="assets/img/favicon.png" alt="" width="36" height="36">
      <span>Re<b>Taste</b></span>
    </a>
    <button class="nav__toggle" id="navToggle" aria-expanded="false" aria-controls="navMenu" aria-label="Buka menu">
      <span></span><span></span><span></span>
    </button>
    <nav class="nav__menu" id="navMenu" aria-label="Menu utama">
      <a href="#tentang">Tentang</a>
      <a href="#alur">Cara Kerja</a>
      <a href="#manfaat">Manfaat</a>
      <a class="btn btn--ghost btn--sm" href="<?= e($app['login']) ?>">Masuk</a>
      <a class="btn btn--primary btn--sm" href="<?= e($app['register']) ?>">Daftar</a>
    </nav>
  </div>
</header>

<main id="konten">

  <!-- ============ 1. VIDEO PEMBUKA ============ -->
  <section class="hero" id="atas" aria-label="Video pembuka ReTaste">
    <div class="hero__video" aria-hidden="true">
      <video id="heroVideo" autoplay muted loop playsinline preload="auto" poster="assets/img/hero-poster.jpg">
        <source src="assets/video/video1.mp4" type="video/mp4">
      </video>
    </div>

    <!-- ============ 2. TEKS DI KIRI (video di belakang, gelap ke sisi kiri) ============ -->
    <div class="hero__text wrap">
      <span class="tag"><i></i> Selamatkan makanan, hemat di kantong</span>
      <h1 class="hero__tagline" aria-label="Good Food. New Chances.">
        <span class="t-w tl" id="t1" data-text="Good Food.">Good Food.</span>
        <span class="t-lime tl" id="t2" data-text="New Chances.">New Chances.</span>
      </h1>
      <p class="hero__lead">
        ReTaste mempertemukan UMKM kuliner yang punya makanan sisa layak konsumsi
        dengan kamu yang mencari makanan enak berharga hemat. Tinggal pesan, lalu ambil di toko terdekat.
      </p>
      <div class="hero__cta">
        <a class="btn btn--primary" href="<?= e($app['register']) ?>">Mulai Selamatkan Makanan <?= ikon('arrow') ?></a>
        <a class="btn btn--ghost" href="#alur">Lihat Cara Kerja</a>
      </div>
      <ul class="hero__chips" aria-label="Ringkasan">
        <li><?= ikon('pin') ?> Toko terdekat</li>
        <li><?= ikon('wallet') ?> Harga lebih hemat</li>
        <li><?= ikon('leaf') ?> Kurangi makanan terbuang</li>
      </ul>
    </div>
  </section>

  <!-- ============ 3 & 4a. GAMBAR + PENJELASAN WEB ============ -->
  <section class="sec sec--tentang" id="tentang">
    <div class="wrap tentang">
      <figure class="tentang__foto reveal">
        <img src="assets/img/retaste-store.jpg" width="1600" height="1067" loading="lazy"
             alt="Penjual menyerahkan kantong kertas ReTaste kepada pembeli di toko dengan kemasan ramah lingkungan">
        <figcaption><?= ikon('leaf') ?> Kemasan ramah lingkungan untuk masa depan yang lebih hijau</figcaption>
      </figure>

      <div class="tentang__isi reveal">
        <p class="eyebrow">Apa itu ReTaste?</p>
        <h2>Makanan layak, jangan sampai terbuang</h2>
        <p>
          Setiap sore, banyak toko roti, warung, katering, dan kafe menyisakan makanan yang
          <strong>masih layak dimakan</strong>. Sayang kalau dibuang, apalagi bagi pemilik usaha.
        </p>
        <p>
          <strong>ReTaste</strong> adalah web marketplace tempat UMKM kuliner menjual paket makanan sisa
          dengan harga lebih murah dalam jendela waktu ambil tertentu, misalnya pukul 17.00&ndash;19.00.
          Pembeli mencari paket terdekat, memesan, membayar, lalu mengambil sendiri di toko.
        </p>
        <ul class="ticks">
          <li><?= ikon('check') ?> <span><b>Paket Kejutan</b> berisi aneka makanan sesuai kategori, atau <b>menu spesifik</b> yang sudah jelas isinya.</span></li>
          <li><?= ikon('check') ?> <span>Toko diverifikasi admin dan penjual wajib menyatakan makanan layak konsumsi serta mencantumkan alergen.</span></li>
        </ul>
      </div>
    </div>
  </section>

  <!-- ============ 4b. ALUR ============ -->
  <section class="sec sec--alur" id="alur">
    <div class="wrap">
      <header class="sec__head reveal">
        <p class="eyebrow">Cara kerja</p>
        <h2>Empat langkah dari layar ke piring</h2>
      </header>

      <div class="tabs reveal" role="tablist" aria-label="Pilih peran">
        <button class="tab is-on" role="tab" id="tab-pembeli" aria-selected="true"  aria-controls="panel-pembeli" type="button"><?= ikon('bag') ?> Untuk Pembeli</button>
        <button class="tab"       role="tab" id="tab-penjual" aria-selected="false" aria-controls="panel-penjual" type="button" tabindex="-1"><?= ikon('store') ?> Untuk Penjual</button>
      </div>

      <div class="panel is-on" role="tabpanel" id="panel-pembeli" aria-labelledby="tab-pembeli">
        <ol class="steps">
          <li class="step reveal">
            <span class="step__no">1</span>
            <span class="step__ico"><?= ikon('pin') ?></span>
            <h3>Cari paket terdekat</h3>
            <p>Atur lokasimu, lalu lihat paket yang masih tersedia. Urutkan dari yang terdekat dan filter sesuai selera.</p>
          </li>
          <li class="step reveal">
            <span class="step__no">2</span>
            <span class="step__ico"><?= ikon('wallet') ?></span>
            <h3>Pesan &amp; bayar</h3>
            <p>Pilih jumlah, lalu bayar dalam waktu 15 menit. Stokmu ditahan selama itu, jadi tidak diambil orang lain.</p>
          </li>
          <li class="step reveal">
            <span class="step__no">3</span>
            <span class="step__ico"><?= ikon('qr') ?></span>
            <h3>Ambil dengan kode</h3>
            <p>Datang ke toko pada jam ambil dan tunjukkan kode pengambilan atau QR dari halaman pesananmu.</p>
          </li>
          <li class="step reveal">
            <span class="step__no">4</span>
            <span class="step__ico"><?= ikon('star') ?></span>
            <h3>Nikmati &amp; beri rating</h3>
            <p>Makan dengan tenang, lalu bantu toko berkembang lewat rating dan ulasanmu.</p>
          </li>
        </ol>
      </div>

      <div class="panel" role="tabpanel" id="panel-penjual" aria-labelledby="tab-penjual" hidden>
        <ol class="steps">
          <li class="step reveal">
            <span class="step__no">1</span>
            <span class="step__ico"><?= ikon('store') ?></span>
            <h3>Daftarkan toko</h3>
            <p>Isi profil toko: alamat, titik lokasi, jam buka, dan foto. Tim ReTaste akan memeriksanya.</p>
          </li>
          <li class="step reveal">
            <span class="step__no">2</span>
            <span class="step__ico"><?= ikon('shield') ?></span>
            <h3>Tunggu verifikasi</h3>
            <p>Setelah disetujui admin, tokomu aktif dan siap memasang paket. Jika ditolak, kamu menerima catatan untuk diperbaiki.</p>
          </li>
          <li class="step reveal">
            <span class="step__no">3</span>
            <span class="step__ico"><?= ikon('box') ?></span>
            <h3>Pasang paket sore</h3>
            <p>Tentukan harga, jumlah, jam ambil, foto, dan alergen. Cukup beberapa menit sebelum toko tutup.</p>
          </li>
          <li class="step reveal">
            <span class="step__no">4</span>
            <span class="step__ico"><?= ikon('check') ?></span>
            <h3>Verifikasi &amp; terima hasil</h3>
            <p>Cocokkan kode dari pembeli, serahkan paketnya, dan pantau pendapatan di dashboard.</p>
          </li>
        </ol>
      </div>
    </div>
  </section>

  <!-- ============ 4c. BENEFIT ============ -->
  <section class="sec sec--manfaat" id="manfaat">
    <div class="wrap">
      <header class="sec__head reveal">
        <p class="eyebrow">Manfaat</p>
        <h2>Menang untuk semua pihak</h2>
        <p class="sec__sub">Satu paket yang diselamatkan memberi kebaikan bagi tiga pihak sekaligus.</p>
      </header>

      <div class="cards">
        <article class="card card--amber reveal">
          <span class="card__ico"><?= ikon('heart') ?></span>
          <h3>Untuk Pembeli</h3>
          <ul>
            <li><?= ikon('check') ?> Makanan enak dengan harga jauh lebih hemat</li>
            <li><?= ikon('check') ?> Lokasi dekat, diurutkan dari jarak terdekat</li>
            <li><?= ikon('check') ?> Harga khusus mahasiswa setelah verifikasi bukti SKS</li>
          </ul>
        </article>

        <article class="card card--olive reveal">
          <span class="card__ico"><?= ikon('chart') ?></span>
          <h3>Untuk UMKM</h3>
          <ul>
            <li><?= ikon('check') ?> Tambahan pemasukan dari dagangan sisa</li>
            <li><?= ikon('check') ?> Pelanggan baru di sekitar toko</li>
            <li><?= ikon('check') ?> Dashboard penjualan dan paket terselamatkan</li>
          </ul>
        </article>

        <article class="card card--moss reveal">
          <span class="card__ico"><?= ikon('leaf') ?></span>
          <h3>Untuk Lingkungan</h3>
          <ul>
            <li><?= ikon('check') ?> Mengurangi makanan yang terbuang</li>
            <li><?= ikon('check') ?> Setiap paket terselamatkan ikut terhitung</li>
            <li><?= ikon('check') ?> Mendorong kemasan yang lebih ramah lingkungan</li>
          </ul>
        </article>
      </div>
    </div>
  </section>

  <!-- ============ CTA PENUTUP KE LOGIN ============ -->
  <section class="sec sec--cta" aria-labelledby="cta-judul">
    <div class="wrap cta reveal">
      <img class="cta__logo" src="assets/img/logo.png" width="180" height="158" loading="lazy" alt="Logo ReTaste">
      <div class="cta__isi">
        <h2 id="cta-judul">Siap memberi makanan kesempatan kedua?</h2>
        <p>Masuk untuk mulai memesan, atau daftarkan tokomu dan ubah sisa hari ini menjadi rezeki.</p>
        <div class="cta__btn">
          <a class="btn btn--primary" href="<?= e($app['login']) ?>">Masuk <?= ikon('arrow') ?></a>
          <a class="btn btn--light" href="<?= e($app['register']) ?>">Buat akun baru</a>
        </div>
      </div>
    </div>
  </section>

</main>

<footer class="foot">
  <div class="wrap foot__in">
    <div class="foot__brand">
      <strong>Re<span>Taste</span></strong>
      <span><?= e($app['tagline']) ?></span>
    </div>
    <nav class="foot__nav" aria-label="Tautan footer">
      <a href="#tentang">Tentang</a>
      <a href="#alur">Cara Kerja</a>
      <a href="#manfaat">Manfaat</a>
      <a href="<?= e($app['login']) ?>">Masuk</a>
      <a href="<?= e($app['register']) ?>">Daftar</a>
    </nav>
    <small>&copy; <?= e((string)$app['tahun']) ?> <?= e($app['nama']) ?>. Semua hak dilindungi.</small>
  </div>
</footer>

<script>
/* ReTaste - main.js */
(function () {
  'use strict';

  /* Bayangan navbar saat di-scroll */
  var nav = document.getElementById('nav');
  function onScroll() { nav.classList.toggle('is-scrolled', window.scrollY > 8); }
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  /* Menu mobile */
  var toggle = document.getElementById('navToggle');
  var menu = document.getElementById('navMenu');
  function closeMenu() {
    menu.classList.remove('is-open');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Buka menu');
  }
  toggle.addEventListener('click', function () {
    var open = menu.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
  });
  menu.addEventListener('click', function (e) { if (e.target.closest('a')) closeMenu(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMenu(); });

  /* Video pembuka: tanpa tombol kontrol. Hormati preferensi "kurangi gerakan". */
  var video = document.getElementById('heroVideo');
  if (video) {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      video.removeAttribute('autoplay');
    } else {
      var p = video.play && video.play();
      if (p && p.catch) p.catch(function () {});
    }
  }

  /* Animasi mengetik pada tagline (langsung tampil penuh bila "kurangi gerakan") */
  (function () {
    var l1 = document.getElementById('t1'), l2 = document.getElementById('t2');
    if (!l1 || !l2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var t1 = l1.getAttribute('data-text'), t2 = l2.getAttribute('data-text');
    l1.setAttribute('aria-hidden', 'true'); l2.setAttribute('aria-hidden', 'true');
    l1.textContent = ''; l2.textContent = '';
    function ketik(el, teks, cb) {
      var i = 0;
      el.classList.add('typing');
      (function langkah() {
        el.textContent = teks.slice(0, ++i);
        if (i < teks.length) setTimeout(langkah, 75);
        else { el.classList.remove('typing'); if (cb) setTimeout(cb, 220); }
      })();
    }
    setTimeout(function () {
      ketik(l1, t1, function () {
        ketik(l2, t2, function () {
          l2.classList.add('typing');
          setTimeout(function () { l2.classList.remove('typing'); }, 2400);
        });
      });
    }, 350);
  })();

  /* Tab alur: Pembeli / Penjual */
  var tabs = Array.prototype.slice.call(document.querySelectorAll('.tab'));
  function pilih(tab) {
    tabs.forEach(function (t) {
      var on = t === tab;
      t.classList.toggle('is-on', on);
      t.setAttribute('aria-selected', String(on));
      t.tabIndex = on ? 0 : -1;
      var panel = document.getElementById(t.getAttribute('aria-controls'));
      panel.hidden = !on;
      panel.classList.toggle('is-on', on);
      if (on) {
        /* ulangi transisi kartu setiap kali tab dibuka (sama seperti tab Pembeli) */
        var kartu = panel.querySelectorAll('.reveal');
        kartu.forEach(function (el) { el.classList.remove('is-in'); });
        void panel.offsetWidth;
        requestAnimationFrame(function () { kartu.forEach(function (el) { el.classList.add('is-in'); }); });
      }
    });
  }
  tabs.forEach(function (t, i) {
    t.addEventListener('click', function () { pilih(t); });
    t.addEventListener('keydown', function (e) {
      var k = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
      if (!k) return;
      var next = tabs[(i + k + tabs.length) % tabs.length];
      next.focus(); pilih(next);
    });
  });

  /* Animasi muncul saat di-scroll */
  var items = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
      });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
    items.forEach(function (el) { io.observe(el); });
  } else {
    items.forEach(function (el) { el.classList.add('is-in'); });
  }
})();
</script>
</body>
</html>