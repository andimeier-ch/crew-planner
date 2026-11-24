<?php

declare(strict_types=1);

namespace App\Enum;

enum SurveyStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
