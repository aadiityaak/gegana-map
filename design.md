# Dark Mode “Hacker” Design

## Tujuan

- Tampilan gelap yang nyaman, kontras tinggi, terasa “terminal/hacker” tanpa mengorbankan keterbacaan.
- Hierarki jelas: konten utama menonjol, elemen sekunder tetap terbaca.
- Konsisten untuk halaman auth, dashboard, dan form.

## Brand Feel

- Nuansa: blue-sky, scanline halus, glow tipis, sudut sedikit tajam.
- Tekstur: grid halus / noise ringan (opsional), jangan sampai mengganggu teks.
- Bahasa UI: ringkas, “system-like” (contoh: ACCESS_REQUIRED, AUTHENTICATING).

## Palet Warna

### Dasar

- Background utama: #05070A
- Surface 1 (panel): #0A0F14
- Surface 2 (raised): #0E1620
- Border: rgba(171, 213, 229, 0.22)

### Teks

- Teks utama: rgba(222, 238, 248, 0.92)
- Teks sekunder: rgba(171, 213, 229, 0.65)
- Teks muted: rgba(171, 213, 229, 0.45)

### Aksen

- Primary (biru muda): #ABD5E5
- Primary hover: #8EC8DD
- Primary glow: rgba(171, 213, 229, 0.35)

### Status

- Info: #60A5FA
- Warning: #F59E0B
- Danger: #F87171
- Success: #ABD5E5

## Tipografi

- Font: monospace untuk UI auth/komponen “terminal”; sans untuk konten panjang bila perlu.
- Ukuran:
    - Heading: 20–28px (semibold)
    - Body: 14–16px (normal)
    - Caption: 12–13px (medium)
- Spasi huruf: heading/button bisa diberi tracking ringan (+0.02–0.06em).

## Grid & Spacing

- Radius: 10–14px untuk card/panel, 8–10px untuk input/button.
- Spacing scale: 4 / 8 / 12 / 16 / 24 / 32.
- Gunakan pemisah tipis (1px) dengan border biru transparan, bukan shadow berat.

## Efek Visual

- Glow: tipis dan terkontrol, muncul saat hover/focus.
- Scanline: opsional, sangat halus (opacity rendah) dan hanya di background.
- Shadow: minim, gunakan “ambient” gelap (bukan drop shadow tebal).

## Komponen

### Button

- Primary: border biru transparan + background biru sangat tipis + teks biru.
- Hover: border lebih terang, background sedikit naik, glow tipis.
- Disabled: turunkan opacity, hilangkan glow.

### Input

- Background: hitam transparan, border biru tipis.
- Focus: ring biru (alpha) + border lebih terang.
- Error: border merah transparan + teks helper merah.

### Card / Panel

- Surface 1/2 dengan border biru tipis.
- Header memakai judul monospace + subjudul muted.

### Table

- Header: teks muted, garis bawah tipis.
- Row hover: naikkan sedikit background (surface 2) dan border halus.

### Toast / Alert

- Style “system log”: ikon kecil + judul singkat + detail opsional.
- Warna status jangan terlalu neon, cukup jelas.

## Motion

- Durasi: 150–220ms.
- Easing: ease-out untuk hover, ease-in-out untuk transisi panel.
- Hindari animasi besar; fokus pada feedback kecil (glow/fade/slide tipis).

## Hacker Animation (Dashboard)

- Prinsip: animasi harus terasa “terminal/sistem”, halus, dan tidak mengganggu keterbacaan.
- Overlay wajib `pointer-events: none` agar tidak mengganggu klik/scroll.
- Gunakan `prefers-reduced-motion` untuk mematikan animasi pada user yang sensitif terhadap motion.

### Layer Overlay

- Grid (`.fx-grid`)
    - Pola: garis horizontal tipis + garis vertikal jarang.
    - Opacity rendah (±0.12–0.20) dan saturasi ringan.
- Scanline (`.fx-scan`)
    - Gradient vertikal tipis bergerak dari atas ke bawah (loop).
    - Mix blend: `screen` agar tidak “menutupi” konten.
    - Durasi: ±6–7s linear infinite.
- Noise (`.fx-noise`)
    - Dot-noise ringan dengan `mix-blend-mode: overlay`.
    - Animasi “jitter” kecil (steps) supaya terasa analog, bukan shimmer besar.

### Animasi Konten

- Glitch title (`.glitch`)
    - Menggunakan `::before` dan `::after` dengan `clip-path` segment atas/bawah.
    - Offset horizontal kecil (±0.4–0.8px) + text-shadow warna aksen.
    - Durasi: ±2.3–2.7s (alternate/alternate-reverse).
- Cursor blink (`.cursor`)
    - Opacity step (on/off) 1s infinite.
- Card glow pulse (`.dash-card`)
    - Box-shadow biru tipis yang "bernapas" (3–4s ease-in-out).
- Chart line draw (`.dash-draw`)
    - `stroke-dasharray`/`stroke-dashoffset` untuk efek “digambar”.
    - Delay per seri: `.dash-draw-1/.dash-draw-2/.dash-draw-3`.

### Reduced Motion

- Untuk `prefers-reduced-motion: reduce`, nonaktifkan:
    - `.fx-scan`, `.fx-noise`, `.dash-card`, `.dash-draw`, `.glitch::before`, `.glitch::after`, `.cursor`

## Aksesibilitas

- Kontras teks utama tetap tinggi di background gelap.
- Fokus keyboard selalu terlihat (ring jelas).
- Jangan mengandalkan warna saja untuk status (tambahkan ikon/label).

## Contoh Utility (Tailwind)

- Background: `bg-black/80` + `backdrop-blur` tipis untuk overlay.
- Border: `border border-sky-500/30`
- Teks: `text-sky-300`
- Focus: `focus:ring-2 focus:ring-sky-400/40 focus:border-sky-400`
- Panel: `bg-black/50` atau `bg-zinc-950/60` dengan border biru transparan

## Do / Don’t

- Do: gunakan biru sebagai aksen, bukan untuk semua teks.
- Do: jaga whitespace agar tidak terlihat "ramai".
- Don't: glow berlebihan atau animasi berkedip yang mengganggu.
- Don't: kombinasi biru neon + background terang (mengurangi kenyamanan).
