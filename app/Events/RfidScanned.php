<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RfidScanned implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public $rfid_id;

    public $broadcastQueue = 'null';

    public $status;

    public $message;

    public $entry_id;

    public $amount;

    public $vehicle_number;

    public $driver_name;

    public $kiosk;

    public $lot;

    public $vehicle_type;

    public function __construct($rfid_id, $status, $message = null, $entry_id = null, $amount = null, $vehicle_number = null, $driver_name = null, $kiosk = null, $lot = null, $vehicle_type = null)
    {
        $this->rfid_id = $rfid_id;
        $this->status = $status;
        $this->message = $message;
        $this->entry_id = $entry_id;
        $this->amount = $amount;
        $this->vehicle_number = $vehicle_number;
        $this->driver_name = $driver_name;
        $this->kiosk = $kiosk;
        $this->lot = $lot;
        $this->vehicle_type = $vehicle_type;
    }

    public function broadcastOn()
    {
        // Per-kiosk channel so only the browser(s) opened on that kiosk (?kiosk=<key>)
        // receive its scans. Falls back to the per-lot channel when the reader hasn't
        // learned its kiosk key yet, and finally to the global 'rfid' channel.
        if ($this->kiosk) {
            return new Channel('kiosk.'.$this->kiosk);
        }

        if ($this->lot) {
            return new Channel('kiosk.'.$this->lot);
        }

        return new Channel('rfid');
    }

    public function broadcastWith()
    {

        return [
            'rfid_id' => $this->rfid_id,
            'status' => $this->status,
            'message' => $this->message,
            'entry_id' => $this->entry_id,
            'amount' => $this->amount,
            'vehicle_number' => $this->vehicle_number,
            'driver_name' => $this->driver_name,
            'kiosk' => $this->kiosk,
            'lot' => $this->lot,
            'vehicle_type' => $this->vehicle_type,
        ];
    }
}
