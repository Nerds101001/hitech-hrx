<?php

namespace App\Events;

use App\Models\UserDevice;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceStatusUpdated implements ShouldBroadcastNow
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

  public UserDevice $device;

  /**
   * Create a new event instance.
   */
  public function __construct(UserDevice $device)
  {
    $this->device = $device;
  }

  /**
   * Get the channels the event should broadcast on.
   *
   * @return array<int, Channel>
   */
  public function broadcastOn(): array
  {
    return [
      new Channel('device-updates'),
    ];
  }
}
