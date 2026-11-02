<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('description');
            $table->decimal('price', 12, 2)->comment('Price in the product base currency');
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('tax_cost', 12, 2);
            $table->decimal('manufacturing_cost', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
