<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use DB;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('roles')->insert([
            'name' => "barber",//ID 1
        ]);
        DB::table('roles')->insert([
            'name' => "client",//ID 2
        ]);
    }
}
