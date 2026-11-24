<?php


namespace App\Classes\AnalyzeRelevance;


use App\Http\Controllers\RelevanceController;
use App\Jobs;

class RelevanceQueues
{
    protected $job;

    public function __construct()
    {
        $this->job = new Jobs();
    }

    public function all()
    {
        return $this->job([RelevanceController::HIGH_QUEUE, RelevanceController::MEDIUM_QUEUE, RelevanceController::NORMAL_QUEUE]);
    }

    public function high()
    {
        return $this->job([RelevanceController::HIGH_QUEUE]);
    }

    public function medium()
    {
        return $this->job([RelevanceController::MEDIUM_QUEUE]);
    }

    public function normal()
    {
        return $this->job([RelevanceController::NORMAL_QUEUE]);
    }

    /**
     * Лёгкая сводка задач без выборки payload целиком: payload отдельных задач весит до десятков МБ,
     * unserialize всей очереди занимал минуты. Поля вырезаются из сериализованной команды на стороне БД.
     *
     * @return \Illuminate\Support\Collection<int, object{job:string,queue:string,userId:int,historyId:int,mainId:int,type:string,phrase:string,link:string}>
     */
    public function summaries(?array $queues = null)
    {
        $queues = $queues ?: [RelevanceController::HIGH_QUEUE, RelevanceController::MEDIUM_QUEUE, RelevanceController::NORMAL_QUEUE];
        $fields = [
            'userId' => 80, 'historyId' => 80, 'mainId' => 80, 'type' => 200,
            'phrase' => 3000, 'link' => 3000, 'main_link' => 3000,
        ];

        $select = ['id', 'queue', 'LEFT(payload, 400) AS head'];
        $bindings = [];
        foreach ($fields as $name => $length) {
            // Побайтовый поиск: LOCATE по utf8mb4 на десятках МБ в разы медленнее
            $select[] = "CONVERT(SUBSTRING(CAST(payload AS BINARY), NULLIF(LOCATE(CAST(? AS BINARY), CAST(payload AS BINARY)), 0), {$length}) USING utf8mb4) AS f_{$name}";
            $bindings[] = self::needle($name);
        }

        $rows = $this->job->newQuery()
            ->selectRaw(implode(', ', $select), $bindings)
            ->whereIn('queue', $queues)
            ->orderBy('id')
            ->get();

        return $rows->map(function ($row) {
            preg_match('/"displayName":"((?:[^"\\\\]|\\\\.)*)"/', (string) $row->head, $m);
            $class = isset($m[1]) ? (string) json_decode('"' . $m[1] . '"') : '';

            $link = self::serializedString($row->f_link);

            return (object) [
                'job' => class_basename($class),
                'queue' => (string) $row->queue,
                'userId' => self::serializedInt($row->f_userId),
                'historyId' => self::serializedInt($row->f_historyId),
                'mainId' => self::serializedInt($row->f_mainId),
                'type' => self::serializedString($row->f_type),
                'phrase' => self::serializedString($row->f_phrase),
                'link' => $link !== '' ? $link : self::serializedString($row->f_main_link),
            ];
        });
    }

    /** Ключ свойства/элемента массива так, как он выглядит внутри JSON payload */
    private static function needle(string $name): string
    {
        return 's:' . strlen($name) . ':\\"' . $name . '\\";';
    }

    private static function serializedInt($snippet): int
    {
        if (! is_string($snippet) || ! preg_match('/^s:\d+:\\\\"\w+\\\\";(?:i:(\d+);|s:\d+:\\\\"(\d+)\\\\";)/', $snippet, $m)) {
            return 0;
        }

        return (int) (($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? 0));
    }

    private static function serializedString($snippet): string
    {
        if (! is_string($snippet) || ! preg_match('/^s:\d+:\\\\"\w+\\\\";s:\d+:\\\\"(.*?)\\\\";/s', $snippet, $m)) {
            return '';
        }
        $value = json_decode('"' . $m[1] . '"');

        return is_string($value) ? $value : '';
    }

    private function job(array $queue)
    {
        $objects = collect([]);
        $queues = $this->job->whereIn('queue', $queue)->pluck('payload');
        $commands = $queues->pluck('data');

        foreach($commands as $command)
        {
            $obj = unserialize($command['command']);
            $objects->push($obj);
        }

        return $objects;
    }
}
