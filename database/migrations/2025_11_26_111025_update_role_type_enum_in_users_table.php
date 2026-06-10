<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE `users` MODIFY `role_type` ENUM('admin','user','crypto') DEFAULT 'user'");
    }

    public function down()
    {
        DB::statement("ALTER TABLE `users` MODIFY `role_type` ENUM('admin','user') DEFAULT 'user'");
    }
};

