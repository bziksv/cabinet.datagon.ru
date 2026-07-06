@php
    $active = $active ?? 'projects';
    $project = $project ?? null;
    $projectId = $projectId ?? ($project->id ?? null);
    $projectName = $projectName ?? ($project->project_name ?? null);
@endphp
<nav class="cabinet-bl-module-nav card border shadow-sm mb-0" aria-label="{{ __('Backlink nav label') }}">
    <div class="card-body py-2 px-2">
        <ul class="nav nav-pills cabinet-bl-module-nav__list flex-wrap gap-1 mb-0">
            <li class="nav-item">
                <a href="{{ route('backlink') }}"
                   class="nav-link{{ $active === 'projects' ? ' active' : '' }}">
                    <i class="bi bi-list-ul me-1" aria-hidden="true"></i>{{ __('My Projects') }}
                </a>
            </li>
            @if($active === 'create')
                <li class="nav-item">
                    <span class="nav-link active" aria-current="page">
                        <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>{{ __('Add link tracking') }}
                    </span>
                </li>
            @endif
            @if(in_array($active, ['show', 'add-link'], true) && $projectId)
                <li class="nav-item">
                    <a href="{{ route('show.backlink', $projectId) }}"
                       class="nav-link{{ $active === 'show' ? ' active' : '' }}"
                       @if($active === 'show') aria-current="page" @endif>
                        <i class="bi bi-folder2-open me-1" aria-hidden="true"></i>{{ $projectName ?: __('My project') }}
                    </a>
                </li>
            @endif
            @if($active === 'add-link')
                <li class="nav-item">
                    <span class="nav-link active" aria-current="page">
                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('Add link') }}
                    </span>
                </li>
            @endif
            @if($admin ?? false)
                <li class="nav-item">
                    <a href="{{ route('backlink.config') }}"
                       class="nav-link{{ $active === 'config' ? ' active' : '' }}"
                       @if($active === 'config') aria-current="page" @endif>
                        <i class="bi bi-gear me-1" aria-hidden="true"></i>{{ __('Module administration') }}
                    </a>
                </li>
            @endif
        </ul>
    </div>
</nav>
