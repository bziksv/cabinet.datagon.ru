@include('pages.partials.team-create-modal', [
    'modalId' => 'sa-team-create-modal',
    'returnTo' => 'site-audit',
    'showManageLink' => true,
    'lead' => 'Команда общая для Чеклиста, SEO-отчётов и Аудита. Создайте, сразу добавьте участников, затем выберите команду у сайта.',
    'teamCandidates' => $teamCandidates ?? collect(),
    'teamRoleLabels' => $teamRoleLabels ?? \App\SeoChecklist\SeoChecklistTeam::roleLabels(),
])
