<?php

namespace Database\Seeders;

use App\Models\TariffBand;
use App\Models\ApplianceType;
use Illuminate\Database\Seeder;

class VoltWatchSeeder extends Seeder
{
    public function run(): void
    {
        // ── Tariff Bands (NERC 2024 rates) ────────────────────────────────
        $bands = [
            ['code' => 'A', 'name' => 'Band A', 'rate_per_kwh' => 225.00, 'min_supply_hours' => 20, 'description' => '≥20 hours supply/day'],
            ['code' => 'B', 'name' => 'Band B', 'rate_per_kwh' => 63.34,  'min_supply_hours' => 16, 'description' => '≥16 hours supply/day'],
            ['code' => 'C', 'name' => 'Band C', 'rate_per_kwh' => 50.27,  'min_supply_hours' => 12, 'description' => '≥12 hours supply/day'],
            ['code' => 'D', 'name' => 'Band D', 'rate_per_kwh' => 44.97,  'min_supply_hours' => 8,  'description' => '≥8 hours supply/day'],
            ['code' => 'E', 'name' => 'Band E', 'rate_per_kwh' => 36.94,  'min_supply_hours' => 0,  'description' => '<8 hours supply/day'],
        ];

        foreach ($bands as $band) {
            TariffBand::updateOrCreate(['code' => $band['code']], array_merge($band, ['is_active' => true, 'effective_from' => '2024-04-01']));
        }

        // ── Appliance Types ────────────────────────────────────────────────
        $appliances = [
            // Cooling
            ['name' => 'Air Conditioner (1hp)',   'slug' => 'ac_1hp',      'category' => 'cooling',       'icon_name' => 'Snowflake',   'avg_wattage' => 746,  'min_wattage' => 700,  'max_wattage' => 850,  'is_always_on' => false, 'sort_order' => 1],
            ['name' => 'Air Conditioner (1.5hp)', 'slug' => 'ac_15hp',     'category' => 'cooling',       'icon_name' => 'Snowflake',   'avg_wattage' => 1119, 'min_wattage' => 1000, 'max_wattage' => 1300, 'is_always_on' => false, 'sort_order' => 2],
            ['name' => 'Air Conditioner (2hp)',   'slug' => 'ac_2hp',      'category' => 'cooling',       'icon_name' => 'Snowflake',   'avg_wattage' => 1492, 'min_wattage' => 1300, 'max_wattage' => 1700, 'is_always_on' => false, 'sort_order' => 3],
            ['name' => 'Ceiling Fan',             'slug' => 'ceiling_fan', 'category' => 'cooling',       'icon_name' => 'Wind',        'avg_wattage' => 75,   'min_wattage' => 50,   'max_wattage' => 100,  'is_always_on' => false, 'sort_order' => 4],
            ['name' => 'Standing Fan',            'slug' => 'stand_fan',   'category' => 'cooling',       'icon_name' => 'Wind',        'avg_wattage' => 55,   'min_wattage' => 40,   'max_wattage' => 80,   'is_always_on' => false, 'sort_order' => 5],

            // Kitchen
            ['name' => 'Refrigerator (single door)', 'slug' => 'fridge_single', 'category' => 'kitchen', 'icon_name' => 'Square',      'avg_wattage' => 100,  'min_wattage' => 80,   'max_wattage' => 150,  'is_always_on' => true,  'sort_order' => 10],
            ['name' => 'Refrigerator (double door)', 'slug' => 'fridge_double', 'category' => 'kitchen', 'icon_name' => 'Square',      'avg_wattage' => 200,  'min_wattage' => 150,  'max_wattage' => 300,  'is_always_on' => true,  'sort_order' => 11],
            ['name' => 'Deep Freezer',            'slug' => 'deep_freezer', 'category' => 'kitchen',     'icon_name' => 'Box',         'avg_wattage' => 300,  'min_wattage' => 200,  'max_wattage' => 450,  'is_always_on' => true,  'sort_order' => 12],
            ['name' => 'Electric Cooker (2-plate)', 'slug' => 'cooker_2',  'category' => 'kitchen',     'icon_name' => 'Flame',       'avg_wattage' => 2000, 'min_wattage' => 1500, 'max_wattage' => 3000, 'is_always_on' => false, 'sort_order' => 13],
            ['name' => 'Microwave',               'slug' => 'microwave',   'category' => 'kitchen',       'icon_name' => 'Zap',         'avg_wattage' => 900,  'min_wattage' => 700,  'max_wattage' => 1200, 'is_always_on' => false, 'sort_order' => 14],
            ['name' => 'Water Dispenser',         'slug' => 'dispenser',   'category' => 'kitchen',       'icon_name' => 'Droplets',    'avg_wattage' => 500,  'min_wattage' => 400,  'max_wattage' => 700,  'is_always_on' => true,  'sort_order' => 15],
            ['name' => 'Electric Kettle',         'slug' => 'kettle',      'category' => 'kitchen',       'icon_name' => 'Coffee',      'avg_wattage' => 1800, 'min_wattage' => 1500, 'max_wattage' => 2200, 'is_always_on' => false, 'sort_order' => 16],
            ['name' => 'Blender',                 'slug' => 'blender',     'category' => 'kitchen',       'icon_name' => 'Zap',         'avg_wattage' => 350,  'min_wattage' => 250,  'max_wattage' => 500,  'is_always_on' => false, 'sort_order' => 17],

            // Lighting
            ['name' => 'LED Bulb',                'slug' => 'led_bulb',    'category' => 'lighting',      'icon_name' => 'Lightbulb',   'avg_wattage' => 9,    'min_wattage' => 5,    'max_wattage' => 18,   'is_always_on' => false, 'sort_order' => 20],
            ['name' => 'Energy Saver Bulb',       'slug' => 'energy_bulb', 'category' => 'lighting',      'icon_name' => 'Lightbulb',   'avg_wattage' => 20,   'min_wattage' => 11,   'max_wattage' => 30,   'is_always_on' => false, 'sort_order' => 21],
            ['name' => 'Fluorescent Tube',        'slug' => 'fluoro_tube', 'category' => 'lighting',      'icon_name' => 'Minus',       'avg_wattage' => 36,   'min_wattage' => 18,   'max_wattage' => 58,   'is_always_on' => false, 'sort_order' => 22],

            // Entertainment & Electronics
            ['name' => 'Television (32")',        'slug' => 'tv_32',       'category' => 'entertainment',  'icon_name' => 'Monitor',     'avg_wattage' => 60,   'min_wattage' => 40,   'max_wattage' => 80,   'is_always_on' => false, 'sort_order' => 30],
            ['name' => 'Television (43–50")',     'slug' => 'tv_43',       'category' => 'entertainment',  'icon_name' => 'Monitor',     'avg_wattage' => 100,  'min_wattage' => 80,   'max_wattage' => 130,  'is_always_on' => false, 'sort_order' => 31],
            ['name' => 'Decoder / Set-Top Box',   'slug' => 'decoder',     'category' => 'entertainment',  'icon_name' => 'Wifi',        'avg_wattage' => 15,   'min_wattage' => 10,   'max_wattage' => 25,   'is_always_on' => true,  'sort_order' => 32],
            ['name' => 'WiFi Router / Modem',     'slug' => 'wifi_router', 'category' => 'entertainment',  'icon_name' => 'Wifi',        'avg_wattage' => 10,   'min_wattage' => 7,    'max_wattage' => 20,   'is_always_on' => true,  'sort_order' => 33],
            ['name' => 'Laptop',                  'slug' => 'laptop',      'category' => 'entertainment',  'icon_name' => 'Laptop',      'avg_wattage' => 65,   'min_wattage' => 30,   'max_wattage' => 100,  'is_always_on' => false, 'sort_order' => 34],
            ['name' => 'Phone Charger',           'slug' => 'phone_charger','category' => 'entertainment', 'icon_name' => 'Smartphone',  'avg_wattage' => 10,   'min_wattage' => 5,    'max_wattage' => 25,   'is_always_on' => false, 'sort_order' => 35],

            // Water / Laundry
            ['name' => 'Water Pump (0.5hp)',      'slug' => 'pump_half',   'category' => 'water',          'icon_name' => 'Droplets',    'avg_wattage' => 373,  'min_wattage' => 300,  'max_wattage' => 450,  'is_always_on' => false, 'sort_order' => 40],
            ['name' => 'Water Pump (1hp)',        'slug' => 'pump_1hp',    'category' => 'water',          'icon_name' => 'Droplets',    'avg_wattage' => 746,  'min_wattage' => 600,  'max_wattage' => 900,  'is_always_on' => false, 'sort_order' => 41],
            ['name' => 'Washing Machine',         'slug' => 'washing_machine','category' => 'water',       'icon_name' => 'RefreshCw',   'avg_wattage' => 500,  'min_wattage' => 300,  'max_wattage' => 800,  'is_always_on' => false, 'sort_order' => 42],
            ['name' => 'Electric Iron',           'slug' => 'iron',        'category' => 'other',          'icon_name' => 'Zap',         'avg_wattage' => 1200, 'min_wattage' => 800,  'max_wattage' => 2400, 'is_always_on' => false, 'sort_order' => 50],
            ['name' => 'Inverter / UPS',          'slug' => 'inverter',    'category' => 'other',          'icon_name' => 'Battery',     'avg_wattage' => 200,  'min_wattage' => 100,  'max_wattage' => 3000, 'is_always_on' => true,  'sort_order' => 51],
        ];

        foreach ($appliances as $appliance) {
            ApplianceType::updateOrCreate(['slug' => $appliance['slug']], $appliance);
        }

        $this->command->info('✅ VoltWatch seed data loaded: ' . count($bands) . ' tariff bands, ' . count($appliances) . ' appliance types');
    }
}
