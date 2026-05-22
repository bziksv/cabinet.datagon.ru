<?php


namespace App\Classes\Monitoring\Widgets;

use Illuminate\Support\Collection;

class WidgetsFactory
{
    protected $widgets;

    /** @var \Illuminate\Support\Collection|null code => widget data */
    protected $resolvedByCode;

    public function __construct()
    {
        $this->widgets = collect([]);
        $this->widgets->push(new ProjectCountWidget());
        $this->widgets->push(new TopTenPercentWidget());
        $this->widgets->push(new TopThirtyPercentWidget());
        $this->widgets->push(new TopOneHundredPercentWidget());
        $this->widgets->push(new MaxBudgetWidget());
        $this->widgets->push(new MasteredBudgetWidget());
        $this->widgets->push(new MasteredBudgetPercentWidget());
        $this->widgets->push(new ProjectManagerCountWidget());
        $this->widgets->push(new SeoUserCountWidget());
    }

    public function get()
    {
        return $this->widgets;
    }

    protected function resolveByCode(): Collection
    {
        if ($this->resolvedByCode !== null) {
            return $this->resolvedByCode;
        }

        $this->resolvedByCode = collect();

        foreach ($this->widgets as $widget) {
            $data = $widget->widget();
            if ($data->isNotEmpty()) {
                $this->resolvedByCode->put($widget->getCode(), $data);
            }
        }

        return $this->resolvedByCode;
    }

    public function getCollection()
    {
        return $this->resolveByCode()->values();
    }

    public function getMenu()
    {
        $resolved = $this->resolveByCode();
        $menu = collect([]);

        foreach ($this->widgets as $widget) {
            $data = $resolved->get($widget->getCode());
            $active = $data !== null && $data->has('active') ? $data->get('active') : false;

            $menu->push(collect([
                'code' => $widget->getCode(),
                'name' => $widget->getName(),
                'active' => $active,
            ]));
        }

        return $menu;
    }

    public function getWidgetByCode(string $code)
    {
        foreach ($this->widgets as $widget)
            if($widget->getCode() === $code)
                return $widget;

        return null;
    }
}
