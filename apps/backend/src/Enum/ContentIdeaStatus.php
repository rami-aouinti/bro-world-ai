<?php

declare(strict_types=1);

namespace App\Enum;

enum ContentIdeaStatus: string
{
    case Draft = 'draft';
    case ReadyForPlanning = 'ready_for_planning';
}
