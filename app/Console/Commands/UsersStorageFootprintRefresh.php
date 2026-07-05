<?php

namespace App\Console\Commands;

use App\Support\UserStorageFootprintService;
use Illuminate\Console\Command;

class UsersStorageFootprintRefresh extends Command
{
    protected $signature = 'users:storage-footprint {--user= : Пересчитать одного пользователя} {--chunk=100 : Размер пачки для полного прогона}';

    protected $description = 'Пересчёт объёма данных пользователей в БД (кэш для /users)';

    public function handle(): int
    {
        $userId = $this->option('user');
        if ($userId !== null && $userId !== '') {
            $payload = UserStorageFootprintService::computeForUser((int) $userId);
            $this->line('User #' . (int) $userId . ': ' . UserStorageFootprintService::formatBrief($payload));

            return 0;
        }

        $chunk = (int) $this->option('chunk');
        $this->info('Полный пересчёт снимков объёма…');
        $result = UserStorageFootprintService::refreshAll($chunk);
        $this->info('Готово: ' . $result['users'] . ' пользователей, ошибок: ' . $result['errors']);

        return $result['errors'] > 0 ? 1 : 0;
    }
}
