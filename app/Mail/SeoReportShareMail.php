<?php

namespace App\Mail;

use App\SeoReports\SeoReport;
use App\SeoReports\SeoReportProject;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SeoReportShareMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var SeoReportProject */
    public $project;

    /** @var SeoReport */
    public $report;

    /** @var string */
    public $publicUrl;

    /** @var string|null */
    public $messageText;

    /** @var string|null */
    public $senderName;

    public function __construct(
        SeoReportProject $project,
        SeoReport $report,
        string $publicUrl,
        ?string $messageText = null,
        ?string $senderName = null
    ) {
        $this->project = $project;
        $this->report = $report;
        $this->publicUrl = $publicUrl;
        $this->messageText = $messageText;
        $this->senderName = $senderName;
    }

    public function build()
    {
        $period = optional($this->report->period_from)->format('d.m.Y')
            . ' — '
            . optional($this->report->period_to)->format('d.m.Y');

        $subject = __('SEO report for :domain', ['domain' => $this->project->domain])
            . ' (' . $period . ')';

        return $this->subject($subject)
            ->markdown('emails.seo-reports.share', [
                'project' => $this->project,
                'report' => $this->report,
                'publicUrl' => $this->publicUrl,
                'messageText' => $this->messageText,
                'senderName' => $this->senderName,
                'period' => $period,
                'hasPin' => trim((string) ($this->report->public_pin ?? '')) !== '',
            ]);
    }
}
