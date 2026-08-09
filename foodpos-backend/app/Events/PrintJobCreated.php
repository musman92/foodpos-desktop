<?php

namespace App\Events;

use App\Models\PrintJob;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrintJobCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PrintJob $printJob) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('branch.'.$this->printJob->branch_id.'.print-jobs'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'PrintJobCreated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->printJob->loadMissing('printer');

        return [
            'id' => $this->printJob->id,
            'document_type' => $this->printJob->document_type,
            'print_url' => $this->printJob->print_url,
            'device_name' => $this->printJob->device_name,
            'printer_title' => $this->printJob->printer?->title,
        ];
    }
}
