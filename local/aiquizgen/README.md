# AI Quiz Generator for Moodle

Plugin Moodle untuk generate soal quiz menggunakan OpenAI ChatGPT API.

## Fitur

- Generate multiple choice, true/false, dan short answer questions
- Konfigurasi OpenAI API key di admin settings
- Support berbagai tingkat kesulitan (Easy, Medium, Hard)
- Support bahasa Indonesia dan English
- Preview questions sebelum disimpan ke Question Bank
- Logging untuk audit trail
- Terintegrasi langsung dengan Question Bank Moodle

## Instalasi

1. Extract plugin ke direktori `/local/aiquizgen/`
2. Login sebagai admin
3. Navigasi ke **Site administration → Notifications**
4. Install plugin dengan mengikuti instruksi
5. Konfigurasi OpenAI API key di **Site administration → Plugins → Local plugins → AI Quiz Generator**

## Konfigurasi

### 1. Dapatkan OpenAI API Key

1. Kunjungi https://platform.openai.com/
2. Sign up atau login
3. Navigasi ke API keys
4. Create new secret key
5. Copy API key

### 2. Konfigurasi Plugin

1. Login sebagai admin
2. **Site administration → Plugins → Local plugins → AI Quiz Generator**
3. Masukkan OpenAI API Key
4. Pilih model AI (GPT-3.5 Turbo, GPT-4, dll)
5. Atur temperature (0.0 - 2.0, default 0.7)
6. Atur max tokens (default 2000)
7. Atur maximum questions per request (default 20)
8. Save changes

## Penggunaan

### Untuk Teacher/Instructor

1. Masuk ke course yang diinginkan
2. Navigasi ke **Question bank** atau klik link **AI Quiz Generator** di course navigation
3. Klik tombol **Generate Questions with AI**
4. Isi form:
   - **Topic**: Masukkan topik/subject untuk questions (contoh: "Photosynthesis in plants")
   - **Number of Questions**: Pilih berapa banyak questions yang ingin di-generate
   - **Question Type**: Pilih tipe (Multiple Choice, True/False, Short Answer)
   - **Difficulty**: Pilih tingkat kesulitan (Easy, Medium, Hard)
   - **Language**: Pilih bahasa (English atau Indonesian)
   - **Category**: Pilih category di Question Bank untuk menyimpan questions
   - **Additional Instructions** (optional): Instruksi tambahan untuk AI
5. Klik **Generate Questions**
6. Preview questions yang di-generate
7. Questions akan otomatis tersimpan di Question Bank
8. Edit questions jika diperlukan

### Tips untuk Hasil Terbaik

- **Topic yang spesifik**: "Pythagorean theorem in right triangles" lebih baik dari "math"
- **Context yang jelas**: Tambahkan additional instructions untuk konteks spesifik
- **Start dengan sedikit**: Generate 3-5 questions dulu, lalu adjust jika perlu
- **Review dan edit**: Selalu review questions sebelum digunakan di quiz

## Permissions

Plugin ini menambahkan capability baru:
- `local/aiquizgen:generate` - Generate quiz questions dengan AI

By default, diberikan ke:
- Editing Teacher
- Manager

## Privacy

Plugin ini mengirim data berikut ke OpenAI API:
- Topic/subject yang diinput user
- Additional instructions (jika ada)

Plugin ini menyimpan log generation activities:
- User ID yang generate
- Course ID
- Topic
- Number of questions
- Timestamp

## Troubleshooting

### "OpenAI API key is not configured"
- Pastikan API key sudah dikonfigurasi di admin settings
- Check bahwa API key tidak ada spasi di awal/akhir

### "Invalid API key"
- Verify API key di OpenAI platform
- Pastikan API key masih aktif dan belum expired
- Check bahwa account OpenAI memiliki credit/balance

### "Error generating questions"
- Check internet connection
- Verify OpenAI API status di https://status.openai.com/
- Coba dengan jumlah questions yang lebih sedikit
- Coba model yang berbeda (GPT-3.5 vs GPT-4)

### Questions tidak sesuai harapan
- Perjelas topic/subject
- Tambahkan additional instructions
- Adjust difficulty level
- Try regenerate dengan prompt yang berbeda

## Requirements

- Moodle 4.4+ or 5.0+
- PHP 8.1+
- curl extension enabled
- OpenAI API account dengan active API key
- Internet connection

## Support

Untuk issue atau questions, silakan contact administrator.

## License

GNU GPL v3 or later

## Credits

Developed for educational institutions
Based on Moodle coding guidelines
Powered by OpenAI ChatGPT API
