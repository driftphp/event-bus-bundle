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
 * Interface EventSerializer.
 */
interface EventSerializer
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
    ): string;

    /**
     * Unserialize.
     *
     * @param string $data
     *
     * @return array
     */
    public static function unserialize(string $data): array;
}
