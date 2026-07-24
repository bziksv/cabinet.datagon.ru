<?php

namespace App\Notifications;

use App\ClusterResults;
use App\Common;
use App\Contracts\EmailPreferenceAware;
use App\Notifications\Concerns\AppendsMailUnsubscribe;
use App\Notifications\Concerns\LocalizesMailContent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClusterCompletedNotification extends Notification implements EmailPreferenceAware
{
    use AppendsMailUnsubscribe;
    use LocalizesMailContent;
    use Queueable;

    /** @var ClusterResults */
    public $cluster;

    /** @var array */
    public $requestData;

    public function __construct(ClusterResults $cluster, array $requestData = [])
    {
        $this->cluster = $cluster;
        $this->requestData = $requestData;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $this->applyMailLocale($notifiable);

        $domain = (string) ($this->requestData['domain'] ?? $this->cluster->domain ?? '—');
        $comment = trim((string) ($this->requestData['comment'] ?? $this->cluster->comment ?? ''));
        $region = Common::getRegionName($this->requestData['region'] ?? '');
        $level = (string) ($this->requestData['clusteringLevel'] ?? $this->cluster->clustering_level ?? '—');

        $mail = (new MailMessage)
            ->subject(__('Cluster completed mail subject', ['id' => $this->cluster->id]))
            ->greeting(__('Hello') . '!')
            ->line(__('Cluster completed mail lead', ['id' => $this->cluster->id]))
            ->line(__('Domain') . ': ' . $domain);

        if ($comment !== '') {
            $mail->line(__('Comment') . ': ' . $comment);
        }

        $mail
            ->line(__('Number of phrases') . ': ' . (int) $this->cluster->count_phrases)
            ->line(__('Number of groups') . ': ' . (int) $this->cluster->count_clusters)
            ->line(__('Top') . ': ' . (int) $this->cluster->top)
            ->line(__('Clustering level') . ': ' . $level)
            ->line(__('Region') . ': ' . ($region ?: '—'))
            ->action(__('View results'), route('show.cluster.result', $this->cluster->id))
            ->line(__('Thank you') . '.');

        return $this->appendMailUnsubscribe($mail, $this->emailPreferenceKey());
    }

    public function emailPreferenceKey(): ?string
    {
        return 'cluster-done';
    }

    public function toArray($notifiable)
    {
        return [
            'cluster_id' => $this->cluster->id,
        ];
    }
}
