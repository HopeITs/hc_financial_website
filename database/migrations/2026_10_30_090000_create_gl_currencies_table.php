<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Financial Manager decision R4-4: the books are not fixed to USD and SYP. A currency is a row; a new donor currency is
     * added on the currencies screen and then works like the others. USD stays the base: every line keeps its USD value.
     *
     * `quote` says how the rate of a currency is typed and stored: `per_usd` = units of the currency for one dollar (SYP,
     * 15,000), `usd_per_unit` = dollars for one unit (EUR, 1.14).
     */
    public function up(): void
    {
        Schema::create('gl_currencies', function (Blueprint $table) {
            $table->string('code', 3)->primary();
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('quote', 12)->default('per_usd');
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('gl_currencies')->insert([
            ['code' => 'USD', 'name_en' => 'US dollar', 'name_ar' => 'دولار أمريكي', 'quote' => 'per_usd', 'is_base' => true, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'SYP', 'name_en' => 'Syrian pound', 'name_ar' => 'ليرة سورية', 'quote' => 'per_usd', 'is_base' => false, 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'EUR', 'name_en' => 'Euro', 'name_ar' => 'يورو', 'quote' => 'usd_per_unit', 'is_base' => false, 'is_active' => true, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_currencies');
    }
};
