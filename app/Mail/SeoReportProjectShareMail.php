<?php

namespace App\Mail;

use App\SeoReports\SeoReportProject;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SeoReportProjectShareMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var SeoReportProject */
    public $project;

    /** @var string */
    public $role;

    public function __construct(SeoReportProject $project, string $role = 'read')
    {
        $this->project = $project;
        $this->role = $role;
    }

    public function build()
    {
        return $this->subject(__('You have been given access to SEO report project') . ': ' . $this->project->domain)
            ->markdown('emails.seo-reports.project-share', [
                'project' => $this->project,
                'role' => $this->role,
                'url' => route('pages.seo-reports.show', ['id' => $this->project->id]),
            ]);
    }
}
