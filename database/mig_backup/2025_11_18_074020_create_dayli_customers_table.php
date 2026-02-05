<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('dayli_customers', function (Blueprint $table) {
      $table->id();

      // Link to global Ops (no FK if different DB/connection)
      $table->unsignedBigInteger('ops_customer_id')->nullable()->index();

      // Shopify customer id with UNIQUE index
      $table->unsignedBigInteger('shopify_customer_id')->nullable();
      $table->unique('shopify_customer_id', 'dayli_customers_shopify_uid');

      $table->enum('account_status', ['ghost', 'active', 'blocked'])->default('active');
      $table->enum('origin_system', ['dayli', 'leela', 'ops'])->default('dayli');
      $table->timestamp('last_logged_at')->nullable();

      // Customer info
      $table->string('first_name')->nullable();
      $table->string('last_name')->nullable();
      $table->string('display_name')->nullable();
      $table->string('phone')->nullable();
      $table->string('email')->nullable();

      // 👉 NEW FIELDS (plain columns for metafields)
      $table->string('nagar')->nullable();
      $table->string('address')->nullable();
      $table->string('pincode')->nullable();
      $table->string('zone_code')->nullable();

      // Generated normalized phone
      $table->string('phone_normalized', 24)->storedAs("
                CASE WHEN COALESCE(`phone`, '') = '' 
                     THEN NULL 
                     ELSE CONCAT('+', REGEXP_REPLACE(`phone`, '[^0-9]', '')) 
                END
            ");

      // Shopify-style / profile fields
      $table->json('default_address_json')->nullable();
      $table->text('image_url')->nullable();
      $table->string('locale', 16)->nullable();
      $table->unsignedBigInteger('number_of_orders')->default(0);
      $table->decimal('amount_spent', 12, 2)->default(0.00);
      $table->boolean('tax_exempt')->default(false);
      $table->decimal('total_amount_due', 12, 2)->default(0.00);
      $table->text('tags')->nullable();
      $table->text('note')->nullable();
      $table->string('originating_from', 64)->nullable();
      $table->json('should_sync_with')->nullable();
      $table->json('sync_completed_with')->nullable();
      $table->string('profile_metaobject_gid')->nullable();

      // Natural key (generated) — ⚠️ NO ->unique() here
      $table->string('natural_key', 128)->storedAs("
                CASE 
                  WHEN COALESCE(`phone`, '') <> '' 
                    THEN REGEXP_REPLACE(LOWER(`phone`), '[^0-9+]', '') 
                  WHEN COALESCE(`email`, '') <> '' 
                    THEN LOWER(`email`) 
                  WHEN COALESCE(`shopify_customer_id`, 0) <> 0
                    THEN CONCAT('shopify:', `shopify_customer_id`)
                  ELSE SHA2(
                        CONCAT_WS('|',
                          COALESCE(`first_name`, ''),
                          COALESCE(`last_name`, ''),
                          COALESCE(JSON_UNQUOTE(JSON_EXTRACT(`default_address_json`, '$.address1')), ''),
                          COALESCE(JSON_UNQUOTE(JSON_EXTRACT(`default_address_json`, '$.zip')), '')
                        ), 256)
                END
            ");

      $table->timestamps();
      $table->softDeletes();

      // Helpful indexes
      $table->index('phone', 'dayli_customers_phone_idx');
      $table->index('email', 'dayli_customers_email_idx');
    });

    // If Dayli and Ops share same DB connection, you may add FK:
    //
    // Schema::table('dayli_customers', function (Blueprint $table) {
    //     $table->foreign('ops_customer_id')
    //           ->references('id')
    //           ->on('ops_customers')
    //           ->nullOnDelete();
    // });
  }

  public function down(): void
  {
    Schema::dropIfExists('dayli_customers');
  }
};
