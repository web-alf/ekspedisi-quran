# 📋 QUOTATION - MUSHAF REQUEST ENHANCEMENT

**Project:** Ekspedisi Quran - Sistem Manajemen Distribusi Al-Qur'an Wakaf
**Date:** 3 Oktober 2025
**Document Type:** Feature Enhancement Quotation
**Valid Until:** 31 Oktober 2025

---

## 📊 EXECUTIVE SUMMARY

Quotation ini mencakup pengembangan 3 fitur enhancement untuk modul **Permintaan Mushaf** yang bertujuan meningkatkan efisiensi operasional tim Customer Service dan transparansi kepada lembaga pemohon.

### 🎯 Objectives:
1. **Efisiensi Import Data** - Bulk import permintaan mushaf dari Excel (menghemat ~80% waktu input manual)
2. **Fleksibilitas Data Management** - Kemampuan hapus data di semua status kecuali completed
3. **Transparansi Approval** - Sistem tracking perbedaan jumlah yang diajukan vs yang disetujui

---

## 💼 SCOPE OF WORK

### **Feature 1: Import Permintaan Mushaf dari Excel** 📥

#### **Business Problem:**
Saat ini CS harus input manual satu per satu permintaan mushaf yang sudah dikumpulkan dari lembaga. Untuk 100 permintaan, dibutuhkan ~5 jam kerja manual.

#### **Solution:**
Sistem import bulk dari file Excel (.xlsx) dengan mapping otomatis ke database.

#### **Technical Specifications:**

**Input Format (Excel Template):**
| Column | Type | Required | Description | Example |
|--------|------|----------|-------------|---------|
| `nama_lembaga` | Text | ✅ Yes | Nama lembaga pemohon | "Pondok Pesantren Al-Hikmah" |
| `nama_penanggung_jawab_1` | Text | ✅ Yes | Nama PJ utama | "Ustadz Ahmad" |
| `nomor_hp_penanggung_jawab_1` | Text | ✅ Yes | Nomor WhatsApp | "081234567890" / "62812..." |
| `alamat_lengkap` | Text | ✅ Yes | Alamat lengkap lembaga | "Jl. Sudirman No. 123, ..." |
| `link_gmaps` | URL | ⚪ Optional | Link Google Maps | "https://maps.google.com/?q=..." |
| `jumlah_kebutuhan_mushaf` | Number | ✅ Yes | Jumlah yang diajukan | 100 |
| `jumlah_digenapkan` | Number | ⚪ Optional | Jumlah yang disetujui | 75 |
| `urgensi` | Text | ⚪ Optional | Tingkat urgensi | "Sangat Mendesak" |

**Auto Processing:**
- ✅ Auto-generate nomor request (format: REQ-2025-00001)
- ✅ Auto-extract koordinat dari Google Maps link
- ✅ Auto-normalize nomor HP ke format international (62xxx)
- ✅ Auto-set default values untuk kolom yang tidak diisi
- ✅ Auto-validation dengan error reporting per baris

**Default Values (Auto-filled):**
```yaml
jenis_mushaf_diminta: ["A5"]  # Default A5
jumlah_mushaf_a5: [jumlah_kebutuhan_mushaf]
kategori_lembaga: null  # Bisa diisi manual nanti
nama_pengurus_2: null
jabatan_pengurus_1: "Penanggung Jawab"
sumber_info: "Import XLSX oleh CS"
foto_santri_path: null
foto_lembaga_path: null
file_nama_santri_path: null
status: "pending"
```

**Deliverables:**
- ✅ Excel template file untuk import
- ✅ Import functionality dengan validation
- ✅ Error reporting (menampilkan baris yang error)
- ✅ Success summary (X data berhasil, Y data gagal)
- ✅ UI: Upload button + Download template button
- ✅ Documentation: User manual import guide

**Time Saving:**
- **Manual:** 100 permintaan = ~5 jam (3 menit/permintaan)
- **Import:** 100 permintaan = ~5 menit
- **Efficiency Gain:** 98% time reduction

---

### **Feature 2: Delete Permission Enhancement** 🗑️

#### **Business Problem:**
Saat ini delete button hanya muncul di status `pending`. Jika ada data duplikat atau kesalahan input di status lain (reviewed, approved, rejected, processed), tidak bisa dihapus.

#### **Solution:**
Enable delete button untuk semua status **KECUALI** `completed`.

#### **Technical Specifications:**

**Current State:**
```javascript
// Delete button hanya muncul di status pending
{#if request.status === 'pending'}
  <button on:click={() => confirmDelete(request)}>Delete</button>
{/if}
```

