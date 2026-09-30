<?php

namespace App\Http\Controllers\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\OnlineUserService;
use Illuminate\Http\Request;

class ServerController extends Controller
{
    public function fetch(Request $request)
    {
        $servers = Server::where('show', true)
            ->where(function ($query) {
                $query->whereNull('transfer_enable')
                    ->orWhere('transfer_enable', 0)
                    ->orWhereRaw('u + d < transfer_enable');
            })
            ->orderBy('sort', 'ASC')
            ->get()
            ->append(['last_check_at', 'is_online', 'cache_key'])
            ->each(function ($server) {
                $server->rate = $server->getCurrentRate();
            });

        $onlineTotal = (new OnlineUserService())->count();
        $eTag = sha1(json_encode([
            'servers' => $servers->pluck('cache_key'),
            'online_total' => $onlineTotal,
        ]));
        if (str_contains($request->header('If-None-Match', ''), $eTag)) {
            return response(null, 304);
        }

        $data = $servers->map(fn ($server) => [
            'id' => $server->id,
            'type' => $server->type,
            'version' => $server->version,
            'name' => $server->name,
            'rate' => $server->rate,
            'tags' => $server->tags,
            'is_online' => $server->is_online,
            'last_check_at' => $server->last_check_at,
        ]);

        return response([
            'online_total' => $onlineTotal,
            'data' => $data,
        ])->header('ETag', "\"{$eTag}\"");
    }
}
