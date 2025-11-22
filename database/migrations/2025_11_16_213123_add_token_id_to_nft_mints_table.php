<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('nft_mints', function (Blueprint $table) {
            $table->unsignedBigInteger('token_id')->nullable()->after('tx_hash');
        });
    }

    public function down()
    {
        Schema::table('nft_mints', function (Blueprint $table) {
            $table->dropColumn('token_id');
        });
    }

};
