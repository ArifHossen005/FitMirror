<?php

namespace Database\Seeders;

use App\Enums\AddonStatus;
use App\Enums\AddonType;
use App\Models\Addon;
use Illuminate\Database\Seeder;

/**
 * The four add-on packs named in PROGRESS.md Phase 3.D's checklist (SMS
 * pack, storage pack, priority support, template pack). Idempotent via
 * updateOrCreate(), same pattern as PlanSeeder — safe to re-run if a price
 * changes.
 */
class AddonSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAddon(
            code: 'sms_pack_500',
            name: 'SMS Pack (500)',
            description: '500 additional SMS credits for OTP, order updates, and campaign messages.',
            type: AddonType::Sms,
            price: 500,
            unitAmount: 500,
            sortOrder: 1,
        );

        $this->seedAddon(
            code: 'storage_pack_10gb',
            name: 'Storage Pack (10 GB)',
            description: '10 GB of additional media storage for product photos, garment assets, and try-on snapshots.',
            type: AddonType::Storage,
            price: 300,
            unitAmount: 10,
            sortOrder: 2,
        );

        $this->seedAddon(
            code: 'priority_support_30d',
            name: 'Priority Support (30 days)',
            description: '30 days of priority email/chat support with a guaranteed same-day response.',
            type: AddonType::Support,
            price: 999,
            unitAmount: 30,
            sortOrder: 3,
        );

        $this->seedAddon(
            code: 'template_pack_10',
            name: 'Campaign Template Pack (10)',
            description: '10 additional pre-designed campaign templates for social media and SMS.',
            type: AddonType::Template,
            price: 799,
            unitAmount: 10,
            sortOrder: 4,
        );
    }

    private function seedAddon(
        string $code,
        string $name,
        string $description,
        AddonType $type,
        int $price,
        int $unitAmount,
        int $sortOrder,
    ): void {
        Addon::query()->updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'description' => $description,
                'type' => $type,
                'price' => $price,
                'currency' => 'BDT',
                'unit_amount' => $unitAmount,
                'status' => AddonStatus::Active,
                'sort_order' => $sortOrder,
            ],
        );
    }
}
