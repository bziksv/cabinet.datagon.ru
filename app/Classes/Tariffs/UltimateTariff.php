<?php


namespace App\Classes\Tariffs;


use App\Classes\Tariffs\Interfaces\Settings;
use App\Classes\Tariffs\Period\ThreeMonthsTariff;
use App\Classes\Tariffs\Settings\UltimateSettings;

class UltimateTariff extends Tariff
{
    public $name = 'Ultimate';
    protected $code = 'Ultimate';

    public function __construct()
    {
        parent::__construct(new ThreeMonthsTariff());

        $this->name = __('Ultimate');
    }

    public function name(): string
    {
        return $this->name;
    }

    public function code(): string
    {
        return $this->code;
    }

    protected function settings(): Settings
    {
        return new UltimateSettings($this->code(), $this->user);
    }
}
