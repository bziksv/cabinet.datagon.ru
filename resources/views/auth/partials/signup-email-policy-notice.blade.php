<div class="cabinet-signup-email-policy-notice" role="alert">
    <div class="cabinet-signup-email-policy-notice__inner">
        <div class="cabinet-signup-email-policy-notice__icon" aria-hidden="true">!</div>
        <div class="cabinet-signup-email-policy-notice__content">
            <div class="cabinet-signup-email-policy-notice__title">{{ __('Signup email policy title') }}</div>
            <div class="cabinet-signup-email-policy-notice__text">
                <p>{!! __('Signup email policy body') !!}</p>
                <p>{!! __('Signup email policy contact', ['email' => e(config('cabinet-signup-email.support_email'))]) !!}</p>
                @if(config('cabinet-signup-email.support_phone'))
                    <p>{!! __('Signup email policy phone', ['phone' => e(config('cabinet-signup-email.support_phone'))]) !!}</p>
                @endif
                <p class="cabinet-signup-email-policy-notice__legal">{!! __('Signup email policy legal') !!}</p>
            </div>
        </div>
    </div>
</div>
