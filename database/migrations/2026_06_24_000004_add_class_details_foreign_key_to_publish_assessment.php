<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publish_assessment', function (Blueprint $table) {
            $table->foreign('class_details_id', 'publish_assessment_class_details_id_foreign')
                ->references('class_details_id')
                ->on('class_details')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('publish_assessment', function (Blueprint $table) {
            $table->dropForeign('publish_assessment_class_details_id_foreign');
        });
    }
};
