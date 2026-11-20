<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['nama' => 'Pak Hendra (Owner Toko)', 'username' => 'owner', 'password' => 'owner123', 'role' => 'owner'],
            ['nama' => 'Siti Rahma (Admin Kasir)', 'username' => 'admin', 'password' => 'admin123', 'role' => 'admin'],
            ['nama' => 'Budi Santoso (Teknisi Laptop)', 'username' => 'budi_teknisi', 'password' => 'teknisi123', 'role' => 'teknisi'],
        ] as $account) {
            DB::table('users')->updateOrInsert(
                ['username' => $account['username']],
                [
                    'nama' => $account['nama'],
                    'password' => Hash::make($account['password']),
                    'role' => $account['role'],
                    'must_change_password' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
