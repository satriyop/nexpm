<?php

namespace App\Notifications;

use App\Models\Assignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AssignmentVerifiedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Assignment $assignment) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'assignment_id' => $this->assignment->id,
            'site_code' => $this->assignment->site->site_code,
            'location_name' => $this->assignment->site->location_name,
            'activity_type' => $this->assignment->activity_type->value,
            'message' => "Assignment {$this->assignment->site->site_code} – {$this->assignment->site->location_name} ({$this->assignment->activity_type->value}) has been verified.",
            'url' => "/subcontractor/assignments/{$this->assignment->id}",
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
