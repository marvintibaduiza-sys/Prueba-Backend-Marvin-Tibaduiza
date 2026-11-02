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
            $table->string('name', 50)->unique()->comment('Unique identifier of the currency, e.g. USD');
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
