<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\GoogleGeoRegions;
use App\Support\YandexLrRegions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Подсказки городов для Select2 (мастер мониторинга и др.).
 * Поиск по кириллице и латинице — локальные JSON, без внешнего API.
 */
class LocationSearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $name = trim((string) $request->get('name', ''));
        $search = (string) $request->get('searchEngine', '');

        if ($name === '' || mb_strlen($name) < 2) {
            return response()->json([]);
        }

        $limit = min(50, max(5, (int) $request->get('limit', 25)));

        if ($search === 'yandex') {
            return response()->json($this->mapResults(YandexLrRegions::search($name, $limit), 'yandex'));
        }

        if ($search === 'google') {
            return response()->json($this->mapResults(GoogleGeoRegions::search($name, $limit), 'google'));
        }

        return response()->json([]);
    }

    /**
     * @param array<int, array{id: string, name: string}> $items
     *
     * @return array<int, array{lr: string, source: string, name: string}>
     */
    private function mapResults(array $items, string $source): array
    {
        $out = [];
        foreach ($items as $item) {
            $id = (string) ($item['id'] ?? '');
            $name = (string) ($item['name'] ?? '');
            if ($id === '' || $name === '') {
                continue;
            }
            $out[] = [
                'lr' => $id,
                'source' => $source,
                'name' => $name,
            ];
        }

        return $out;
    }
}