**New State:**
```javascript
// Delete button muncul di semua status kecuali completed
{#if request.status !== 'completed'}
  <button on:click={() => confirmDelete(request)}>Delete</button>
{/if}
```

**Status Matrix:**
| Status | Can Delete? | Reasoning |
|--------|-------------|-----------|
| `pending` | ✅ Yes | Data baru, belum direview |
| `reviewed` | ✅ Yes | Sedang direview, masih bisa dibatalkan |
| `approved` | ✅ Yes | Disetujui tapi belum diproses ke pengiriman |
| `rejected` | ✅ Yes | Sudah ditolak, bisa dihapus untuk clean up |
| `processed` | ✅ Yes | Sudah jadi pengiriman tapi belum selesai |
| `completed` | ❌ No | **Data historis, harus dijaga integritas** |

**Safety Features:**
- ✅ Confirmation dialog sebelum delete
- ✅ Backend validation (prevent delete completed)
- ✅ Auto-delete associated files (foto santri, foto lembaga, dll)
- ✅ Transaction safety (rollback jika gagal)
- ✅ Audit log deletion activity

**Deliverables:**
- ✅ Update frontend kondisi delete button
- ✅ Backend validation prevent delete completed
- ✅ File cleanup on delete
- ✅ Success/error notification

---

### **Feature 3: Edit Jumlah Mushaf & Approval Workflow** ✏️

#### **Business Problem:**
Saat ini jika lembaga mengajukan 100 mushaf tapi tim hanya bisa setujui 75, maka:
1. Data asli yang diajukan hilang (overwrite)
2. User tidak bisa lihat perbedaan di tracking page
3. Tidak ada transparansi approval decision

#### **Solution:**
Sistem tracking terpisah untuk "Jumlah Diajukan" vs "Jumlah Disetujui" dengan transparansi penuh di tracking page.

#### **Technical Specifications:**

**Database Schema Enhancement:**

**New Columns:**
```sql
jumlah_mushaf_approved INT NULL COMMENT 'Total mushaf yang disetujui tim'
jumlah_mushaf_a5_approved INT DEFAULT 0 COMMENT 'Jumlah A5 yang disetujui'
jumlah_mushaf_a6_approved INT DEFAULT 0 COMMENT 'Jumlah A6 yang disetujui'
jumlah_iqra_approved INT DEFAULT 0 COMMENT 'Jumlah IQRA yang disetujui'
catatan_perubahan_jumlah TEXT NULL COMMENT 'Alasan perubahan jumlah'
```

**Existing Columns (Preserved):**
```sql
jumlah_mushaf INT -- Tetap simpan yang DIAJUKAN
jumlah_mushaf_a5 INT -- Tetap simpan yang DIAJUKAN
jumlah_mushaf_a6 INT -- Tetap simpan yang DIAJUKAN
jumlah_iqra INT -- Tetap simpan yang DIAJUKAN
```

**Data Flow Example:**

**Scenario A: Lembaga mengajukan melalui website**
```yaml
Input Website:
  - jumlah_mushaf_a5: 50
  - jumlah_mushaf_a6: 30
  - jumlah_iqra: 20
  - Total: 100

Database Initial:
  - jumlah_mushaf_a5: 50  # REQUESTED
  - jumlah_mushaf_a6: 30  # REQUESTED
  - jumlah_iqra: 20       # REQUESTED
  - jumlah_mushaf_a5_approved: null  # Belum direview
  - jumlah_mushaf_a6_approved: null
  - jumlah_iqra_approved: null
```

**Scenario B: Admin approve dengan jumlah berbeda**
```yaml
Admin Decision:
  - Setujui A5: 40 (turun dari 50)
  - Setujui A6: 25 (turun dari 30)
  - Setujui IQRA: 10 (turun dari 20)
  - Total Disetujui: 75 (turun dari 100)
  - Catatan: "Disesuaikan dengan stok tersedia saat ini"

Database After Approval:
  # Original request (PRESERVED)
  - jumlah_mushaf_a5: 50
  - jumlah_mushaf_a6: 30
  - jumlah_iqra: 20

  # Approved quantities (NEW)
  - jumlah_mushaf_a5_approved: 40
  - jumlah_mushaf_a6_approved: 25
  - jumlah_iqra_approved: 10
  - catatan_perubahan_jumlah: "Disesuaikan dengan stok tersedia saat ini"
```

