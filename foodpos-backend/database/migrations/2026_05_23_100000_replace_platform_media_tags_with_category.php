<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_media', function (Blueprint $table) {
            $table->string('category')->nullable()->after('file_path');
        });

        if (Schema::hasColumn('platform_media', 'tags')) {
            foreach (DB::table('platform_media')->whereNotNull('tags')->where('tags', '!=', '')->get() as $row) {
                $category = trim(explode(',', (string) $row->tags)[0]);
                if ($category !== '') {
                    DB::table('platform_media')->where('id', $row->id)->update(['category' => $category]);
                }
            }

            Schema::table('platform_media', function (Blueprint $table) {
                $table->dropColumn('tags');
            });
        }

        Schema::table('platform_media', function (Blueprint $table) {
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::table('platform_media', function (Blueprint $table) {
            $table->string('tags')->nullable()->after('file_path');
        });

        foreach (DB::table('platform_media')->whereNotNull('category')->get() as $row) {
            DB::table('platform_media')->where('id', $row->id)->update(['tags' => $row->category]);
        }

        Schema::table('platform_media', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};
