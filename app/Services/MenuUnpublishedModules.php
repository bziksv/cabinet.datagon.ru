<?php

namespace App\Services;

use App\MainProject;
use App\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class MenuUnpublishedModules
{
    /** @var array<string, string>|null */
    private static $catalogPrefixes;

    /** @var array<string, string>|null */
    private static $catalogControllerActions;

    /**
     * Полная сводка для /configuration-menu.
     *
     * @return array{
     *   catalogHidden: Collection,
     *   moduleExtraPages: Collection,
     *   outsideCatalog: Collection
     * }
     */
    public static function summaryForUser(?User $user = null): array
    {
        self::buildCatalogIndex();

        return [
            'catalogHidden' => self::catalogHiddenModules($user),
            'moduleExtraPages' => self::publishedModuleExtraPages($user),
            'outsideCatalog' => self::routesOutsideCatalog($user),
        ];
    }

    /**
     * @deprecated Use summaryForUser()['catalogHidden']
     */
    public static function forUser(?User $user = null): Collection
    {
        return self::catalogHiddenModules($user);
    }

    /**
     * main_projects с show=0.
     *
     * @return Collection<int, array{project: MainProject, pages: array}>
     */
    public static function catalogHiddenModules(?User $user = null): Collection
    {
        $user = self::resolveUser($user);
        if (! $user) {
            return collect();
        }

        return MenuProjectRegistry::ensureAllLoaded()
            ->where('show', 0)
            ->filter(static function (MainProject $project) use ($user) {
                return self::userCanAccessProject($project, $user);
            })
            ->map(static function (MainProject $project) {
                return [
                    'project' => $project,
                    'pages' => self::relatedPages($project),
                ];
            })
            ->values();
    }

    /**
     * Опубликованные модули: страницы из controller / под-путей, кроме главной ссылки в меню.
     *
     * @return Collection<int, array{project: MainProject, pages: array}>
     */
    public static function publishedModuleExtraPages(?User $user = null): Collection
    {
        $user = self::resolveUser($user);
        if (! $user) {
            return collect();
        }

        return MenuProjectRegistry::ensureAllLoaded()
            ->where('show', 1)
            ->filter(static function (MainProject $project) use ($user) {
                return self::userCanAccessProject($project, $user);
            })
            ->map(static function (MainProject $project) {
                $mainPath = self::projectPath($project);
                $mainUrl = $mainPath === '' ? '/' : '/' . $mainPath;

                $pages = array_values(array_filter(
                    self::relatedPages($project),
                    static function (array $page) use ($mainUrl, $mainPath) {
                        if (! empty($page['external'])) {
                            return false;
                        }
                        $url = $page['url'];
                        if ($url === $mainUrl) {
                            return false;
                        }
                        if ($mainPath !== '' && $url === '/' . $mainPath) {
                            return false;
                        }

                        return true;
                    }
                ));

                return [
                    'project' => $project,
                    'pages' => $pages,
                ];
            })
            ->filter(static function (array $entry) {
                if (self::projectPath($entry['project']) === '') {
                    return false;
                }

                return count($entry['pages']) > 0;
            })
            ->values();
    }

    /**
     * GET-маршруты с auth, не привязанные ни к одному main_projects (ни link, ни controller).
     *
     * @return Collection<int, array{group: string, label: string, pages: array}>
     */
    public static function routesOutsideCatalog(?User $user = null): Collection
    {
        $user = self::resolveUser($user);
        if (! $user) {
            return collect();
        }

        self::buildCatalogIndex();

        $groups = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            if (! self::routeRequiresAuth($route)) {
                continue;
            }
            if (! self::userCanAccessRoute($route, $user)) {
                continue;
            }

            $uri = $route->uri();
            if (self::shouldSkipRouteUri($uri)) {
                continue;
            }
            if (self::routeCoveredByCatalog($route)) {
                continue;
            }

            $segment = explode('/', $uri)[0] ?: 'other';
            if (! isset($groups[$segment])) {
                $groups[$segment] = [
                    'group' => $segment,
                    'label' => self::groupLabel($segment),
                    'pages' => [],
                ];
            }

            $url = self::routeUrl($route);
            $groups[$segment]['pages'][] = [
                'label' => $route->getName() ?: $uri,
                'url' => $url,
            ];
        }

        foreach ($groups as &$group) {
            usort($group['pages'], static function ($a, $b) {
                return strcmp($a['url'], $b['url']);
            });
        }
        unset($group);

        uasort($groups, static function ($a, $b) {
            return strcmp($a['label'], $b['label']);
        });

        return collect(array_values($groups));
    }

    /**
     * @return array<int, array{label: string, url: string, external?: bool}>
     */
    public static function relatedPages(MainProject $project): array
    {
        $link = localize_cabinet_url($project->link);
        if ($link === null || $link === '') {
            return [];
        }

        $host = parse_url($link, PHP_URL_HOST);
        $path = self::projectPath($project);

        if ($host && ! self::isCabinetHost($host)) {
            return [
                [
                    'label' => $link,
                    'url' => $link,
                    'external' => true,
                ],
            ];
        }

        $pages = [];
        $seen = [];

        $mainUrl = $path === '' ? '/' : '/' . $path;
        $pages[] = [
            'label' => __('Module home'),
            'url' => $mainUrl,
        ];
        $seen[$mainUrl] = true;

        self::appendRoutesFromControllerField($project, $pages, $seen);
        self::appendRoutesByPathPrefix($path, $pages, $seen);

        usort($pages, static function ($a, $b) {
            return strcmp($a['url'], $b['url']);
        });

        return $pages;
    }

    private static function buildCatalogIndex(): void
    {
        if (self::$catalogPrefixes !== null) {
            return;
        }

        self::$catalogPrefixes = [];
        self::$catalogControllerActions = [];

        foreach (MenuProjectRegistry::ensureAllLoaded() as $project) {
            $path = self::projectPath($project);
            $key = $path === '' ? '__home__' : $path;
            self::$catalogPrefixes[$key] = $project->title;

            if (empty($project->controller)) {
                continue;
            }

            foreach (preg_split('/\R/', $project->controller) as $line) {
                $line = trim(str_replace('!', '@', $line));
                if (! preg_match('/^([A-Za-z0-9_\\\\]+)@([A-Za-z0-9_]+)/', $line, $m)) {
                    continue;
                }
                $class = 'App\\Http\\Controllers\\' . ltrim($m[1], '\\');
                $action = $m[2];
                self::$catalogControllerActions[$class . '@' . $action] = $project->title;
            }
        }
    }

    private static function routeCoveredByCatalog($route): bool
    {
        $uri = $route->uri();

        if ($uri === '/') {
            return isset(self::$catalogPrefixes['__home__']);
        }

        foreach (self::$catalogPrefixes as $prefix => $_title) {
            if ($prefix === '__home__') {
                continue;
            }
            if ($uri === $prefix || strpos($uri, $prefix . '/') === 0) {
                return true;
            }
        }

        $action = $route->getActionName();
        if (isset(self::$catalogControllerActions[$action])) {
            return true;
        }

        foreach (array_keys(self::$catalogControllerActions) as $controllerAction) {
            if (strpos($action, $controllerAction) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function appendRoutesFromControllerField(MainProject $project, array &$pages, array &$seen): void
    {
        if (empty($project->controller)) {
            return;
        }

        foreach (preg_split('/\R/', $project->controller) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $line = str_replace('!', '@', $line);
            if (! preg_match('/^([A-Za-z0-9_\\\\]+)@([A-Za-z0-9_]+)/', $line, $m)) {
                continue;
            }
            $controllerClass = 'App\\Http\\Controllers\\' . ltrim($m[1], '\\');
            $action = $m[2];

            foreach (Route::getRoutes() as $route) {
                if (! in_array('GET', $route->methods(), true)) {
                    continue;
                }
                if (! self::routeRequiresAuth($route)) {
                    continue;
                }
                $actionName = $route->getActionName();
                if (strpos($actionName, $controllerClass . '@' . $action) === false) {
                    continue;
                }
                $url = self::routeUrl($route);
                if (isset($seen[$url])) {
                    continue;
                }
                $seen[$url] = true;
                $pages[] = [
                    'label' => $route->getName() ?: $route->uri(),
                    'url' => $url,
                ];
            }
        }
    }

    private static function appendRoutesByPathPrefix(string $path, array &$pages, array &$seen): void
    {
        if ($path === '') {
            return;
        }

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            if (! self::routeRequiresAuth($route)) {
                continue;
            }
            $uri = $route->uri();
            if ($uri !== $path && strpos($uri, $path . '/') !== 0) {
                continue;
            }
            $url = self::routeUrl($route);
            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $pages[] = [
                'label' => $route->getName() ?: $uri,
                'url' => $url,
            ];
        }
    }

    private static function routeUrl($route): string
    {
        $uri = trim($route->uri(), '/');

        return $uri === '' ? '/' : '/' . $uri;
    }

    private static function projectPath(MainProject $project): string
    {
        $link = localize_cabinet_url($project->link);
        $path = parse_url($link ?? '', PHP_URL_PATH);

        return trim((string) $path, '/');
    }

    private static function userCanAccessProject(MainProject $project, User $user): bool
    {
        $access = is_array($project->access) ? $project->access : [];

        return $access === [] || $user->hasRole($access);
    }

    private static function userCanAccessRoute($route, User $user): bool
    {
        foreach ($route->middleware() as $middleware) {
            if (strpos($middleware, 'permission:') === 0) {
                $permission = substr($middleware, strlen('permission:'));
                if (! $user->can($permission)) {
                    return false;
                }
            }
            if (strpos($middleware, 'role:') === 0) {
                $roles = explode('|', substr($middleware, strlen('role:')));
                if (! $user->hasRole($roles)) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function shouldSkipRouteUri(string $uri): bool
    {
        if (preg_match('#^(public/|email/)#', $uri)) {
            return true;
        }
        if ($uri === 'api/user') {
            return true;
        }
        if ($uri === 'test') {
            return true;
        }

        return false;
    }

    private static function groupLabel(string $segment): string
    {
        $map = [
            'news' => 'Новости',
            'profile' => 'Профиль',
            'balance' => 'Баланс',
            'balance-add' => 'Пополнение баланса',
            'tariff' => 'Тариф',
            'checklist' => 'Чеклисты',
            'partners' => 'Партнёры',
            'share-my-projects' => 'Шаринг проектов',
            'access-projects' => 'Доступ к проектам',
            'history' => 'История (релевантность)',
            'create-queue' => 'Очередь (релевантность)',
            'relevance-config' => 'Конфиг релевантности',
            'all-projects' => 'Все проекты релевантности',
            'competitors-config' => 'Конфиг конкурентов',
            'cluster-configuration' => 'Конфиг кластеризатора',
            'cluster-projects' => 'Проекты кластеризатора',
            'ai-generation' => 'AI-генерация',
            'ai-macros' => 'AI-макросы',
            'ai-stopwords' => 'AI стоп-слова',
            'ai-stopwords-categories' => 'AI категории стоп-слов',
            'relevance-history' => 'AI / история релевантности',
            'relevance-projects' => 'AI / проекты релевантности',
            'unique-words' => 'Уникальные слова (отдельный URL)',
            'visits-statistics' => 'Статистика визитов',
            'modules-statistics' => 'Статистика модулей',
            'visit-statistics' => 'Визиты пользователя',
            'create-news' => 'Создание новости',
            'create-project' => 'HTML-редактор: проект',
            'create-description' => 'HTML-редактор: описание',
            'edit-project' => 'HTML-редактор: правка проекта',
            'edit-description' => 'HTML-редактор: правка описания',
            'add-backlink' => 'Бэклинки',
            'add-site-monitoring' => 'Мониторинг сайтов',
            'add-domain-information' => 'Информация о доменах',
            'monitoring-competitors' => 'Мониторинг: конкуренты',
            'other' => 'Прочее',
        ];

        return $map[$segment] ?? ucfirst(str_replace('-', ' ', $segment));
    }

    private static function resolveUser(?User $user): ?User
    {
        $user = $user ?? Auth::user();
        if ($user) {
            $user->loadMissing('roles');
        }

        return $user;
    }

    private static function isCabinetHost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', 'lk.redbox.su', 'cabinet.datagon.ru'], true);
    }

    private static function routeRequiresAuth($route): bool
    {
        foreach ($route->middleware() as $m) {
            if ($m === 'auth' || strpos($m, 'auth') === 0) {
                return true;
            }
        }

        return false;
    }
}
