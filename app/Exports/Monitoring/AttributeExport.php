<?php


namespace App\Exports\Monitoring;

use Illuminate\Support\Collection;
use Illuminate\Http\Request;

class AttributeExport
{
    private $collection;
    private $request;
    private $budget = 0;

    public function __construct(Collection &$collection, Request $request)
    {
        $this->collection = $collection;
        $this->request = $request;
    }

    public function execute()
    {
        if ($this->request['mode'] == 'finance') {
            $this->setTotalSum($this->getBudget());
        }

        if ($this->request['dynamicsDays']) {
            $this->removeDynamicDays();
        }

        $this->url();
    }

    protected function removeDynamicDays()
    {
        $this->collection['data']->transform(function($item) {
            foreach ($item as $col => $val) {
                $item[$col] = preg_replace('/<sup(.*)sup>/', '', $val);
            }

            return $item;
        });
    }

    protected function setTotalSum($budget)
    {
        $total = $this->collection['data']->pluck('mastered')->sum();
        $columnKeys = $this->collection['columns']->keys()->values();

        if ($columnKeys->isEmpty()) {
            return;
        }

        $labelKey = $columnKeys->first();
        $valueKey = $this->collection['columns']->has('mastered')
            ? 'mastered'
            : $columnKeys->last();

        $this->collection['data']->push($this->summaryRow($columnKeys, $labelKey, $valueKey, 'Выведено фраз на сумму:', $total));
        $this->collection['data']->push($this->summaryRow($columnKeys, $labelKey, $valueKey, 'Максимальный бюджет:', $budget));
    }

    /**
     * @param \Illuminate\Support\Collection $columnKeys
     * @param mixed $value
     */
    private function summaryRow($columnKeys, string $labelKey, string $valueKey, string $label, $value): Collection
    {
        $row = $columnKeys->mapWithKeys(static function ($key) {
            return [$key => ''];
        });
        $row[$labelKey] = $label;
        $row[$valueKey] = $value;

        return $row;
    }

    protected function url()
    {
        $this->collection['data']->transform(function($item){
            if ($item->has('url')) {
                $url = $item['url'];

                $doc = new \DOMDocument();
                $doc->loadHTML($url);

                $a = $doc->getElementsByTagName('a');
                $links = $a[0]->getAttribute('data-content');

                if ($links) {
                    $doc->loadHTML($links);
                    $a = $doc->getElementsByTagName('a');

                    if ($a->length) {
                        $item['url'] = strip_tags($a[$a->length - 1]->textContent);
                    }
                }
            }
            return $item;
        });
    }

    public function getBudget()
    {
        return $this->budget;
    }

    public function setBudget($budget): void
    {
        $this->budget = $budget;
    }


}
