<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->id();

            $table->string('title', 150);
            $table->string('subtitle', 255)->nullable();

            $table->string('image_path');

            // myday_likes, shop_top, services_top
            $table->string('placement', 50)->index();

            // none, product, category, service, internal_route, external_url
            $table->string('action_type', 50)->default('none');
            $table->string('action_value')->nullable();

            $table->string('button_text', 50)->nullable();

            $table->dateTime('start_at')->nullable()->index();
            $table->dateTime('end_at')->nullable()->index();

            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true)->index();

            $table->unsignedBigInteger('impressions_count')->default(0);
            $table->unsignedBigInteger('clicks_count')->default(0);

            $table->timestamps();

            $table->index([
                'placement',
                'is_active',
                'start_at',
                'end_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};
