<?php

namespace App\Mail;

use App\SeoReports\SeoReport;
use App\SeoReports\SeoReportProject;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SeoReportClientReactionMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var SeoReportProject */
    public $project;

    /** @var SeoReport */
    public $report;

    /** @var string */
    public $sectionTitle;

    /** @var string */
    public $typeLabel;

    /** @var string|null */
    public $text;

    /** @var string */
    public $cabinetUrl;

    public function __construct(
        SeoReportProject $project,
        SeoReport $report,
        string $sectionTitle,
        string $typeLabel,
        ?string $text,
        string $cabinetUrl
    ) {
        $this->project = $project;
        $this->report = $report;
        $this->sectionTitle = $sectionTitle;
        $this->typeLabel = $typeLabel;
        $this->text = $text;
        $this->cabinetUrl = $cabinetUrl;
    }

    public function build()
    {
        $subject = __('SEO report client reaction subject', [
            'domain' => $this->project->domain,
        ]);

        return $this->subject($subject)
            ->markdown('emails.seo-reports.client-reaction', [
                'project' => $this->project,
                'report' => $this->report,
                'sectionTitle' => $this->sectionTitle,
                'typeLabel' => $this->typeLabel,
                'text' => $this->text,
                'cabinetUrl' => $this->cabinetUrl,
            ]);
    }
}
