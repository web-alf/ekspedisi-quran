# Plan: Tambah Section Video di Landing Page

## Context

User ingin menambahkan section video di landing page (`/`) yang mirip dengan gallery section yang sudah ada. Section video harus memiliki fungsionalitas serupa dengan gallery: tampilan grid/slider, lightbox, pengaturan via admin panel, dan manajemen konten CRUD.

## Analysis dari Existing Gallery

Berdasarkan eksplorasi, gallery section menggunakan pola berikut:

1. **Komponen Frontend**: `GallerySection.svelte` menerima props `settings` dan `galleries`
2. **Database**: Tabel `galleries` dengan kolom: title, image, caption, category, sort_order, is_active
3. **Admin**: Full CRUD dengan upload gambar, pengaturan layout (grid/slider/hero/masonry), lightbox
4. **Settings**: Konfigurasi di tabel `settings` dengan group = 'gallery'

## Implementation Approach

### Strategy: Extend Gallery System

Membuat sistem video yang paralel dengan gallery dengan komponen berbeda namun pola yang sama. Menggunakan tabel terpisah `videos` untuk fleksibilitas.

## Detailed Plan

### Phase 1: Database & Model

**File Baru:**
- `database/migrations/2025_04_06_000001_create_videos_table.php`
  - Kolom: id, title, video_url (string), thumbnail (string, nullable), caption, video_type ('youtube'), category, sort_order, is_active, timestamps
  - Note: Hanya support YouTube videos
  - Indexes: category, is_active, sort_order

- `app/Models/Video.php`
  - $fillable: [title, video_url, thumbnail, caption, video_type, category, sort_order, is_active]
  - Note: video_type hanya 'youtube' (Vimeo/upload tidak di-support)
  - Casts untuk boolean
  - Accessor: video_embed_url, thumbnail_url
  - Scopes: active(), ordered(), byType()

### Phase 2: Backend (Controller & Routes)

**File Baru:**
- `app/Http/Controllers/Admin/VideoController.php`
  - Copy pola dari GalleryController
  - Methods: index, create, store, show, edit, update, destroy, toggleStatus, updateOrder, updateSettings
  - Handle video URL validation (YouTube format only)
  - Support berbagai format URL YouTube: youtube.com, youtu.be, dengan/without query params
    - Contoh: https://youtu.be/3_mxkdlLL8Y?si=Qhr1VIjv78FYh7iP
  - Extract video ID dari URL untuk embed dan thumbnail
  - Auto-generate thumbnail dari YouTube (https://img.youtube.com/vi/{video_id}/hqdefault.jpg)

**Modifikasi:**
- `routes/web.php` (tambahkan route admin untuk videos)
- Route public: query Video::active()->ordered()->get() dan pass ke Landing page (sudah ada di route landing)

### Phase 3: Frontend - Komponen Video

**File Baru:**
- `resources/js/Components/VideoSection.svelte`
  - Props: settings (konfigurasi), videos (array data)
  - Layouts: grid, slider, hero (sama seperti gallery)
  - Lightbox modal untuk play video
  - Support embed YouTube dengan iframe (YouTube only)
  - Lazy loading untuk thumbnail
  - Fallback jika tidak ada video

**Fitur:**
- Grid: 3 kolom responsive
- Slider: Navigasi arrow + dots
- Hero: Video utama besar + thumbnails
- Lightbox: Modal dengan iframe embed
- Auto-generate thumbnail dari YouTube

### Phase 4: Admin Pages

**File Baru:**
- `resources/js/Pages/Admin/Videos/Index.svelte`
  - Grid view video dengan thumbnail
  - Tab: Videos list + Settings
  - Actions: Edit, Delete, Toggle status, Reorder

- `resources/js/Pages/Admin/Videos/Create.svelte`
  - Form: Title, Video URL (YouTube/Vimeo), Thumbnail upload (optional), Caption, Sort order
  - Preview video sebelum save
  - Validasi URL format

- `resources/js/Pages/Admin/Videos/Edit.svelte`
  - Sama dengan Create tapi dengan data existing

- `resources/js/Pages/Admin/Videos/Show.svelte`
  - Detail view video

### Phase 5: Landing Page Integration

**Modifikasi:**
- `resources/js/Pages/Landing.svelte`
  - Tambahkan: `export let videos = [];`
  - Import VideoSection component
  - Tambahkan section video setelah gallery atau di posisi yang diinginkan
  - Pass props: `<VideoSection {settings} {videos} />`

- `routes/web.php` (route landing)
  - Tambahkan query: `$videos = Video::active()->ordered()->get();`
  - Pass ke Inertia: `['videos' => $videos]`

### Phase 6: Settings

**Settings yang akan dibuat** (group = 'video'):
- `landing_video_enabled` (boolean) - Toggle section on/off
- `landing_video_title` (string) - Judul section
- `landing_video_subtitle` (string) - Deskripsi section
- `landing_video_layout` (select: 'grid', 'slider') - Layout tampilan (simplified: grid atau slider saja)
- `landing_video_autoplay` (boolean) - Auto-play di slider
- `landing_video_show_captions` (boolean) - Tampilkan caption
- `landing_video_lightbox` (boolean) - Enable lightbox modal
- `landing_video_max_items` (number) - Maksimal video ditampilkan

## Reusable Functions/Components

Dari eksplorasi, berikut yang bisa direuse:

1. **Image handling pattern** dari GalleryController (HandlesImageUpload trait)
2. **Lightbox pattern** dari GallerySection.svelte (modal dengan keyboard nav)
3. **Layout grid/slider** CSS classes dan struktur
4. **Settings management** dari GalleryController/updateSettings
5. **Pagination component** dari Admin galleries
6. **Toast notifications** dari stores/toast.js

## Critical Files to Modify

| File | Change Type | Description |
|------|-------------|-------------|
| `database/migrations/*_create_videos_table.php` | Create | New migration for videos table |
| `app/Models/Video.php` | Create | New model |
| `app/Http/Controllers/Admin/VideoController.php` | Create | New controller |
| `routes/web.php` | Modify | Add video routes + pass videos to landing |
| `resources/js/Components/VideoSection.svelte` | Create | New video component |
| `resources/js/Pages/Landing.svelte` | Modify | Add VideoSection component usage |
| `resources/js/Pages/Admin/Videos/*.svelte` | Create | Admin CRUD pages (4 files) |

## Verification Steps

1. **Migration**: Run `php artisan migrate` - verify videos table created
2. **Admin CRUD**:
   - Navigate to `/admin/videos`
   - Create new video with YouTube URL
   - Verify thumbnail auto-generated
   - Edit video
   - Toggle status active/inactive
   - Reorder videos
3. **Landing Page**:
   - Visit `/` - verify video section appears
   - Test layout switching (grid/slider/hero)
   - Test lightbox play video
   - Test responsive design
4. **Settings**:
   - Toggle section enabled/disabled
   - Change title/subtitle
   - Verify changes reflected on landing

## Dependencies

- YouTube/Vimeo embed API (no package needed, pure iframe)
- Existing: FileStorageService untuk thumbnail upload
- Existing: Gallery pattern sebagai referensi implementasi
