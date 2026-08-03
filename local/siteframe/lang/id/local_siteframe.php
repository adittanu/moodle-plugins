<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Indonesian language strings for local_siteframe
 *
 * @package     local_siteframe
 * @copyright   2026 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'SiteFrame';
$string['siteframe:view'] = 'Lihat konten SiteFrame';
$string['siteframe:manage'] = 'Kelola item SiteFrame';
$string['siteframe:configurecourse'] = 'Konfigurasi SiteFrame untuk kursus';

// Settings
$string['settings_heading'] = 'Pengaturan SiteFrame';
$string['settings_heading_desc'] = 'Konfigurasi pengaturan iframe embedding untuk SiteFrame.';
$string['enabled'] = 'Aktifkan SiteFrame';
$string['enabled_desc'] = 'Jika diaktifkan, fitur SiteFrame tersedia di seluruh situs.';
$string['default_url'] = 'URL Default';
$string['default_url_desc'] = 'URL iframe default yang digunakan secara global ketika tidak ada URL spesifik yang dikonfigurasi.';
$string['allowed_domains'] = 'Domain yang Diizinkan';
$string['allowed_domains_desc'] = 'Daftar domain yang diizinkan, satu per baris (contoh: example.com). Kosongkan untuk mengizinkan semua domain.';
$string['allow_fullpage'] = 'Izinkan mode Halaman Penuh';
$string['allow_fullpage_desc'] = 'Aktifkan mode tampilan halaman penuh untuk item SiteFrame.';
$string['allow_coursepage'] = 'Izinkan mode Halaman Kursus';
$string['allow_coursepage_desc'] = 'Aktifkan mode tampilan halaman kursus.';
$string['allow_widget'] = 'Izinkan mode Widget Mengambang';
$string['allow_widget_desc'] = 'Aktifkan mode tampilan widget mengambang.';
$string['allow_modal'] = 'Izinkan mode Modal/Lightbox';
$string['allow_modal_desc'] = 'Aktifkan mode tampilan modal/lightbox.';
$string['widget_position'] = 'Posisi Widget';
$string['widget_position_desc'] = 'Posisi sudut tombol widget mengambang.';
$string['widget_position_bottomright'] = 'Kanan Bawah';
$string['widget_position_bottomleft'] = 'Kiri Bawah';
$string['widget_position_topright'] = 'Kanan Atas';
$string['widget_position_topleft'] = 'Kiri Atas';
$string['widget_icon'] = 'Ikon Widget';
$string['widget_icon_desc'] = 'Ikon atau emoji yang ditampilkan pada tombol widget.';
$string['widget_title'] = 'Judul Widget';
$string['widget_title_desc'] = 'Judul yang ditampilkan di header panel widget.';
$string['sandbox_flags'] = 'Flag Sandbox';
$string['sandbox_flags_desc'] = 'Flag atribut sandbox iframe (dipisahkan spasi). Contoh: allow-scripts allow-same-origin allow-popups';
$string['extra_allowed_urls'] = 'URL Tambahan yang Diizinkan';
$string['extra_allowed_urls_desc'] = 'URL tambahan yang dapat dipilih guru, satu per baris. Format: label|url';

// Display modes
$string['displaymode_fullpage'] = 'Halaman Penuh';
$string['displaymode_coursepage'] = 'Halaman Kursus';
$string['displaymode_widget'] = 'Widget Mengambang';
$string['displaymode_modal'] = 'Modal/Lightbox';

$string['content'] = 'Konten dan penempatan';
// Manage page
$string['manage_siteframes'] = 'Kelola Item SiteFrame';
$string['manage_heading'] = 'Item SiteFrame';
$string['add_siteframe'] = 'Tambah Item SiteFrame';
$string['edit_siteframe'] = 'Edit Item SiteFrame';
$string['item_name'] = 'Nama';
$string['item_name_desc'] = 'Nama tampilan untuk item SiteFrame ini.';
$string['item_url'] = 'URL';
$string['item_url_desc'] = 'URL sumber iframe.';
$string['item_displaymode'] = 'Mode Tampilan';
$string['item_displaymode_desc'] = 'Bagaimana iframe ini harus ditampilkan.';
$string['item_courseid'] = 'Kursus';
$string['item_courseid_desc'] = 'Kosongkan atau isi 0 untuk global (semua kursus). Pilih kursus untuk kursus spesifik.';
$string['item_height'] = 'Tinggi (px)';
$string['item_height_desc'] = 'Tinggi iframe dalam piksel. 0 = auto/100%.';
$string['item_width'] = 'Lebar';
$string['item_width_desc'] = 'Lebar iframe (contoh: 100%, 800px).';
$string['item_scrolling'] = 'Scrolling';
$string['item_scrolling_desc'] = 'Perilaku scrolling iframe.';
$string['item_visible'] = 'Terlihat';
$string['item_visible_desc'] = 'Tampilkan atau sembunyikan item ini.';
$string['item_saved'] = 'Item SiteFrame berhasil disimpan.';
$string['item_deleted'] = 'Item SiteFrame dihapus.';
$string['no_items'] = 'Belum ada item SiteFrame yang dikonfigurasi.';
$string['actions'] = 'Aksi';
$string['placement'] = 'Penempatan';
$string['preview'] = 'Pratinjau';
$string['scope'] = 'Cakupan';
$string['scope_global'] = 'Global (semua kursus)';
$string['scrolling_auto'] = 'Otomatis';
$string['status_active'] = 'Aktif';
$string['status_hidden'] = 'Tersembunyi';
$string['error_widget_exists'] = 'Widget mengambang aktif sudah ada untuk cakupan ini. Edit, sembunyikan, atau hapus widget tersebut terlebih dahulu.';
$string['visibility_updated'] = 'Visibilitas diperbarui.';

// View page
$string['view_title'] = 'SiteFrame';
$string['course_page'] = 'SiteFrame';
$string['iframe_not_allowed'] = 'Situs ini tidak mengizinkan embedding. Hubungi administrator situs.';
$string['domain_not_allowed'] = 'Domain URL tidak ada dalam daftar domain yang diizinkan.';
$string['url_invalid'] = 'URL yang diberikan tidak valid.';
$string['error_mode_disabled'] = 'Mode tampilan ini dinonaktifkan di pengaturan.';
$string['error_course_not_found'] = 'Kursus tidak ditemukan.';
$string['iframe_blocked'] = 'Situs ini tidak dapat dimuat dalam iframe (diblokir X-Frame-Options atau CSP). Coba buka di tab baru.';
$string['item_hidden'] = 'Item SiteFrame ini tersembunyi.';
$string['sortorder'] = 'Urutan';

// Widget
$string['widget_open'] = 'Buka SiteFrame';
$string['widget_close'] = 'Tutup';

// Privacy
$string['privacy:metadata'] = 'Plugin SiteFrame tidak menyimpan data pribadi pengguna.';
