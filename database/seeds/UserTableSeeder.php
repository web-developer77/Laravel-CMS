<?php

use Illuminate\Database\Seeder;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = \App\User::create([
            'title' => 'Mr',
            'firstName' => 'admin',
            'lastName' => 'admin',
            'role' => 'Admin',
            'email' => 'admin@metroengine.com.au',
            'password' => app('hash')->make('12345678'),
        ]);

    }
}
