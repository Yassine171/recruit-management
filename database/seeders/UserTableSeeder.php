<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->insert([
            'slug'=>Str::slug('yassine oussi'),
            'name'=>"yassine oussi",
            'email'=>"yassine@email.ma",
            'password'=>"$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi",
            'is_admin'=>true,
            'status'=>'active',
            'created_at'=>now(),
            'updated_at'=>now()
        ]);

        User::factory()->count(50)->create();
    }
}
