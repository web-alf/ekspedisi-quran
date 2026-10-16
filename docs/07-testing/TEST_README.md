# 🧪 Testing Quick Reference Guide

Quick guide untuk running dan managing tests untuk fitur Camera Capture & Documentation Upload.

---

## 🚀 **Quick Start**

### **Run All Tests**
```bash
# Backend + Frontend
./vendor/bin/pest && npm run test:run

# Or separately
php artisan test
npm run test:run
```

### **Run Specific Tests**
```bash
# Backend - Dokumentasi tests only
php artisan test tests/Unit/DokumentasiUploadTest.php
php artisan test tests/Feature/TrackingHistoryDokumentasiTest.php

# Frontend - Specific component
npm test CameraCapture.test.js
npm test DokumentasiUpload.test.js
```

### **Run with Coverage**
```bash
# Backend
php artisan test --coverage --min=80

# Frontend
npm run test:coverage

# View HTML report
open coverage/index.html
```

---

## 📊 **Test Summary**

| Type | Tests | Time | Coverage |
|------|-------|------|----------|
| Backend Unit | 18 | ~3-5s | 85%+ |
| Backend Feature | 15 | ~5-8s | 85%+ |
| Frontend Unit | 59 | ~3-5s | 85%+ |
| **Total** | **92** | **~11-18s** | **85%+** |

---

## 🎯 **Test Files**

### **Backend (PHP/Pest)**
- `tests/Unit/DokumentasiUploadTest.php` - File upload tests
- `tests/Feature/TrackingHistoryDokumentasiTest.php` - Workflow tests

### **Frontend (Vitest)**
- `tests/unit/CameraCapture.test.js` - Camera component tests
- `tests/unit/DokumentasiUpload.test.js` - Upload wrapper tests

### **Configuration**
- `vitest.config.js` - Vitest setup
- `tests/setup.js` - Mock setup
- `package.json` - Test scripts

---

## 📝 **Available Commands**

### **Backend Tests**
```bash
php artisan test                    # Run all tests
php artisan test --filter=name      # Run specific test
php artisan test --parallel         # Parallel execution
php artisan test --coverage         # With coverage
php artisan test -v                 # Verbose output
```

### **Frontend Tests**
```bash
npm test                   # Watch mode (auto-rerun)
npm run test:run          # Single run
npm run test:ui           # UI mode (visual)
npm run test:coverage     # With coverage
npm test -- --run file    # Specific file
```

---

## 🐛 **Troubleshooting**

### **Backend: Role Not Found**
```php
// Add to test setUp()
$this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
```

### **Frontend: Camera API Error**
Already mocked in `tests/setup.js`. No action needed.

### **Storage Fake Not Working**
```php
// Add to test setUp()
Storage::fake('public');
```

---

## 📚 **Documentation**

- **Full Guide:** `TESTING_DOCUMENTATION.md`
- **Summary:** `TESTING_SUMMARY.md`
- **Feature Docs:** `CAMERA_DOCUMENTATION.md`

---

## ✅ **CI/CD Integration**

```yaml
# .github/workflows/tests.yml
name: Tests
on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Backend Tests
        run: php artisan test
      - name: Frontend Tests
        run: npm run test:run
```

---

## 🎓 **Quick Tips**

### **Speed Up Tests**
- Use `--parallel` for backend
- Use `--run` for frontend (skip watch)
- Filter tests during development

### **Debug Failed Tests**
```bash
# Backend - verbose output
php artisan test --filter=test_name -v

# Frontend - single test with details
npm test -- --run --reporter=verbose file.test.js
```

### **Watch Mode**
```bash
# Frontend - auto-rerun on changes
npm test

# Backend - requires pest-plugin-watch
./vendor/bin/pest --watch
```

---

**Last Updated:** 2025-10-10
**Status:** ✅ Production Ready
**Total Tests:** 92
**Coverage:** 85%+
