<?php

namespace Orkester\Persistence\Enum;

enum EventType: string
{
    case UPDATE = "UPDATE";
    case UPSERT = "UPSERT";
    case INSERT = "INSERT";
    case DELETE = "DELETE";
}
