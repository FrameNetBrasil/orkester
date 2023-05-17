<?php

namespace Orkester\GraphQL\Operation;

enum EventOperation: string
{
    case UPDATE = "UPDATE";
    case UPSERT = "UPSERT";
    case INSERT = "INSERT";
    case DELETE = "DELETE";
}
