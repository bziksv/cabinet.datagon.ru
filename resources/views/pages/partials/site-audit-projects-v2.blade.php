<section class="cabinet-sa-sites" id="sa-projects" data-sa-tour="projects">
    <header class="cabinet-sa-sites__head">
        <div>
            <h2 class="cabinet-sa-sites__title">Ваши сайты</h2>
            <p class="cabinet-sa-sites__sub mb-0">
                @if(isset($projectsLimit))
                    {{ $projects->count() }} / {{ (int) $projectsLimit }}
                @else
                    {{ $projects->count() }}
                @endif
                @if(isset($schedulesLimit))
                    <span data-sa-pro>· авто {{ (int) ($schedulesUsed ?? 0) }}/{{ (int) $schedulesLimit }}</span>
                @endif
            </p>
        </div>
        <div class="cabinet-sa-sites__head-actions" data-sa-pro>
            @if(!empty($teamAccessReady))
                <button type="button"
                        class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#sa-team-create-modal">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i> Создать команду
                </button>
            @endif
            <a href="{{ route('profile.index') }}#team" class="cabinet-sa-sites__team-link">Моя команда</a>
        </div>
    </header>

    <div class="cabinet-sa-sites__list">
    @forelse($projects as $project)
        @php
            $last = $project->crawls->first();
            $sch = ($schedules ?? collect())->get($project->id);
            $schSettings = ($sch && is_array($sch->settings_json)) ? $sch->settings_json : [];
            $schWeekday = (int) ($schSettings['weekday'] ?? now()->dayOfWeekIso);
            $schHour = (int) ($schSettings['hour'] ?? 4);
            $schSpeed = (string) ($schSettings['crawl_speed'] ?? 'normal');
            $schConc = max(1, (int) ($schSettings['concurrency'] ?? 1));
            $schPages = (int) ($schSettings['pages_limit'] ?? ($pagesLimit ?? 100));
            $maxConc = max(1, (int) ($concurrencyLimit ?? 1));
            $maxPages = max(1, (int) ($pagesLimit ?? 100));
            $isProjectOwner = auth()->id() && (int) $project->user_id === (int) auth()->id();
            $teamTitle = !empty($project->team) ? $project->team->title : null;
            $schOn = $sch && $sch->enabled;
            $hasManage = $isProjectOwner && (!empty($teamAccessReady) || !empty($canSchedule));
        @endphp
        <article class="cabinet-sa-site">
            <div class="cabinet-sa-site__row">
                <div class="cabinet-sa-site__main min-w-0">
                    <div class="cabinet-sa-site__domain text-truncate">{{ $project->domain }}</div>
                    <div class="cabinet-sa-site__meta">
                        @if($last)
                            <a href="{{ route('pages.site-audit.crawl.show', $last->id) }}">#{{ $last->id }}</a>
                            · {{ $last->statusLabelRu() }}
                            · {{ $last->pages_fetched }}/{{ $last->pages_total }}
                        @else
                            ещё не запускался
                        @endif
                    </div>
                </div>

                <div class="cabinet-sa-site__chips" data-sa-pro>
                    @if($teamTitle)
                        <span class="cabinet-sa-chip">{{ $teamTitle }}</span>
                    @elseif($isProjectOwner)
                        <span class="cabinet-sa-chip cabinet-sa-chip--muted">без команды</span>
                    @else
                        <span class="cabinet-sa-chip cabinet-sa-chip--muted">по команде</span>
                    @endif
                    @if($schOn && $sch->next_run_at)
                        <span class="cabinet-sa-chip cabinet-sa-chip--on">авто {{ $sch->next_run_at->format('d.m H:i') }}</span>
                    @elseif($isProjectOwner && !empty($canSchedule))
                        <span class="cabinet-sa-chip cabinet-sa-chip--muted">авто выкл</span>
                    @endif
                </div>

                <div class="cabinet-sa-site__actions">
                    @if($last)
                        <a class="btn btn-sm btn-primary" href="{{ route('pages.site-audit.crawl.show', $last->id) }}">Отчёт</a>
                    @endif
                    @if($isProjectOwner)
                        <form method="POST" action="{{ route('pages.site-audit.project.destroy', $project->id) }}" class="d-inline"
                              data-cabinet-confirm="Удалить проект {{ e($project->domain) }} и все проверки?"
                              data-cabinet-confirm-title="Удаление проекта"
                              data-cabinet-confirm-ok="Удалить"
                              data-cabinet-confirm-danger="1">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Удалить">
                                <i class="bi bi-trash" aria-hidden="true"></i>
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            @if($hasManage)
                <details class="cabinet-sa-site__more" data-sa-pro>
                    <summary class="cabinet-sa-site__more-sum">
                        Команда и расписание
                        <i class="bi bi-chevron-down" aria-hidden="true"></i>
                    </summary>
                    <div class="cabinet-sa-site__more-body">
                        @if($isProjectOwner && !empty($teamAccessReady))
                            <form method="POST" action="{{ route('pages.site-audit.project.team', $project->id) }}" class="cabinet-sa-site__team">
                                @csrf
                                <label class="form-label small mb-1" for="sa-team-{{ $project->id }}">Команда</label>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <select name="team_id" id="sa-team-{{ $project->id }}" class="form-select form-select-sm" style="min-width:12rem;max-width:18rem">
                                        <option value="0">Без команды</option>
                                        @foreach(($checklistTeams ?? []) as $team)
                                            <option value="{{ $team->id }}" @if((int) ($project->team_id ?? 0) === (int) $team->id) selected @endif>
                                                {{ $team->title }}
                                                @if(isset($team->members_count)) · {{ (int) $team->members_count }} чел.@endif
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Сохранить</button>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#sa-team-create-modal">
                                        Создать…
                                    </button>
                                </div>
                                @if(empty($checklistTeams) || (is_countable($checklistTeams) && count($checklistTeams) === 0))
                                    <div class="form-text">
                                        Команд пока нет —
                                        <button type="button" class="btn btn-link btn-sm p-0 align-baseline"
                                                data-bs-toggle="modal" data-bs-target="#sa-team-create-modal">
                                            создайте в окне
                                        </button>
                                        или в
                                        <a href="{{ route('profile.index') }}#team">Профиль → Моя команда</a>.
                                    </div>
                                @endif
                            </form>
                        @endif

                        @if($isProjectOwner && !empty($canSchedule))
                            <form method="POST" action="{{ route('pages.site-audit.schedule', $project->id) }}" class="cabinet-sa-project__schedule cabinet-sa-site__schedule mt-3" data-sa-tour="schedule">
                                @csrf
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <div class="form-check mb-0">
                                        <input type="checkbox" class="form-check-input" id="sa-sch-{{ $project->id }}"
                                               name="enabled" value="1" {{ $schOn ? 'checked' : '' }}>
                                        <label class="form-check-label fw-medium" for="sa-sch-{{ $project->id }}">Авторасписание</label>
                                    </div>
                                    @if($schOn && $sch->next_run_at)
                                        <span class="badge text-bg-primary">след. {{ $sch->next_run_at->format('d.m.Y H:i') }} МСК</span>
                                    @endif
                                </div>
                                <div class="cabinet-sa-project__schedule-body">
                                    <div class="row g-2 align-items-end mt-1">
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small mb-1" for="sa-sch-freq-{{ $project->id }}">Как часто</label>
                                            <select name="frequency" id="sa-sch-freq-{{ $project->id }}" class="form-select form-select-sm">
                                                @foreach(($scheduleFrequencies ?? []) as $freqCode => $freqLabel)
                                                    <option value="{{ $freqCode }}"
                                                        {{ ($sch ? \App\SiteAuditSchedule::normalizeFrequency($sch->frequency) : 'weekly') === $freqCode ? 'selected' : '' }}>
                                                        {{ $freqLabel }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small mb-1" for="sa-sch-wd-{{ $project->id }}">День</label>
                                            <select name="weekday" id="sa-sch-wd-{{ $project->id }}" class="form-select form-select-sm">
                                                @foreach(($scheduleWeekdays ?? []) as $wd => $wdLabel)
                                                    <option value="{{ $wd }}" {{ (int) $schWeekday === (int) $wd ? 'selected' : '' }}>{{ $wdLabel }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label small mb-1" for="sa-sch-hour-{{ $project->id }}">Час</label>
                                            <select name="hour" id="sa-sch-hour-{{ $project->id }}" class="form-select form-select-sm">
                                                @php
                                                    $peakHours = [11, 12, 13, 14];
                                                    if (in_array($schHour, $peakHours, true)) {
                                                        $schHour = 4;
                                                    }
                                                @endphp
                                                @for($h = 0; $h <= 23; $h++)
                                                    @if(in_array($h, $peakHours, true))
                                                        <option value="{{ $h }}" disabled>{{ sprintf('%02d:00', $h) }}</option>
                                                    @else
                                                        <option value="{{ $h }}" {{ $schHour === $h ? 'selected' : '' }}>{{ sprintf('%02d:00', $h) }}</option>
                                                    @endif
                                                @endfor
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label small mb-1" for="sa-sch-speed-{{ $project->id }}">Скорость</label>
                                            <select name="crawl_speed" id="sa-sch-speed-{{ $project->id }}" class="form-select form-select-sm">
                                                <option value="slow" {{ $schSpeed === 'slow' ? 'selected' : '' }}>slow</option>
                                                <option value="normal" {{ $schSpeed === 'normal' ? 'selected' : '' }}>normal</option>
                                                <option value="fast" {{ $schSpeed === 'fast' ? 'selected' : '' }}>fast</option>
                                                <option value="turbo" {{ $schSpeed === 'turbo' ? 'selected' : '' }}>turbo</option>
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-1">
                                            <label class="form-label small mb-1" for="sa-sch-conc-{{ $project->id }}">Пот.</label>
                                            <select name="concurrency" id="sa-sch-conc-{{ $project->id }}" class="form-select form-select-sm">
                                                @for($n = 1; $n <= $maxConc; $n++)
                                                    <option value="{{ $n }}" {{ $schConc === $n ? 'selected' : '' }}>{{ $n }}</option>
                                                @endfor
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label small mb-1" for="sa-sch-limit-{{ $project->id }}">Лимит</label>
                                            <input type="text" class="form-control form-control-sm sa-num-space" name="pages_limit"
                                                   id="sa-sch-limit-{{ $project->id }}"
                                                   inputmode="numeric" autocomplete="off"
                                                   value="{{ number_format((int) min($schPages, $maxPages), 0, '', ' ') }}"
                                                   data-min="1" data-max="{{ $maxPages }}">
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <button type="submit" class="btn btn-sm btn-primary w-100">OK</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        @elseif($isProjectOwner)
                            <div class="small text-secondary mt-2">Авторасписание на бесплатном тарифе недоступно.</div>
                        @endif
                    </div>
                </details>
            @endif
        </article>
    @empty
        <div class="cabinet-sa-sites__empty">
            Сайтов пока нет — запустите первую проверку слева.
        </div>
    @endforelse
    </div>
</section>
