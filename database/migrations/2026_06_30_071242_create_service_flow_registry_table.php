<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_flow_registry', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('service_id');

            /*
                examples:
                electrician.inverter_issue
                electrician.fan_installation
                carpenter.wardrobe_build
                medical.physiotherapy
            */
            $table->string('flow_key', 150);

            $table->string('title')->nullable();
            $table->text('description')->nullable();

            // Questions Mitra should ask customer
            $table->json('request_schema')->nullable();

            // Fields provider should respond with
            $table->json('response_schema')->nullable();

            // AI conversation questions
            $table->json('ai_questions')->nullable();

            // Estimate/cost rules
            $table->json('estimate_rules')->nullable();

            // Matching rules: zone, skill, tags, experience etc.
            $table->json('matching_rules')->nullable();

            $table->string('version')->default('1.0');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['service_id', 'flow_key']);
            $table->index(['flow_key']);
            $table->index(['service_id', 'is_active']);

            $table->foreign('service_id')
                ->references('service_id')
                ->on('services')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_flow_registry');
    }
};
