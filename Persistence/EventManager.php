<?php

namespace Orkester\Persistence;

use Carbon\Carbon;
use Orkester\Persistence\Enum\EventType;
use Orkester\Security\Role;
use Ramsey\Uuid\Uuid;

class EventManager
{
    protected static array $events = [];

    public static function createEvent(EventType $event, Role $role, ?array $old, ?array $new, string $modelOrTablename)
    {
        static::$events[] = [
            'id' => Uuid::uuid7()->toString(),
            'created_at' => Carbon::now()->unix(),
            'table' => $modelOrTablename,
            'event' => [
                'op' => $event->value,
                'data' => [
                    'old' => $old,
                    'new' => $new
                ],
                'role' => $role->serialize()
            ]
        ];
    }

    public static function flush(): array
    {
        $e = static::$events;
        static::$events = [];
        return $e;
    }

}
