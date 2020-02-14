<?php

/*
 * This file is part of the DriftPHP Project
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Feel free to edit as you please, and have fun.
 *
 * @author Marc Morera <yuhu@mmoreram.com>
 */

declare(strict_types=1);

namespace Drift\EventBus\Serializer;

/**
 * Class PHPEventSerializer.
 */
class PHPEventSerializer implements EventSerializer
{
    /**
     * Serialize.
     *
     * @param $eventName
     * @param $event
     *
     * @return string
     */
    public static function serialize(
        string $eventName,
        $event
    ): string {
        return json_encode([
            'event_name' => $eventName,
            'event' => \serialize($event),
        ]);
    }

    /**
     * Unserialize.
     *
     * @param string $data
     *
     * @return array
     */
    public static function unserialize(string $data): array
    {
        $decodedData = json_decode($data, true);

        return [
            $decodedData['event_name'],
            \unserialize($decodedData['event']),
        ];
    }
}
