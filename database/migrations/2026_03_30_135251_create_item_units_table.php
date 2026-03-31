<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('item_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('item_categories')->onDelete('cascade');
            $table->string('serial_number')->unique();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->string('individual');
            $table->text('description')->nullable();
            $table->integer('stock')->default(0);
            $table->string('image_url')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('serial_number');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_units');
    }
};
