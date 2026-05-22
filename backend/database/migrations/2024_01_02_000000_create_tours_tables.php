<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // pgvector + trigram are created by the docker init.sql, but call
        // again here so a host install (composer + artisan migrate, no docker)
        // also works.
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        $embeddingDim = (int) config('services.embeddings.dim', 384);

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tours', function (Blueprint $table) use ($embeddingDim) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('short_description', 500);
            $table->text('description');
            $table->unsignedSmallInteger('duration_days')->default(1);
            $table->unsignedSmallInteger('duration_hours')->nullable();
            $table->string('difficulty')->default('easy'); // easy | moderate | hard
            $table->string('cover_image')->nullable();
            $table->json('route_points'); // [[lat, lon, label?], ...]
            $table->json('route_center')->nullable(); // [lat, lon, zoom?]
            $table->json('highlights')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        DB::statement("ALTER TABLE tours ADD COLUMN embedding vector({$embeddingDim})");
        // IVFFlat needs data before it gives a useful plan; HNSW is the
        // recommended choice for small/medium catalogues and works out of
        // the box on pgvector >= 0.5.
        DB::statement('CREATE INDEX tours_embedding_hnsw ON tours USING hnsw (embedding vector_cosine_ops)');
        DB::statement('CREATE INDEX tours_title_trgm ON tours USING gin (title gin_trgm_ops)');
        DB::statement('CREATE INDEX tours_description_trgm ON tours USING gin (description gin_trgm_ops)');

        Schema::create('tour_category', function (Blueprint $table) {
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['tour_id', 'category_id']);
        });

        Schema::create('tour_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('alt')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tour_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('price', 10, 2);
            $table->string('currency', 8)->default('RUB');
            $table->unsignedInteger('seats_total')->default(0);
            $table->unsignedInteger('seats_available')->default(0);
            $table->timestamps();
            $table->index(['tour_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_dates');
        Schema::dropIfExists('tour_photos');
        Schema::dropIfExists('tour_category');
        Schema::dropIfExists('tours');
        Schema::dropIfExists('categories');
    }
};
