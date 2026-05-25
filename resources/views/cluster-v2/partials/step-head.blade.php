<div class="cabinet-cluster-v2-step__head">
    <span class="cabinet-cluster-v2-step__num" aria-hidden="true">{{ $num }}</span>
    <div class="cabinet-cluster-v2-step__titles">
        <h2 class="cabinet-cluster-v2-step__title">{{ $title }}</h2>
        @if(!empty($desc))
            <p class="cabinet-cluster-v2-step__desc">{{ $desc }}</p>
        @endif
    </div>
</div>
