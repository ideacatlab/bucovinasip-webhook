<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone')->nullable()->after('email');
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('users', function (Blueprint $table) {
                $table->string('full_name')
                    ->virtualAs("TRIM(CONCAT(COALESCE(`first_name`, ''), ' ', COALESCE(`last_name`, '')))")
                    ->after('last_name');
            });
        } elseif (DB::getDriverName() === 'pgsql') {
            Schema::table('users', function (Blueprint $table) {
                $table->string('full_name')
                    ->storedAs("trim(concat(coalesce(first_name,''),' ',coalesce(last_name,'')))")
                    ->after('last_name');
            });
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->string('full_name')->nullable()->after('last_name');
            });
        }

        if (Schema::hasColumn('users', 'name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('name');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('name')->nullable()->after('id');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'full_name')) {
                $table->dropColumn('full_name');
            }

            if (Schema::hasColumn('users', 'phone')) {
                $table->dropColumn('phone');
            }
            if (Schema::hasColumn('users', 'last_name')) {
                $table->dropColumn('last_name');
            }
            if (Schema::hasColumn('users', 'first_name')) {
                $table->dropColumn('first_name');
            }
        });
    }
};
