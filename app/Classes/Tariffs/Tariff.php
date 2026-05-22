<?php


namespace App\Classes\Tariffs;


use App\Classes\Tariffs\Interfaces\Period;
use App\Classes\Tariffs\Interfaces\Settings;
use App\User;

abstract class Tariff
{
    protected $responses;
    protected $price = 0;
    protected $period;
    protected $user = null;
    protected $priceBootstrapped = false;

    public function __construct(Period $period)
    {
        $this->period = $period;
    }

    abstract public function name(): string;

    abstract public function code(): string;

    public function price($field = null)
    {
        $period = $this->period->setPrice($this->priceForDay());

        $collection = collect([
            'price' => $period->price(),
            'priceWithDiscount' => $period->total(),
            'percent' => $period->percent(),
            'discount' => $period->discount(),
        ]);

        if(!is_null($field = $collection->get($field)))
            return $field;
        else
            return $collection;
    }

    public function priceForDay()
    {
        $this->bootstrapPriceFromSettings();

        return $this->price;
    }

    /**
     * Раньше вызывалось в __construct() каждого тарифа → 3× N+1 к tariff_settings на любой странице.
     */
    protected function bootstrapPriceFromSettings(): void
    {
        if ($this->priceBootstrapped) {
            return;
        }

        $settings = $this->settings()->get();
        if (array_key_exists('price', $settings)) {
            $this->price = (int) $settings['price']['value'];
        }

        $this->priceBootstrapped = true;
    }

    abstract protected function settings(): Settings;

    public function getAsArray()
    {
        $this->responses['name'] = $this->name();

        $settings = $this->settings();
        $this->responses['settings'] = $settings->get();

        return $this->responses;
    }

    public function setUser($user): void
    {
        $this->user = $user;
    }

    public function setPeriod(Period $period)
    {
        $this->period = $period;
    }

    public function getPeriod()
    {
        return $this->period;
    }

    public function assignRoleByUser(User $user)
    {
        $roles = $user->getRoleNames();

        foreach ($roles as $role){
            if($role === 'user')
                continue;

            $user->removeRole($role);
        }

        $user->assignRole($this->code());
    }

    public function assignRole()
    {
        $user = auth()->user();

        $user->removeRole('Free');

        $user->assignRole($this->code());
    }

    public function removeRole()
    {
        $user = auth()->user();

        $user->removeRole($this->code());

        $user->assignRole('Free');
    }
}