**Scenario C: Import Excel dengan jumlah berbeda**
```yaml
Excel Input:
  - jumlah_kebutuhan_mushaf: 100  # Yang diajukan
  - jumlah_digenapkan: 75         # Yang disetujui

Auto-Mapping:
  # Requested (default A5)
  - jumlah_mushaf_a5: 100
  - jumlah_mushaf_a6: 0
  - jumlah_iqra: 0

  # Approved (karena berbeda)
  - jumlah_mushaf_a5_approved: 75
  - jumlah_mushaf_a6_approved: 0
  - jumlah_iqra_approved: 0
  - catatan_perubahan_jumlah: "Jumlah disesuaikan dari 100 menjadi 75 (import XLSX)"
```

**User Interface - Admin Page:**

**Edit Modal:**
```
┌─────────────────────────────────────────────────────┐
│  Edit Jumlah Mushaf yang Disetujui                 │
├─────────────────────────────────────────────────────┤
│                                                     │
│  📋 Jumlah yang Diajukan Lembaga:                  │
│  ┌───────────────────────────────────────────────┐ │
│  │  A5: 50  │  A6: 30  │  IQRA: 20  │ Total: 100│ │
│  └───────────────────────────────────────────────┘ │
│                                                     │
│  ✅ Jumlah yang Disetujui Tim:                     │
│  ┌───────────────────────────────────────────────┐ │
│  │  A5: [40 ▼]  A6: [25 ▼]  IQRA: [10 ▼]        │ │
│  │  Total: 75                                     │ │
│  └───────────────────────────────────────────────┘ │
│                                                     │
│  Catatan Perubahan:                                │
│  ┌───────────────────────────────────────────────┐ │
│  │ [Disesuaikan dengan stok tersedia saat ini   ]│ │
│  └───────────────────────────────────────────────┘ │
│                                                     │
│              [Batal]  [Simpan Perubahan]           │
└─────────────────────────────────────────────────────┘
```

**Public Tracking Page:**

**Before Enhancement (Current):**
```
┌─────────────────────────────────────┐
│  Detail Permintaan                  │
├─────────────────────────────────────┤
│  Total Mushaf: 75                   │
│  - A5: 40                           │
│  - A6: 25                           │
│  - IQRA: 10                         │
└─────────────────────────────────────┘
❌ User tidak tahu yang diajukan 100 tapi yang disetujui 75
```

**After Enhancement (New):**
```
┌─────────────────────────────────────────────────────────┐
│  Detail Jumlah Mushaf                                   │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  📋 Yang Diajukan:          ✅ Yang Disetujui:         │
│  ┌─────────────────────┐   ┌─────────────────────┐    │
│  │ A5:    50           │   │ A5:    40           │    │
│  │ A6:    30           │   │ A6:    25           │    │
│  │ IQRA:  20           │   │ IQRA:  10           │    │
│  │ ─────────────────── │   │ ─────────────────── │    │
│  │ Total: 100          │   │ Total: 75           │    │
│  └─────────────────────┘   └─────────────────────┘    │
│                                                         │
│  ⚠️  Informasi Penyesuaian Jumlah                      │
│  ┌───────────────────────────────────────────────────┐ │
│  │ Disesuaikan dengan stok tersedia saat ini         │ │
│  └───────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────┘
✅ User bisa lihat transparansi penuh
```

**Features:**
- ✅ Preserve original request (data historis terjaga)
- ✅ Separate approval quantities (editable)
- ✅ Side-by-side comparison di tracking page
- ✅ Catatan perubahan jumlah (transparansi)
- ✅ Visual differentiation (warna berbeda)
- ✅ Auto-calculation totals
- ✅ Admin can edit anytime (flexible)

**Deliverables:**
- ✅ Database migration (4 new columns)
- ✅ Model updates (accessors & mutators)
- ✅ Admin edit modal UI
- ✅ Public tracking page enhancement
- ✅ Import integration (auto-mapping)
- ✅ API endpoint for update quantities
- ✅ Validation rules
- ✅ Documentation

---

## 💰 PRICING BREAKDOWN

### **Development Cost:**

