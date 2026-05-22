<?php

namespace App\Console\Commands;

use App\Support\MainProjectMenuIcons;
use Illuminate\Console\Command;

class ApplyMainProjectMenuIcons extends Command
{
    protected $signature = 'cabinet:apply-menu-icons';

    protected $description = 'Обновить иконки main_projects по карте MainProjectMenuIcons::MAP';

    public function handle(): int
    {
        $updated = MainProjectMenuIcons::apply();
        $this->info("Обновлено записей: {$updated}");

        return 0;
    }
}
