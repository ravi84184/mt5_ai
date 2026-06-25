<?php

namespace App\Enums;

enum SignalStatus: string
{
    case Pending = 'PENDING';
    case Executed = 'EXECUTED';
    case Rejected = 'REJECTED';
    case Closed = 'CLOSED';
}
