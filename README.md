# ⚡ VoltWatch — Laravel Backend

## Stack
- **Laravel 11** + **Sanctum** (API token auth)
- **MySQL** (primary DB) + **Redis** (cache, queues, WebSockets)
- **Laravel Reverb** (WebSockets for real-time IoT data)
- **Laravel Queues** (background jobs: alerts, reports, IoT processing)
- **Paystack** (Nigerian payment gateway for subscriptions)
- **Firebase Cloud Messaging** (push notifications via Laravel)

---

## Quick Setup

```bash
# 1. Create fresh Laravel project
composer create-project laravel/laravel voltwatch-backend
cd voltwatch-backend

# 2. Install packages
composer require laravel/sanctum
composer require laravel/reverb
composer require spatie/laravel-permission
composer require php-mqtt/laravel-mqtt
composer require kreait/laravel-firebase
composer require knuckleswtf/scribe   # API docs auto-generator

# 3. Copy all files from this repo into place

# 4. Set up .env (see .env.example below)
cp .env.example .env
php artisan key:generate

# 5. Run migrations (in order — they're numbered)
php artisan migrate

# 6. Seed initial data (tariff bands, appliance types)
php artisan db:seed

# 7. Start services
php artisan serve                    # API server
php artisan queue:work               # Background jobs
php artisan reverb:start             # WebSocket server (IoT + real-time)
```

---

## Architecture Overview

```
voltwatch-backend/
├── app/
│   ├── Models/
│   │   ├── User.php                 # Auth user
│   │   ├── Meter.php                # Prepaid meter
│   │   ├── Recharge.php             # Token recharge log
│   │   ├── Appliance.php            # User's appliances
│   │   ├── ApplianceType.php        # Seeded: AC, Fridge, Fan...
│   │   ├── UsageReading.php         # IoT real-time data points
│   │   ├── Alert.php                # Alert rules per user
│   │   ├── AlertLog.php             # Fired alert history
│   │   ├── Estate.php               # Compound/estate
│   │   ├── EstateMember.php         # Tenant → Estate mapping
│   │   ├── IoTDevice.php            # Hardware device record
│   │   ├── TariffBand.php           # Seeded: Band A-E + rates
│   │   └── Subscription.php         # User plan + Paystack ref
│   ├── Http/
│   │   ├── Controllers/API/
│   │   │   ├── AuthController.php
│   │   │   ├── MeterController.php
│   │   │   ├── RechargeController.php
│   │   │   ├── ApplianceController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── AnalyticsController.php
│   │   │   ├── AlertController.php
│   │   │   ├── EstateController.php
│   │   │   ├── IoTController.php
│   │   │   ├── SubscriptionController.php
│   │   │   └── NotificationController.php
│   │   ├── Requests/               # Form validation
│   │   ├── Resources/              # API response transformers
│   │   └── Middleware/
│   │       ├── EnsureHasActiveMeter.php
│   │       └── EnsureIoTDeviceOwner.php
│   ├── Services/
│   │   ├── ConsumptionEstimator.php # Core business logic
│   │   ├── UnitDepletionPredictor.php
│   │   ├── AlertEvaluator.php
│   │   ├── PaystackService.php
│   │   └── IoTBridgeService.php
│   ├── Jobs/
│   │   ├── EvaluateUserAlerts.php
│   │   ├── GenerateWeeklyReport.php
│   │   └── ProcessIoTReading.php
│   └── Events/
│       ├── IoTReadingReceived.php
│       └── UnitsCriticallyLow.php
├── database/
│   └── migrations/                  # 14 migration files
└── routes/
    └── api.php                      # All API routes
```

---

## API Base URL
```
https://api.voltwatchapp.com/api/v1
```

## Authentication
All protected routes require:
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```
