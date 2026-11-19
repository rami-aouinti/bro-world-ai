<?php

declare(strict_types=1);

namespace App\Enum;

enum ContentIdeaStatus: string
{
    case Draft = 'draft';
    case ReadyForPlanning = 'ready_for_planning';
    case Review = 'review';
    case Approved = 'approved';
    case HandedOff = 'handed_off';
}
