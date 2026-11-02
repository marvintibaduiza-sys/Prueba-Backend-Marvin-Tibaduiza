<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->char('code', 3)->unique()->comment('ISO 4217 code');
            $table->string('symbol', 5);
            $table->decimal('exchange_rate', 12, 6)->comment('Units of this currency per 1 unit of the base currency');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
