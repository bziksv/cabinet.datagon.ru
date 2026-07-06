<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\PromoCode;
use App\User;
use App\Services\Finance\FinanceAdminService;
use App\Services\Finance\PromoCodeService;

$user = User::where('email', 'sv6@list.ru')->firstOrFail();
$admin = User::findOrFail(4);
$promos = app(PromoCodeService::class);
$finance = app(FinanceAdminService::class);

echo 'User #' . $user->id . ' balance=' . $user->balance . PHP_EOL;

$fixed = PromoCode::firstOrCreate(['code' => 'TEST500'], [
    'title' => 'Test fixed 500',
    'bonus_type' => 'fixed',
    'bonus_value' => 500,
    'usage_mode' => 'once',
    'is_active' => true,
    'created_by' => 4,
]);
$percent = PromoCode::firstOrCreate(['code' => 'TEST10P'], [
    'title' => 'Test 10 percent',
    'bonus_type' => 'percent',
    'bonus_value' => 10,
    'usage_mode' => 'multi',
    'is_active' => true,
    'created_by' => 4,
]);

$p1 = $promos->previewForUser($user, 'TEST500', 1000);
echo 'Preview TEST500: valid=' . (int) $p1['valid'] . ' bonus=' . $p1['bonus_sum'] . ' total=' . $p1['total_sum'] . PHP_EOL;

$before = (int) $user->fresh()->balance;
$b1 = $finance->simulateTopUp($user, 1000, 'TEST500', $admin, $promos);
$after = (int) $user->fresh()->balance;
echo 'Simulate TEST500: paid=' . $b1->paid_sum . ' bonus=' . $b1->bonus_sum . ' credited=' . $b1->sum . ' delta=' . ($after - $before) . PHP_EOL;

$p2 = $promos->previewForUser($user->fresh(), 'TEST500', 1000);
echo 'Preview TEST500 again: valid=' . (int) $p2['valid'] . ' msg=' . $p2['message'] . PHP_EOL;

$before2 = (int) $user->fresh()->balance;
$b2 = $finance->simulateTopUp($user->fresh(), 2000, 'TEST10P', $admin, $promos);
$after2 = (int) $user->fresh()->balance;
echo 'Simulate TEST10P 2000: bonus=' . $b2->bonus_sum . ' credited=' . $b2->sum . ' delta=' . ($after2 - $before2) . PHP_EOL;

$b3 = $finance->simulateTopUp($user->fresh(), 1000, 'TEST10P', $admin, $promos);
echo 'Simulate TEST10P 1000 again: bonus=' . $b3->bonus_sum . ' credited=' . $b3->sum . PHP_EOL;

echo 'Uses TEST500=' . $fixed->fresh()->uses_count . ' TEST10P=' . $percent->fresh()->uses_count . PHP_EOL;
