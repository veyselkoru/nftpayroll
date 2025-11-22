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
            $table->string('ipfs_cid')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('nft_mints', function (Blueprint $table) {
            $table->string('ipfs_cid')->nullable(false)->change();
        });
    }

};
