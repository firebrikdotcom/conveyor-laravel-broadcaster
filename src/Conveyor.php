<?php

namespace Kanata\LaravelBroadcaster;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Http;

class Conveyor
{
    /**
     * @param array<int, string|PrivateChannel|Channel> $channels
     * @param string $event
     * @param array<string, mixed> $payload
     */
    public function broadcast(array $channels, string $event, array $payload = []): void
    {
        $url = (config('conveyor.protocol') === 'ws' ? 'http' : 'https') . '://'
            . config('conveyor.uri') . ':'
            . config('conveyor.port') . '/conveyor/message'
            . (config('conveyor.query') ? '?' . config('conveyor.query') : '');

        foreach ($channels as $channel) {
            Http::post($url, [
                'channel' => (string) $channel,
                'message' => json_encode($payload, JSON_THROW_ON_ERROR),
            ])->throw();
        }
    }

    /**
     * It retrieves a single use token for the private channel.
     */
    public static function getToken(?string $channel = null): string
    {
        $query = config('conveyor.query');
        if (empty($query)) {
            return '';
        }

        $conveyorUrl = (config('conveyor.protocol') === 'ws' ? 'http' : 'https') . '://'
            . config('conveyor.uri') . ':'
            . config('conveyor.port') . '/conveyor/auth?' . $query;

        $body = $channel === null ? [] : [
            'channel' => $channel,
        ];

        return (string) Http::post($conveyorUrl, $body)->throw()->json('auth', '');
    }
}
