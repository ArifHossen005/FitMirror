<?php

namespace App\Enums;

/**
 * Backed enum, not a MySQL ENUM column, per DOCUMENTATION.md §4.4.1. The
 * four packs named in the PROGRESS.md Phase 3.D checklist.
 */
enum AddonType: string
{
    case Sms = 'sms';
    case Storage = 'storage';
    case Support = 'support';
    case Template = 'template';
}
