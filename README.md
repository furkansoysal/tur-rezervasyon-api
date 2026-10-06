# Tur Rezervasyon API

Tur ve gezi şirketleri için küçük bir rezervasyon servisi: turları ve kalkış tarihlerini listeler, kontenjan kontrolüyle rezervasyon alır, rezervasyonu sorgulatır ve iptal kuralına göre iptal eder.

PHP 8.4 ve SQLite ile yazıldı, **dış bağımlılığı yok** (Composer paketi gerekmiyor). Katmanlı yapı Laravel mantığına yakın tutuldu; amaç, aynı iş kurallarının bir framework'e kolayca taşınabilmesi.

![Demo arayüzü](docs/demo.png)

## Çalıştırma

```bash
php bin/seed.php                              # örnek turlar ve kalkış tarihleri
php -S 127.0.0.1:8000 -t public public/index.php
```

Tarayıcıda `http://127.0.0.1:8000` demo arayüzünü açar. Testler için:

```bash
php tests/run.php        # geçici bir veritabanı kullanır, 26 kontrol
```

Gereksinim: PHP 8.2+ (`pdo_sqlite`, `mbstring`).

## Uç noktalar

| Metod | Adres | Açıklama |
|---|---|---|
| GET | `/api/tours?city=&from=&to=` | Turları ve kalan koltuklu kalkışları listeler |
| GET | `/api/tours/{slug}` | Tek tur ve kalkış tarihleri |
| POST | `/api/reservations` | Rezervasyon oluşturur |
| GET | `/api/reservations/{code}` | Rezervasyonu koduyla sorgular |
| POST | `/api/reservations/{code}/cancel` | Rezervasyonu iptal eder |
| GET | `/api/health` | Servis durumu |

Örnek istek:

```bash
curl -X POST http://127.0.0.1:8000/api/reservations \
  -H 'Content-Type: application/json' \
  -d '{"tour":"pamukkale-hierapolis","date":"2026-10-13","adults":2,"children":1,
       "name":"Ayşe Yılmaz","email":"ayse@example.com","phone":"0532 123 45 67"}'
```

Hatalar `{"error": "...", "fields": {...}}` biçiminde döner: 400 bozuk JSON, 404 bulunamadı, 405 desteklenmeyen metod, 409 kontenjan/iptal çakışması, 422 doğrulama hatası.

## İş kuralları

- **Fiyat:** yetişkin ve çocuk için ayrı birim fiyat; toplam sunucuda hesaplanır, istemciden alınmaz.
- **Kontenjan:** her kalkışın kendi kapasitesi var; yetişkin + çocuk sayısı koltuktan düşer.
- **Eşzamanlılık:** kontenjan kontrolü ve kayıt tek `BEGIN IMMEDIATE` işleminde yapılır. Aynı anda gelen isteklerle denendi: 24 kişilik kalkışa 12 paralel "5 kişilik" istek atıldığında yalnızca 4'ü onaylandı, 8'i 409 aldı; kalan koltuk 4 olarak doğru göründü.
- **İptal:** kalkışa 48 saatten az kalmışsa iptal edilemez; iptal edilen koltuklar yeniden satışa açılır; aynı rezervasyon iki kez iptal edilemez.
- **Geçmiş kalkışlar** listelenmez ve rezervasyona kapalıdır.

## Yapı

```
public/index.php          giriş noktası (front controller) ve demo.html
src/Core/                 Router, Request, Response, Validator, Database
src/Services/             TourService, ReservationService (iş kuralları burada)
src/App.php               rotalar ve hata yönetimi
database/schema.sql       tablolar ve kısıtlar
bin/seed.php              örnek veri
tests/run.php             sabit saatle çalışan test seti
```

## Kapsam dışı (bilinçli)

Ödeme alma, e-posta/SMS bildirimi, kullanıcı girişi ve yönetim paneli bu sürümde yok. Gerçek bir projede ödeme adımı rezervasyonu "beklemede" tutup ödeme onayında kesinleştirecek şekilde araya eklenir.

Turlar, fiyatlar ve müşteri bilgileri tamamen örnektir.
