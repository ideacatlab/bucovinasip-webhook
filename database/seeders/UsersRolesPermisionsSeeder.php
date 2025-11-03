<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersRolesPermisionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $uuid = '0199442b-eb62-71a6-ad56-f55fba3c7e32';

        $role = DB::table('hexa_roles')->where('uuid', $uuid)->first();

        if (! $role) {
            $roleId = DB::table('hexa_roles')->insertGetId([
                'uuid' => $uuid,
                'name' => 'Admin',
                'team_id' => null,
                'created_by_name' => 'admin',
                'access' => json_encode([
                    'role_permissions' => [
                        'role.index',
                        'role.create',
                        'role.update',
                        'role.delete',
                    ],
                ]),
                'guard' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $roleId = $role->id;

            DB::table('hexa_roles')
                ->where('id', $roleId)
                ->update([
                    'name' => 'Admin',
                    'access' => json_encode([
                        'role_permissions' => [
                            'role.index',
                            'role.create',
                            'role.update',
                            'role.delete',
                        ],
                    ]),
                    'guard' => 'web',
                    'updated_at' => now(),
                ]);
        }

        $user = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'phone' => '+40 723 123 456',
                'password' => Hash::make('password'),
            ]
        );

        if (is_null($user->email_verified_at)) {
            $user->email_verified_at = now();
            $user->save();
        }

        $user->roles()->syncWithoutDetaching([$roleId]);
    }
}
