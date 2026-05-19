# Dark Mode “Hacker” Design

## Tujuan

- Tampilan gelap yang nyaman, kontras tinggi, terasa “terminal/hacker” tanpa mengorbankan keterbacaan.
- Hierarki jelas: konten utama menonjol, elemen sekunder tetap terbaca.
- Konsisten untuk halaman auth, dashboard, dan form.

## Brand Feel

- Nuansa: matrix-green, scanline halus, glow tipis, sudut sedikit tajam.
- Tekstur: grid halus / noise ringan (opsional), jangan sampai mengganggu teks.
- Bahasa UI: ringkas, “system-like” (contoh: ACCESS_REQUIRED, AUTHENTICATING).

## Palet Warna

### Dasar

- Background utama: #05070A
- Surface 1 (panel): #0A0F14
- Surface 2 (raised): #0E1620
- Border: rgba(34, 197, 94, 0.22)

### Teks

- Teks utama: rgba(226, 255, 232, 0.92)
- Teks sekunder: rgba(164, 255, 186, 0.65)
- Teks muted: rgba(164, 255, 186, 0.45)

### Aksen

- Primary (green): #22C55E
- Primary hover: #34D399
- Primary glow: rgba(34, 197, 94, 0.35)

### Status

- Info: #60A5FA
- Warning: #F59E0B
- Danger: #F87171
- Success: #22C55E

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
- Gunakan pemisah tipis (1px) dengan border hijau transparan, bukan shadow berat.

## Efek Visual

- Glow: tipis dan terkontrol, muncul saat hover/focus.
- Scanline: opsional, sangat halus (opacity rendah) dan hanya di background.
- Shadow: minim, gunakan “ambient” gelap (bukan drop shadow tebal).

## Komponen

### Button

- Primary: border hijau transparan + background hijau sangat tipis + teks hijau.
- Hover: border lebih terang, background sedikit naik, glow tipis.
- Disabled: turunkan opacity, hilangkan glow.

### Input

- Background: hitam transparan, border hijau tipis.
- Focus: ring hijau (alpha) + border lebih terang.
- Error: border merah transparan + teks helper merah.

### Card / Panel

- Surface 1/2 dengan border hijau tipis.
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

## Aksesibilitas

- Kontras teks utama tetap tinggi di background gelap.
- Fokus keyboard selalu terlihat (ring jelas).
- Jangan mengandalkan warna saja untuk status (tambahkan ikon/label).

## Contoh Utility (Tailwind)

- Background: `bg-black/80` + `backdrop-blur` tipis untuk overlay.
- Border: `border border-green-500/30`
- Teks: `text-green-300` / `text-green-300/70`
- Focus: `focus:ring-2 focus:ring-green-400/40 focus:border-green-400`
- Panel: `bg-black/50` atau `bg-zinc-950/60` dengan border hijau transparan

## Do / Don’t

- Do: gunakan hijau sebagai aksen, bukan untuk semua teks.
- Do: jaga whitespace agar tidak terlihat “ramai”.
- Don’t: glow berlebihan atau animasi berkedip yang mengganggu.
- Don’t: kombinasi hijau neon + background terang (mengurangi kenyamanan).
