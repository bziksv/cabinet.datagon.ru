<?php


namespace App\Classes\Monitoring;


use App\User;
use Carbon\Carbon;

class PositionLimit extends Limits
{
    private $name = "monitoring";

    /**
     * @param User|int $user
     */
    public function __construct($user)
    {
        if (! $user instanceof User) {
            $user = User::find($user);
        }
        $this->user = $user;

        $tariff = $user->tariff()->getAsArray();

        if(isset($tariff['settings'][$this->name])){
            $settings = $tariff['settings'][$this->name];
            $this->limit = $settings['value'];
        }

        $this->date = Carbon::now()->format($this->dateFormat);
    }

}
