<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The install's own marks. `logo` already exists and belongs to the printed
     * page — a wide wordmark at the top of a PDF. These four are the screen:
     * the square icon in the header, sidebar and login card, its dark-mode
     * twin, the browser-tab favicon, and the short name shown beside them.
     *
     * All nullable, all falling back to the product's own mark, so an install
     * that uploads nothing looks exactly as it does today.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('brand_name')->nullable()->after('name');
            $table->string('app_icon')->nullable()->after('logo');
            $table->string('app_icon_dark')->nullable()->after('app_icon');
            $table->string('favicon')->nullable()->after('app_icon_dark');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['brand_name', 'app_icon', 'app_icon_dark', 'favicon']);
        });
    }
};
