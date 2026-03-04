# ✅ Dayli Push Notifications — Full Process (First → Last)  
**Stack:** Flutter (Android) + Laravel + Firebase FCM HTTP v1 + Queue (Windows)

> This is the *complete* end-to-end process we did:  
> Firebase setup → Flutter token → Laravel save token → Laravel send push (HTTP v1) → Queue job dispatch → Test + DB checks + common errors.

---

## 0) Firebase Console (ONE TIME)

### 0.1 Create Firebase Project
1. Open Firebase Console
2. Click **Create a project**
3. Enter project name (ex: `Dayli App`)
4. Continue → (Google Analytics optional) → Create

### 0.2 Add Android App
1. Firebase project → Click **Android icon**
2. Enter **Android package name** (MUST match Flutter `applicationId`)
   - In Flutter project: `android/app/build.gradle`  
     Find:
     ```gradle
     defaultConfig {
       applicationId = "com.example.dayli"
     }
     ```
   - Copy the exact value and paste into Firebase.
3. Register app
4. Download `google-services.json`
5. Put it here:

dayli-mob/android/app/google-services.json


### 0.3 Add iOS App
1. Firebase project → Click **iOS icon**
2. Enter **Bundle ID** (Runner bundle id in Xcode)
3. Register app
4. Download `GoogleService-Info.plist`
5. Put it here:

dayli-mob/ios/Runner/GoogleService-Info.plist


### 0.4 Create Service Account JSON (SERVER ONLY)
1. Firebase → Project settings → **Service Accounts**
2. Generate new private key JSON
3. Store JSON only in server / Laravel machine (never in Flutter)
4. FCM HTTP v1 endpoint format:

https://fcm.googleapis.com/v1/projects/{projectId}/messages:send


---

## 1) Flutter Side (Generate token + print token + send token to backend)

