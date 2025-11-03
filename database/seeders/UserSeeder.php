<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Créer un administrateur
        User::updateOrCreate(
            ['email' => 'admin@gestion-compte.com'],
            [
                'name' => 'Admin User',
                'email_verified_at' => now(),
                'password' => bcrypt('admin123'),
                'role' => 'admin',
                'is_active' => true,
                'remember_token' => Str::random(10),
            ]
        );

        // Créer des agents
        User::updateOrCreate(
            ['email' => 'agent1@gestion-compte.com'],
            [
                'name' => 'Agent Dupont',
                'email_verified_at' => now(),
                'password' => bcrypt('agent123'),
                'role' => 'agent',
                'is_active' => true,
                'remember_token' => Str::random(10),
            ]
        );

        User::updateOrCreate(
            ['email' => 'agent2@gestion-compte.com'],
            [
                'name' => 'Agent Martin',
                'email_verified_at' => now(),
                'password' => bcrypt('agent123'),
                'role' => 'agent',
                'is_active' => true,
                'remember_token' => Str::random(10),
            ]
        );

        // Créer des utilisateurs clients
        User::factory(7)->make()->each(function ($user) {
    User::updateOrCreate(
        ['email' => $user->email], // évite les doublons
        array_merge($user->toArray(), [
            'password' => bcrypt('client123'), // mot de passe par défaut pour tous les clients
            'email_verified_at' => now(),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ])
    );
});

    }
}