| Item | Description | Hours | Rate | Subtotal |
|------|-------------|-------|------|----------|
| **1. Database Schema Design** | Migration files, model updates, rollback strategy | 2h | Rp 200.000 | Rp 400.000 |
| **2. Import Feature - Backend** | Import class, validation, auto-mapping, error handling | 3h | Rp 200.000 | Rp 600.000 |
| **2. Import Feature - Frontend** | Upload UI, template download, progress indicator | 1h | Rp 200.000 | Rp 200.000 |
| **3. Delete Enhancement** | Frontend condition update, backend validation, file cleanup | 0.5h | Rp 200.000 | Rp 100.000 |
| **4. Edit Jumlah - Backend** | API endpoint, validation, business logic | 2h | Rp 200.000 | Rp 400.000 |
| **4. Edit Jumlah - Frontend** | Edit modal, form handling, validation UI | 2h | Rp 200.000 | Rp 400.000 |
| **5. Tracking Page Update** | Side-by-side comparison UI, responsive design | 1.5h | Rp 200.000 | Rp 300.000 |
| **6. Excel Template Creation** | Professional template dengan instruksi | 0.5h | Rp 200.000 | Rp 100.000 |
| **7. Testing & QA** | Unit tests, integration tests, manual testing | 2h | Rp 200.000 | Rp 400.000 |
| **8. Documentation** | User manual, technical docs, update CLAUDE.md | 0.5h | Rp 200.000 | Rp 100.000 |
| **9. Deployment Support** | Migration execution, data backup, rollback plan | 1h | Rp 200.000 | Rp 200.000 |
| | | | | |
| **Subtotal Development** | | **16h** | | **Rp 3.200.000** |

### **Additional Services (Optional):**

| Item | Description | Cost |
|------|-------------|------|
| **Data Migration** | Migrate existing mushaf requests to new schema (if needed) | Rp 500.000 |
| **Training Session** | Online training untuk CS team (2 jam) | Rp 400.000 |
| **Extended Support** | 1 bulan support setelah deployment | Rp 1.000.000 |

### **Total Investment:**

| Package | Items Included | Total |
|---------|----------------|-------|
| **Basic Package** | Development only (all 3 features) | **Rp 3.200.000** |
| **Standard Package** | Development + Data Migration + Training | **Rp 4.100.000** |
| **Premium Package** | Development + Data Migration + Training + Extended Support | **Rp 5.100.000** |

---

## 📅 TIMELINE

### **Development Phases:**

```
Week 1: Foundation & Core Development
├─ Day 1-2: Database schema + Model updates
├─ Day 3-4: Import feature implementation
└─ Day 5: Delete enhancement + Testing

Week 2: Advanced Features & Polish
├─ Day 1-2: Edit Jumlah feature (backend + frontend)
├─ Day 3: Tracking page update
├─ Day 4: Integration testing
└─ Day 5: Documentation + Deployment

Total Duration: 10 working days (2 weeks)
```

**Milestone Deliveries:**

| Milestone | Deliverable | ETA | Payment |
|-----------|-------------|-----|---------|
| **M1: Database Ready** | Migration files, model updates, tested rollback | Day 2 | 20% |
| **M2: Import Complete** | Import feature fully functional with template | Day 5 | 30% |
| **M3: All Features Done** | All 3 features implemented & tested | Day 8 | 30% |
| **M4: Production Deploy** | Live deployment, documentation complete | Day 10 | 20% |

---

## ✅ ACCEPTANCE CRITERIA

### **Feature 1: Import Excel**
- [ ] Can upload .xlsx/.xls file max 10MB
- [ ] Successfully import 100+ records in under 1 minute
- [ ] Auto-generate unique request numbers without collision
- [ ] Auto-extract coordinates from Google Maps links (3 format variants)
- [ ] Display clear error messages per row if validation fails
- [ ] Show success summary: "X berhasil, Y gagal"
- [ ] Template file available for download
- [ ] All imported data appears in admin list immediately

### **Feature 2: Delete Enhancement**
- [ ] Delete button visible for status: pending, reviewed, approved, rejected, processed
- [ ] Delete button HIDDEN for status: completed
- [ ] Confirmation dialog appears before delete
- [ ] Backend prevents delete of completed requests (even via API)
- [ ] Associated files (foto, documents) deleted automatically
- [ ] Success notification after successful delete
- [ ] Data removed from list immediately

### **Feature 3: Edit Jumlah & Tracking**
- [ ] Admin can edit approved quantities from detail page
- [ ] Original requested quantities preserved (never overwritten)
- [ ] Edit modal shows side-by-side comparison
- [ ] Total auto-calculates when changing breakdown
- [ ] Catatan perubahan field available & saved
- [ ] Public tracking page shows both "Diajukan" and "Disetujui"
- [ ] Visual distinction (different colors) for requested vs approved
- [ ] Warning notice if quantities differ
- [ ] Import auto-maps "jumlah_digenapkan" to approved fields

---

## 🎁 BONUS FEATURES (No Extra Charge)

Jika project selesai lebih cepat dari timeline, kami akan include:

1. ✨ **Batch Edit Quantities** - Edit multiple requests at once
2. ✨ **Export with Approval Data** - Excel export include both requested & approved
3. ✨ **Auto-Notification** - WhatsApp notification when quantity changed (if WA feature exists)
4. ✨ **Import History Log** - Track who imported what and when
5. ✨ **Import Preview** - Preview data before actually importing

