<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class CreateModerationPostsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->increments('id');
            $table->enum('status', [
                config('moderation.status.pending'),
                config('moderation.status.approved'),
                config('moderation.status.rejected'),
                config('moderation.status.postponed')
            ])->default(config('moderation.status.pending'));
            $table->dateTime('moderated_at');
            $table->integer('moderated_by')->nullable()->unsigned();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('posts');
    }
}
