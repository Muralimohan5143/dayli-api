<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            // 🔐 Auth / app related
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->foreignId('zone_id')
                ->nullable()
                ->constrained('zones')
                ->nullOnDelete();

            // 🔗 External / ops ids
            $table->unsignedBigInteger('ops_customer_id')->nullable();
            $table->unsignedBigInteger('shopify_customer_id')->nullable();

            // 👤 Account state
            $table->enum('account_status', ['ghost', 'active', 'blocked'])
                ->default('active');
            $table->enum('origin_system', ['dayli', 'leela', 'ops'])
                ->default('dayli');
            $table->timestamp('last_logged_at')->nullable();

            // 🧍 Name & basic profile
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('gender')->nullable();

            // 📞 Contact
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            // 📍 Address (current / default)
            $table->string('nagar')->nullable();
            $table->string('address')->nullable();
            $table->string('pincode')->nullable();
            $table->string('zone_code')->nullable();

            // derived from phone (MySQL generated column)
            $table->string('phone_normalized', 24)->storedAs(
                "case " .
                "when (coalesce(`phone`, '') = '') " .
                "then null " .
                "else concat('+', regexp_replace(`phone`, '[^0-9]', '')) " .
                "end"
            );

            // Shopify / other systems default address JSON
            $table->json('default_address_json')->nullable();

            // 🖼 Media / profile
            $table->text('image_url')->nullable();
            $table->string('avatar')->nullable();

            // 🌐 locale / currency / marketing
            $table->string('locale', 16)->nullable();
            $table->string('currency')->nullable();
            $table->boolean('verified_email')->default(false);
            $table->boolean('tax_exempt')->default(false);
            $table->json('marketing_opt_in_level')->nullable();

            // 📊 Stats
            $table->unsignedBigInteger('number_of_orders')->default(0);
            $table->decimal('amount_spent', 12, 2)->default(0);
            $table->decimal('total_amount_due', 12, 2)->default(0);

            // 🏷 Tags / notes / bio
            $table->text('tags')->nullable();
            $table->text('note')->nullable();
            $table->text('bio')->nullable();

            // 🔁 Origin / sync meta
            $table->string('originating_from', 64)->nullable();
            $table->json('should_sync_with')->nullable();
            $table->json('sync_completed_with')->nullable();
            $table->string('profile_metaobject_gid')->nullable();

            // 💼 Extra profile fields from old users table
            $table->string('skills')->nullable();

            // 🕒 Shopify timestamps
            $table->timestamp('shopify_created_at')->nullable();
            $table->timestamp('shopify_updated_at')->nullable();

            // 🔑 Natural key (same logic as dayli_customers)
            $table->string('natural_key', 128)->storedAs(
                "case " .
                "when (coalesce(`phone`, '') <> '') " .
                    "then regexp_replace(lower(`phone`), '[^0-9+]', '') " .
                "when (coalesce(`email`, '') <> '') " .
                    "then lower(`email`) " .
                "when (coalesce(`shopify_customer_id`, 0) <> 0) " .
                    "then concat('shopify:', `shopify_customer_id`) " .
                "else sha2(" .
                    "concat_ws(" .
                        "'|'," .
                        "coalesce(`first_name`, '')," .
                        "coalesce(`last_name`, '')," .
                        "coalesce(json_unquote(json_extract(`default_address_json`, '$.address1')), '')," .
                        "coalesce(json_unquote(json_extract(`default_address_json`, '$.zip')), '')" .
                    ")," .
                    "256" .
                ") " .
                "end"
            );

            $table->timestamps();
            $table->softDeletes();

            // 🔑 Indexes / uniques
            $table->unique('shopify_customer_id', 'customers_shopify_uid');
            $table->index('phone', 'customers_phone_idx');
            $table->index('email', 'customers_email_idx');
            $table->index('ops_customer_id', 'customers_ops_customer_id_idx');
            $table->index('zone_id', 'customers_zone_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