### 1.1 Add packages
```bash
cd C:\Users\mandl\work\dayli-mob
flutter pub add firebase_core firebase_messaging
dart pub global activate flutterfire_cli
1.2 If flutterfire command not found (Windows)

FlutterFire installs here:

C:\Users\mandl\AppData\Local\Pub\Cache\bin

Run it via full path:

C:\Users\mandl\AppData\Local\Pub\Cache\bin\flutterfire.bat configure
1.3 Firebase CLI install & login (required by flutterfire)

If flutterfire says: "requires Firebase CLI to be installed"
Install Firebase CLI (Node required):

npm install -g firebase-tools
firebase --version

If you get:
firebase.ps1 cannot be loaded because running scripts is disabled

Run (no admin required):

Set-ExecutionPolicy -Scope CurrentUser RemoteSigned

Then:

firebase login
1.4 Run flutterfire configure
C:\Users\mandl\AppData\Local\Pub\Cache\bin\flutterfire.bat configure

Select:

Project: dayli-app (Dayli App)

Platforms: ✅ android ✅ ios (only)

Expected output:

Registers android app com.example.dayli

Registers ios app com.example.dayli

Generates:

lib/firebase_options.dart
1.5 Android Gradle plugin (must)

In android/app/build.gradle ensure:

plugins {
  id("com.android.application")
  id("kotlin-android")
  id("dev.flutter.flutter-gradle-plugin")
  id("com.google.gms.google-services")
}

android {
  namespace = "com.example.dayli"
  defaultConfig {
    applicationId = "com.example.dayli"
  }
}
1.6 main.dart (your file) — add Firebase + token print

You already have EasyLocalization. Keep it.
Add Firebase initialization + token print.

Example pattern (merge into your main):

import firebase_core + firebase_messaging

call Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform)

print token

Expected terminal output:

FCM TOKEN: d5dJx4wyQyyPz_...

✅ You already got the token and confirmed it in console.

1.7 IMPORTANT: token only on phone

✅ Android phone (real device) -> works

❌ Windows desktop -> no mobile push token

❌ Web (chrome/edge) -> needs web push setup, not this flow

2) Laravel Side (Store device tokens)
2.1 Migration (device_tokens)

Create migration:

cd C:\Users\mandl\work\dayli-api
php artisan make:migration create_device_tokens_table

Migration content:

Schema::create('device_tokens', function (Blueprint $table) {
  $table->id();
  $table->foreignId('user_id')->nullable()->index();
  $table->string('token')->unique();
  $table->string('platform', 20)->nullable(); // android|ios|web
  $table->string('device_id', 100)->nullable();
  $table->string('app_version', 50)->nullable();
  $table->timestamp('last_seen_at')->nullable();
  $table->boolean('is_valid')->default(true)->index();
  $table->timestamps();
});

Run migration:

php artisan migrate
2.2 Model

app/Models/DeviceToken.php

class DeviceToken extends Model
{
  protected $fillable = [
    'user_id','token','platform','device_id','app_version','last_seen_at','is_valid'
  ];

  protected $casts = [
    'last_seen_at' => 'datetime',
    'is_valid' => 'boolean',
  ];
}
2.3 API Route

In routes/api.php inside auth:sanctum group:

Route::post('/device-tokens', [DeviceTokenController::class, 'store']);

Verify route exists:

php artisan route:list | findstr device-tokens

Expected:

POST  api/device-tokens  Api\DeviceTokenController@store
2.4 Controller

app/Http/Controllers/Api/DeviceTokenController.php

public function store(Request $request)
{
  $user = $request->user();

  $data = $request->validate([
    'token' => ['required','string','max:4096'],
    'platform' => ['nullable','string','max:20'],
    'device_id' => ['nullable','string','max:100'],
    'app_version' => ['nullable','string','max:50'],
  ]);

  DeviceToken::updateOrCreate(
    ['token' => $data['token']],
    [
      'user_id' => $user->id,
      'platform' => $data['platform'] ?? null,
      'device_id' => $data['device_id'] ?? null,
      'app_version' => $data['app_version'] ?? null,
      'last_seen_at' => now(),
      'is_valid' => true,
    ]
  );

  return response()->json(['ok' => true]);
}
3) Postman Test (Save device token)
3.1 Start Laravel server
php artisan serve --host=localhost --port=8000
3.2 Get Sanctum token (OTP verify)

Send OTP
POST:

http://localhost:8000/api/auth/send-otp

Body:

{
  "phone": "YOUR_PHONE"
}

Response example:

{
  "message": "OTP sent",
  "otp": "547352"
}

Verify OTP
POST:

http://localhost:8000/api/auth/verify-otp

Body:

{
  "phone": "YOUR_PHONE",
  "otp": "547352"
}

Response contains:

{
  "token": "1108|VlxIsJNGMA5A2VwXDNIDuUv6tvhYxqW74ivvpNxD43d9d169",
  "user_id": 10916
}
3.3 Save device token

POST:

http://localhost:8000/api/device-tokens

Headers:

Accept: application/json

Content-Type: application/json

Authorization: Bearer 1108|VlxIsJNGMA5A2VwXDNIDuUv6tvhYxqW74ivvpNxD43d9d169

Body:

{
  "token": "d5dJx4wyQyyPz_X9Hg2a4X:APA91bHLwxBHqZdyd7Tk5AHbTFM8JkEVF-mmHJX941puj7LbjXNbWO-2-8JnI7AM-_TTMW6zCOcnZpENe3q1M-aN6CRxP4Hh3wJydJ8WUqieeYdpNys6BZ8",
  "platform": "android",
  "device_id": "V2401",
  "app_version": "1.0.0"
}

Expected response:

{ "ok": true }
3.4 Confirm in DB

SQL:

SELECT * FROM device_tokens ORDER BY id DESC LIMIT 5;

You confirmed row like:

1  10916  d5dJx4wy...  android  V2401  1.0.0  ...
4) Laravel Send Push (FCM HTTP v1)
4.1 Install libraries
composer require google/auth guzzlehttp/guzzle
4.2 Save service account JSON on server

Example:

C:\Users\mandl\work\dayli-api\storage\app\firebase\service-account.json

Add to .gitignore:

/storage/app/firebase/*.json
4.3 Add env

.env:

FCM_PROJECT_ID=dayli-app
FCM_SERVICE_ACCOUNT_JSON=C:\Users\mandl\work\dayli-api\storage\app\firebase\service-account.json
4.4 Add config/services.php
'fcm' => [
  'project_id' => env('FCM_PROJECT_ID'),
  'service_account_json' => env('FCM_SERVICE_ACCOUNT_JSON'),
],
4.5 Clear cache (important)
php artisan optimize:clear
4.6 Create service class

app/Services/FcmService.php

Key responsibilities:

generate OAuth access token from service account JSON

call FCM v1 endpoint

send to token

send to user (loops through all valid tokens)

(Use the exact code you already pasted earlier.)

4.7 Create test route (no body)

In routes/api.php inside auth:sanctum group:

Route::post('/push/test', function (Request $request) {
  $user = $request->user();

  $tokenRow = \App\Models\DeviceToken::where('user_id', $user->id)
    ->where('is_valid', true)
    ->latest()
    ->firstOrFail();

  $payload = [
    'title' => 'Dayli Test',
    'body'  => 'Push working ✅',
    'data'  => [
      'type' => 'test',
      'entity_id' => '0',
    ],
  ];

  $res = app(\App\Services\FcmService::class)
    ->sendToToken($tokenRow->token, $payload);

  return response()->json(['ok' => true, 'fcm' => $res]);
});
4.8 Test push in Postman (no body)

POST:

http://localhost:8000/api/push/test

Headers:

Accept: application/json

Authorization: Bearer 1108|VlxIs...

Body: EMPTY

Expected response:

{
  "ok": true,
  "fcm": {
    "name": "projects/dayli-app/messages/0:...."
  }
}

✅ You got:

projects/dayli-app/messages/...

So Laravel → Firebase push is working.

5) Queue Job (Recommended)
5.1 Why queue

Push call is network call (slow)

API should not wait

Queue retries if Firebase fails

Scales to many notifications

5.2 Set queue driver to database

.env:

QUEUE_CONNECTION=database

Create tables:

php artisan queue:table
php artisan queue:failed-table
php artisan migrate
5.3 Create job
php artisan make:job SendPushToUserJob

app/Jobs/SendPushToUserJob.php

class SendPushToUserJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public int $tries = 3;
  public array $backoff = [5, 30, 120];

  public function __construct(
    public int $userId,
    public array $payload
  ) {}

  public function handle(FcmService $fcm): void
  {
    $fcm->sendToUser($this->userId, $this->payload);
  }
}
5.4 Start worker (keep running)
php artisan queue:work --queue=ops,default --tries=3
5.5 Dispatch job anywhere (controller/service/handler)
use App\Jobs\SendPushToUserJob;

SendPushToUserJob::dispatch($userId, [
  'title' => 'Order Updated',
  'body'  => 'Your order #4995 is out for delivery',
  'data'  => [
    'type' => 'order',
    'entity_id' => '4995',
    'deeplink' => 'dayli://orders/4995',
  ],
])->onQueue('ops');
5.6 Where we add dispatch in Dayli flows

✅ In vendor save:

createOrderFromMySupplies()
Add push dispatch after order saved and before return.

✅ In delivery save:

createOrderFromMyWork()
Add push dispatch after:

$order->delivery_status = 'delivered';
$order->delivered_at = now();
$order->save();

and before return.

IMPORTANT rule: push should be dispatched only after DB transaction succeeded.

6) User must login on phone to receive push

Device tokens exist only for devices that run Firebase Messaging.

Windows desktop login won’t create mobile push token.

Customer must login on phone once so their token is stored in device_tokens.

7) Common Errors We Faced + Fix
Error: Header name must be a valid HTTP token ["Content-Type "]

✅ Fix: Remove trailing space in header key (must be exactly Content-Type)

Error: connect ECONNREFUSED 127.0.0.1:8000

✅ Fix: Start Laravel server:

php artisan serve --host=localhost --port=8000
Error: Invalid protocol: post http:

✅ Fix: In Postman URL box type ONLY:

http://localhost:8000/api/device-tokens

Do not type post http://...

Error: {"message":"Unauthenticated."}

✅ Fix: Add header:

Authorization: Bearer <token-from-verify-otp>
Accept: application/json
Content-Type: application/json
flutterfire configure not found

✅ Fix: run:

C:\Users\mandl\AppData\Local\Pub\Cache\bin\flutterfire.bat configure
Firebase CLI .ps1 cannot be loaded (scripts disabled)

✅ Fix:

Set-ExecutionPolicy -Scope CurrentUser RemoteSigned
