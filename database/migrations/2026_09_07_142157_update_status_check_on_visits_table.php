<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->enum('status', [
                'REGISTERED',
                'EXAMINED',
                'WAITING_PAYMENT',
                'COMPLETED',
                'CANCELLED'
            ])->default('REGISTERED');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->enum('status', [
                'REGISTERED',
                'EXAMINED',
                'COMPLETED',
                'CANCELLED'
            ])->default('REGISTERED');
        });
    }
};