# Quick Start Guide - AI Quiz Generator

## Untuk Administrator

### 1. Install Plugin
```bash
# Plugin sudah ada di /local/aiquizgen/
# Login sebagai admin dan navigate ke:
Site administration → Notifications → Install plugin
```

### 2. Konfigurasi OpenAI API Key
```
1. Get API key dari https://platform.openai.com/api-keys
2. Site administration → Plugins → Local plugins → AI Quiz Generator
3. Masukkan API Key
4. Pilih Model: GPT-3.5 Turbo (recommended)
5. Save changes
```

### 3. Test Plugin
```
1. Masuk ke course sebagai teacher
2. Klik "AI Quiz Generator" di course navigation
3. Generate 2-3 test questions
4. Verify questions muncul di Question Bank
```

---

## Untuk Teacher/Instructor

### Cara Generate Questions

1. **Access Plugin**
   - Masuk ke course Anda
   - Klik link **"AI Quiz Generator"** di course navigation
   - Atau dari Question Bank, klik tombol **"Generate Questions with AI"**

2. **Isi Form**
   - **Topic**: Contoh: "Sistem Peredaran Darah Manusia"
   - **Number**: Pilih 5 questions
   - **Type**: Multiple Choice
   - **Difficulty**: Medium
   - **Language**: Indonesian
   - **Category**: Pilih category di Question Bank
   - **Additional Instructions** (optional): "Fokus pada fungsi organ"

3. **Generate & Review**
   - Klik **Generate Questions**
   - Tunggu 10-30 detik
   - Preview questions yang di-generate
   - Questions otomatis tersimpan di Question Bank

4. **Use in Quiz**
   - Buka Quiz activity
   - Edit quiz
   - Add questions from Question Bank
   - Pilih questions yang di-generate tadi

### Tips untuk Hasil Terbaik

✅ **DO:**
- Gunakan topic yang spesifik: "Newton's Third Law of Motion"
- Berikan context jika perlu: "For high school physics students"
- Start dengan 3-5 questions, lalu adjust
- Review dan edit questions sebelum digunakan
- Gunakan bahasa yang konsisten (Indo atau English)

❌ **DON'T:**
- Topic terlalu umum: "Science" atau "Math"
- Generate terlalu banyak sekaligus (>10)
- Langsung pakai tanpa review
- Mix languages dalam satu generation

### Contoh Topics yang Bagus

**Matematika:**
- "Teorema Pythagoras untuk segitiga siku-siku"
- "Persamaan kuadrat dan cara penyelesaiannya"
- "Integral tak tentu fungsi trigonometri"

**Sains:**
- "Fotosintesis pada tumbuhan hijau"
- "Hukum kekekalan energi"
- "Reaksi asam basa dan indikator pH"

**Bahasa:**
- "Past perfect tense dalam Bahasa Inggris"
- "Majas metafora dan personifikasi"
- "Kalimat aktif dan pasif"

**Umum:**
- "Sejarah kemerdekaan Indonesia"
- "Komponen hardware komputer"
- "Prinsip dasar akuntansi"

---

## Troubleshooting Cepat

**❌ "API key not configured"**
→ Hubungi admin untuk setting API key

**❌ "Error generating questions"**
→ Coba lagi dengan topic yang lebih sederhana
→ Kurangi jumlah questions

**❌ Questions tidak sesuai**
→ Perjelas topic
→ Tambahkan additional instructions
→ Coba regenerate

**❌ Questions terlalu mudah/sulit**
→ Ubah difficulty level
→ Tambahkan context di additional instructions

---

## FAQ

**Q: Berapa lama proses generate?**
A: 10-30 detik tergantung jumlah questions dan model yang dipakai

**Q: Apakah gratis?**
A: Plugin gratis, tapi OpenAI API berbayar (cost per request)

**Q: Bisa bahasa Indonesia?**
A: Ya! Pilih "Indonesian" di language selector

**Q: Apakah questions otomatis masuk quiz?**
A: Tidak, tersimpan di Question Bank dulu. Anda perlu add manual ke quiz

**Q: Bisa edit questions setelah di-generate?**
A: Ya! Edit di Question Bank seperti questions biasa

**Q: Compatible dengan Moodle versi berapa?**
A: Moodle 4.4+ dan 5.0+

**Q: Maksimal berapa questions?**
A: Default 20 per request (configurable oleh admin)

**Q: Apakah questions unik setiap generate?**
A: Ya, AI akan generate questions berbeda setiap kali (tergantung temperature)

---

## Next Steps

1. ✅ Generate test questions
2. ✅ Review & edit if needed
3. ✅ Add to quiz activity
4. ✅ Test quiz with students
5. ✅ Collect feedback
6. ✅ Generate more questions as needed

---

**Need Help?**
- Check INSTALL.md untuk troubleshooting detail
- Check README.md untuk dokumentasi lengkap
- Contact system administrator
