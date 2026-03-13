<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_service_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_service_id')
                ->constrained('user_services')
                ->cascadeOnDelete();

            $table->string('document_type', 50); // profile_photo, aadhaar_front, pan_card, etc
            $table->string('file_path', 500);
            $table->string('file_name', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            $table->enum('status', [
                'uploaded',
                'verified',
                'rejected',
            ])->default('uploaded');

            $table->text('remarks')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index('user_service_id');
            $table->index('document_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_service_documents');
    }
};
