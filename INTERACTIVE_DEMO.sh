#!/bin/bash

# 🎯 Laravel Test Türleri - İnteraktif Demo Script
# Bu script sunum sırasında kullanılacak komutları içerir

echo "🚀 Laravel Test Türleri Demo'ya Hoş Geldiniz!"
echo "=============================================="
echo ""

# 1. Giriş - Test Coverage Raporu
echo "📊 1. Test Coverage Raporu Gösterimi"
echo "-------------------------------------"
echo "Mevcut test coverage raporumuzu görelim:"
echo ""

# Coverage raporu oluştur
php artisan test --coverage-html coverage

echo ""
echo "✅ Coverage raporu oluşturuldu: coverage/index.html"
echo ""

# 2. Unit Test Demo
echo "🧪 2. Unit Test Demo - Post Model Slug Testi"
echo "---------------------------------------------"
echo "Post model'inin slug oluşturma özelliğini test edelim:"
echo ""

# Test'i çalıştır
echo "🔍 Test çalıştırılıyor..."
php artisan test tests/Unit/PostTest.php::test_post_generates_slug_automatically

echo ""
echo "✅ Test başarılı! Şimdi model'i bozup test'in hata verdiğini görelim:"
echo ""

# Model'i geçici olarak boz (demo için)
echo "⚠️  Post model'inde generateSlug method'unu geçici olarak bozuyoruz..."
cp app/Models/Post.php app/Models/Post.php.backup

# Method'u geçici olarak değiştir
sed -i '' 's/return Str::slug($this->title);/return "broken-slug";/' app/Models/Post.php

echo "🔍 Bozulan model ile test çalıştırılıyor..."
php artisan test tests/Unit/PostTest.php::test_post_generates_slug_automatically

echo ""
echo "❌ Test hata verdi! Şimdi düzeltelim:"
echo ""

# Düzelt
echo "🔧 Model düzeltiliyor..."
cp app/Models/Post.php.backup app/Models/Post.php

echo "🔍 Düzeltilen model ile test tekrar çalıştırılıyor..."
php artisan test tests/Unit/PostTest.php::test_post_generates_slug_automatically

echo ""
echo "✅ Test tekrar başarılı!"
echo ""

# 3. Feature Test Demo
echo "🎮 3. Feature Test Demo - Post Controller Create Testi"
echo "------------------------------------------------------"
echo "Post oluşturma endpoint'ini test edelim:"
echo ""

# Test'i çalıştır
echo "🔍 Test çalıştırılıyor..."
php artisan test tests/Feature/PostControllerTest.php::test_authenticated_user_can_create_post

echo ""
echo "✅ Test başarılı! Şimdi validation kurallarını bozup test'in hata verdiğini görelim:"
echo ""

# Controller'ı geçici olarak boz (demo için)
echo "⚠️  PostController'da validation kurallarını geçici olarak bozuyoruz..."
cp app/Http/Controllers/PostController.php app/Http/Controllers/PostController.php.backup

# Validation'ı geçici olarak değiştir
sed -i '' 's/required|string|max:255/required|string|max:1/' app/Http/Controllers/PostController.php

echo "🔍 Bozulan controller ile test çalıştırılıyor..."
php artisan test tests/Feature/PostControllerTest.php::test_authenticated_user_can_create_post

echo ""
echo "❌ Test hata verdi! Şimdi düzeltelim:"
echo ""

# Düzelt
echo "🔧 Controller düzeltiliyor..."
cp app/Http/Controllers/PostController.php.backup app/Http/Controllers/PostController.php

echo "🔍 Düzeltilen controller ile test tekrar çalıştırılıyor..."
php artisan test tests/Feature/PostControllerTest.php::test_authenticated_user_can_create_post

echo ""
echo "✅ Test tekrar başarılı!"
echo ""

# 4. Performance Test Demo
echo "⚡ 4. Performance Test Demo - Query Performance Testi"
echo "----------------------------------------------------"
echo "Query performansını test edelim:"
echo ""

# Test'i çalıştır
echo "🔍 Test çalıştırılıyor..."
php artisan test tests/Feature/PerformanceTest.php::test_posts_index_query_performance

echo ""
echo "✅ Test başarılı! Şimdi eager loading'i kaldırıp N+1 query problem'ini görelim:"
echo ""

# Controller'da eager loading'i geçici olarak kaldır
echo "⚠️  PostController'da eager loading geçici olarak kaldırılıyor..."
cp app/Http/Controllers/PostController.php app/Http/Controllers/PostController.php.backup2

# Eager loading'i kaldır
sed -i '' 's/with.*user.*comments.*//' app/Http/Controllers/PostController.php

echo "🔍 Eager loading olmadan test çalıştırılıyor..."
php artisan test tests/Feature/PerformanceTest.php::test_posts_index_query_performance

echo ""
echo "❌ Test hata verdi! N+1 query problem'i tespit edildi. Şimdi düzeltelim:"
echo ""

# Düzelt
echo "🔧 Controller düzeltiliyor..."
cp app/Http/Controllers/PostController.php.backup2 app/Http/Controllers/PostController.php

echo "🔍 Düzeltilen controller ile test tekrar çalıştırılıyor..."
php artisan test tests/Feature/PerformanceTest.php::test_posts_index_query_performance

echo ""
echo "✅ Test tekrar başarılı!"
echo ""

# 5. Error Handling Test Demo
echo "🚨 5. Error Handling Test Demo - Validation Error Testi"
echo "-------------------------------------------------------"
echo "Validation error handling'i test edelim:"
echo ""

# Test'i çalıştır
echo "🔍 Test çalıştırılıyor..."
php artisan test tests/Feature/ErrorHandlingTest.php::test_validation_errors_return_proper_format

echo ""
echo "✅ Test başarılı!"
echo ""

# 6. Yeni Test Yazma Demo
echo "✍️  6. Yeni Test Yazma Demo - User Full Name Testi"
echo "---------------------------------------------------"
echo "User model'inde yeni bir method test edelim:"
echo ""

# Test'i çalıştır
echo "🔍 Yeni test çalıştırılıyor..."
php artisan test tests/Unit/UserTest.php::test_user_full_name_attribute

echo ""
echo "✅ Yeni test başarılı!"
echo ""

# 7. Final Test Run
echo "🎯 7. Final Test Run - Tüm Testler"
echo "----------------------------------"
echo "Son olarak tüm testleri çalıştıralım:"
echo ""

# Tüm testleri çalıştır
php artisan test

echo ""
echo "🎉 Demo tamamlandı!"
echo "📊 Coverage raporu: coverage/index.html"
echo "📝 Sunum script'i: SUNUM_DEMO_SCRIPT.md"
echo ""
echo "Teşekkürler! 🚀" 