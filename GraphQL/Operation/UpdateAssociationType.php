<?php

namespace Orkester\GraphQL\Operation;

enum UpdateAssociationType
{
    case Replace;
    case Append;
    case Remove;
    case Clear;
}
