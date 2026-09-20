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
        Schema::create('article_tag', function (Blueprint $table) {
            $table->foreignUuid('id_article')->constrained('articles', 'id_article')->cascadeOnDelete();
            $table->foreignUuid('id_tag')->constrained('tags', 'id_tag')->cascadeOnDelete();
            $table->primary(['id_article', 'id_tag']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_tag');
    }
};
