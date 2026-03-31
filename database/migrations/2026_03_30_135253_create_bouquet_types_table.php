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
        Schema::create('bouquet_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('bouquet_categories')->onDelete('cascade');
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_custom')->default(false);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bouquet_types');
    }
};
