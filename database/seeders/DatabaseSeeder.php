<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Device;
use App\Models\EmergencyMessage;
use App\Models\Playlist;
use App\Models\Ticker;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Branch
        $branch = Branch::updateOrCreate(
            ['name' => 'Главная клиника'],
            [
                'address' => null,
                'timezone' => 'Asia/Baku',
                'status' => 'active',
            ]
        );

        // Users
        $super = User::updateOrCreate(
            ['email' => 'super@clinic.local'],
            [
                'name' => 'Главный администратор',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'branch_id' => $branch->id,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@clinic.local'],
            [
                'name' => 'Администратор филиала',
                'password' => Hash::make('password'),
                'role' => 'branch_admin',
                'branch_id' => $branch->id,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'content@clinic.local'],
            [
                'name' => 'Контент-менеджер',
                'password' => Hash::make('password'),
                'role' => 'content_manager',
                'branch_id' => $branch->id,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'viewer@clinic.local'],
            [
                'name' => 'Наблюдатель',
                'password' => Hash::make('password'),
                'role' => 'viewer',
                'branch_id' => $branch->id,
                'is_active' => true,
            ]
        );

        // Zones
        $reception = Zone::updateOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Ресепшен'],
            ['description' => null, 'sort_order' => 1]
        );

        $waiting = Zone::updateOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Зал ожидания'],
            ['description' => null, 'sort_order' => 2]
        );

        $corridor = Zone::updateOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Коридор 2 этаж'],
            ['description' => null, 'sort_order' => 3]
        );

        // Devices
        Device::updateOrCreate(
            ['device_code' => 'TV-01'],
            [
                'branch_id' => $branch->id,
                'zone_id' => $reception->id,
                'name' => 'TV-01',
                'pairing_code' => 'A7K9-22',
                'api_token' => null,
                'platform' => 'browser',
                'device_type' => 'browser_player',
                'status' => 'offline',
            ]
        );

        Device::updateOrCreate(
            ['device_code' => 'TV-02'],
            [
                'branch_id' => $branch->id,
                'zone_id' => $waiting->id,
                'name' => 'TV-02',
                'pairing_code' => null,
                'api_token' => str_repeat('a2', 32),
                'platform' => 'browser',
                'device_type' => 'browser_player',
                'status' => 'online',
                'last_seen_at' => now(),
            ]
        );

        Device::updateOrCreate(
            ['device_code' => 'TV-03'],
            [
                'branch_id' => $branch->id,
                'zone_id' => $corridor->id,
                'name' => 'TV-03',
                'pairing_code' => null,
                'api_token' => str_repeat('b3', 32),
                'platform' => 'browser',
                'device_type' => 'browser_player',
                'status' => 'offline',
            ]
        );

        // Ticker
        Ticker::updateOrCreate(
            ['text' => 'Уважаемые пациенты! Приём по предварительной записи. Скидка 15% на check-up до конца месяца.'],
            [
                'branch_id' => $branch->id,
                'position' => 'bottom',
                'is_active' => true,
                'target_type' => 'all',
            ]
        );

        // Emergency message
        EmergencyMessage::updateOrCreate(
            ['text' => 'Сегодня лаборатория работает до 16:00.'],
            [
                'target_type' => 'all',
                'is_active' => false,
                'created_by' => $super->id,
            ]
        );

        // Playlist
        Playlist::updateOrCreate(
            ['name' => 'Зал ожидания — утро'],
            [
                'branch_id' => $branch->id,
                'status' => 'active',
                'created_by' => $super->id,
            ]
        );
    }
}
