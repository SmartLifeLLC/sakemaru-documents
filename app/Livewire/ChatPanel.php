<?php

namespace App\Livewire;

use App\Models\DocumentChangeRequest;
use App\Services\DocumentChangeCommentClient;
use App\Services\DocumentChangeRequestClient;
use Carbon\Carbon;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ChatPanel extends Component
{
    public int $requestId;

    public string $currentStatus = '';

    /** @var list<array<string, mixed>> */
    public array $comments = [];

    #[Validate('required|string|max:5000')]
    public string $newMessage = '';

    public string $newStatus = '';

    /**
     * Status transitions allowed from the admin side.
     *
     * @var array<string, list<string>>
     */
    private const ADMIN_TRANSITIONS = [
        'open' => ['in_review', 'rejected'],
        'in_review' => ['resolved', 'rejected'],
    ];

    private const STATUS_LABELS = [
        'open' => '受付',
        'in_review' => '確認中',
        'resolved' => '完了',
        'rejected' => '却下',
        'canceled' => '取消',
    ];

    public function mount(int $requestId): void
    {
        $this->requestId = $requestId;
        $this->currentStatus = DocumentChangeRequest::find($requestId)?->status ?? '';
        $this->newStatus = '';
        $this->loadComments();
    }

    public function loadComments(): void
    {
        $raw = app(DocumentChangeRequestClient::class)->getComments($this->requestId);
        $new = app(DocumentChangeCommentClient::class)->resolveCommenterNames($raw)->values()->all();

        if (count($new) !== count($this->comments)) {
            $this->comments = $new;
            $this->dispatch('commentsUpdated');
        }
    }

    public function sendMessage(): void
    {
        $this->validate();

        $message = trim($this->newMessage);
        if ($message === '') {
            return;
        }

        app(DocumentChangeCommentClient::class)->post($this->requestId, $message);

        $this->newMessage = '';
        $this->loadComments();
        $this->dispatch('commentsUpdated');
    }

    public function changeStatus(): void
    {
        $allowed = self::ADMIN_TRANSITIONS[$this->currentStatus] ?? [];

        if (! in_array($this->newStatus, $allowed, true)) {
            $this->addError('newStatus', '無効なステータス遷移です。');

            return;
        }

        $updated = app(DocumentChangeRequestClient::class)->updateStatus($this->requestId, $this->newStatus);

        if ($updated) {
            $this->currentStatus = $this->newStatus;
            $this->dispatch('$refresh');
        }
    }

    /**
     * @return array<string, string>
     */
    public function getAvailableTransitions(): array
    {
        $transitions = self::ADMIN_TRANSITIONS[$this->currentStatus] ?? [];
        $options = [];
        foreach ($transitions as $status) {
            $options[$status] = self::STATUS_LABELS[$status] ?? $status;
        }

        return $options;
    }

    public function getStatusLabel(): string
    {
        return self::STATUS_LABELS[$this->currentStatus] ?? $this->currentStatus;
    }

    public function getStatusColor(): string
    {
        return match ($this->currentStatus) {
            'open' => '#f59e0b',
            'in_review' => '#3b82f6',
            'resolved' => '#10b981',
            'rejected' => '#ef4444',
            'canceled' => '#6b7280',
            default => '#6b7280',
        };
    }

    public function getStatusBgColor(): string
    {
        return match ($this->currentStatus) {
            'open' => 'bg-amber-50 dark:bg-amber-950/30',
            'in_review' => 'bg-blue-50 dark:bg-blue-950/30',
            'resolved' => 'bg-emerald-50 dark:bg-emerald-950/30',
            'rejected' => 'bg-red-50 dark:bg-red-950/30',
            'canceled' => 'bg-gray-50 dark:bg-gray-800',
            default => 'bg-gray-50 dark:bg-gray-800',
        };
    }

    public static function formatTime(?string $datetime): string
    {
        if (! $datetime) {
            return '';
        }

        try {
            $carbon = Carbon::parse($datetime);
            $today = Carbon::today();

            if ($carbon->isSameDay($today)) {
                return $carbon->format('H:i');
            }
            if ($carbon->year === $today->year) {
                return $carbon->format('n/j H:i');
            }

            return $carbon->format('Y/n/j H:i');
        } catch (\Throwable) {
            return $datetime;
        }
    }

    public function render()
    {
        return view('livewire.chat-panel');
    }
}