---

## 📋 TERMS & CONDITIONS

### **Payment Terms:**
- **Method:** Bank Transfer / E-Wallet
- **Schedule:**
  - 30% Down Payment (before work starts)
  - 30% at M2 completion (Import feature done)
  - 20% at M3 completion (All features done)
  - 20% at M4 completion (Production deployment)

### **Warranty & Support:**
- **Bug Fix Period:** 30 days free bug fixes after deployment
- **Scope:** Bugs related to the 3 implemented features only
- **Response Time:** Max 24 hours for critical bugs
- **Extended Support:** Available at Rp 1.000.000/month (optional)

### **Revision Policy:**
- **Major Scope Changes:** Will be quoted separately
- **Minor UI Tweaks:** Up to 2 rounds of revisions included
- **Additional Features:** Will require new quotation

### **Cancellation Policy:**
- **Before M1:** Full refund minus 10% admin fee
- **After M1:** Prorated refund based on completed milestones
- **After M3:** No refund (but will deliver what's completed)

### **Source Code & IP:**
- Client owns all source code after final payment
- Developer retains right to use anonymized project as portfolio
- No confidential business data will be shared

### **Dependencies & Requirements:**
- Client provides staging/production server access
- Client provides database backup before migration
- Client CS team available for UAT testing
- Client provides sample Excel data for testing (minimum 50 records)

---

## 📞 CONTACT & NEXT STEPS

### **To Proceed:**

1. **Review & Approve** this quotation
2. **Sign Agreement** (digital signature accepted)
3. **Transfer Down Payment** (30%)
4. **Kick-off Meeting** (schedule within 2 days after payment)
5. **Start Development** (Day 1 begins after kick-off)

### **Questions?**

**Project Contact:**
📧 Email: [developer-email]
📱 WhatsApp: [developer-phone]
🕐 Available: Mon-Fri, 09:00-17:00 WIB

**Expected Response Time:** Within 4 business hours

---

## 🔒 CONFIDENTIALITY

This quotation and all associated technical documentation are confidential and intended solely for Ekspedisi Quran team. Unauthorized distribution is prohibited.

---

## ✍️ APPROVAL

**Client Approval:**

```
Company: Ekspedisi Quran
Name: _______________________________
Position: ____________________________
Signature: ___________________________
Date: _______________________________
```

**Developer:**

```
Name: [Developer Name]
Signature: ___________________________
Date: _______________________________
```

---

**Document Version:** 1.0
**Last Updated:** 3 Oktober 2025
**Document ID:** QT-MUSHAF-ENH-2025-001

---

## 📎 APPENDIX

### **A. Technical Stack**
- Backend: Laravel 12 (PHP 8.4.11)
- Frontend: Svelte 4 + Inertia.js v2
- Database: MySQL 8.0+
- Excel: Maatwebsite Excel v3.1
- Server: Laravel Herd (Development) / Production TBD

### **B. Sample Data Structure**

**Excel Import Sample:**
```csv
nama_lembaga,nama_penanggung_jawab_1,nomor_hp_penanggung_jawab_1,alamat_lengkap,link_gmaps,jumlah_kebutuhan_mushaf,jumlah_digenapkan,urgensi
"Pondok Pesantren Al-Hikmah","Ustadz Ahmad","081234567890","Jl. Sudirman No. 123, Bandung, Jawa Barat","https://maps.google.com/?q=-6.9175,107.6191",100,75,"Sangat Mendesak"
"Masjid Al-Falah","H. Budi Santoso","081234567891","Jl. Merdeka No. 45, Jakarta Pusat","https://goo.gl/maps/abc123",50,50,"Mendesak"
```

### **C. Database Schema Preview**

**Before:**
```sql
mushaf_requests:
  - jumlah_mushaf INT
  - jumlah_mushaf_a5 INT
  - jumlah_mushaf_a6 INT
  - jumlah_iqra INT
```

**After:**
```sql
mushaf_requests:
  # Original (Requested)
  - jumlah_mushaf INT
  - jumlah_mushaf_a5 INT
  - jumlah_mushaf_a6 INT
  - jumlah_iqra INT

  # New (Approved)
  - jumlah_mushaf_approved INT NULL
  - jumlah_mushaf_a5_approved INT DEFAULT 0
  - jumlah_mushaf_a6_approved INT DEFAULT 0
  - jumlah_iqra_approved INT DEFAULT 0
  - catatan_perubahan_jumlah TEXT NULL
```

---

**END OF QUOTATION**

*Thank you for considering our proposal. We look forward to enhancing your Mushaf Request management system!* 🚀
