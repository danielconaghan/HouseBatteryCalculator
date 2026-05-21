<?php

declare(strict_types=1);

namespace App\Enums;

enum RecommendationAction: string
{
    case Charge        = 'CHARGE';
    case PartialCharge = 'PARTIAL_CHARGE';
    case DoNotCharge   = 'DO_NOT_CHARGE';
}
