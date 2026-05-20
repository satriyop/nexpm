<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Subcontractor = 'subcontractor';
    case Drafter = 'drafter';
}
