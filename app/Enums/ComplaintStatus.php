<?php

namespace App\Enums;

enum ComplaintStatus: string
{
    case New = 'new';
    case Responded = 'responded';
    case Resolved = 'resolved';
}
