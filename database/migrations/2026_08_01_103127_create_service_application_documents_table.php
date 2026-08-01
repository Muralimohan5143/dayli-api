<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_application_documents', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Polymorphic owner
            |--------------------------------------------------------------------------
            |
            | documentable_type:
            | App\Models\VendorZoneService
            | App\Models\WorkmanZoneService
            |
            | documentable_id:
            | vendor_zone_services.id
            | workman_zone_services.id
            |
            */

            $table->string('documentable_type', 150);
            $table->unsignedBigInteger('documentable_id');

            $table->string('document_type', 100);

            $table->string('file_path');
            $table->string('file_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            $table->enum('status', [
                'uploaded',
                'verified',
                'rejected',
            ])->default('uploaded')->index();

            $table->text('remarks')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(
                [
                    'documentable_type',
                    'documentable_id',
                ],
                'service_application_documents_owner_index'
            );

            $table->index(
                [
                    'documentable_type',
                    'documentable_id',
                    'document_type',
                ],
                'service_application_documents_type_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_application_documents');
    }
};
