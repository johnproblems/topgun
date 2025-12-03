<?php

namespace App\Actions\Server;

use App\Models\Server;
use Lorisleiva\Actions\Concerns\AsAction;

class StopSentinel
{
    use AsAction;

    public function handle(Server $server): void
    {
        instant_remote_process(['docker rm -f coolify-sentinel'], $server, false);
        $server->sentinelHeartbeat(isReset: true);
    }
}
