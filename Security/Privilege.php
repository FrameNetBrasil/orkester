<?php

namespace Orkester\Security;

enum Privilege: string
{
    case QUERY = 'query';
    case INSERT = 'insert';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case UPSERT = "upsert";
    case READ_FIELD = 'read_field';
}
