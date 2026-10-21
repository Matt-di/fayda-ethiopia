<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('fayda.persistence.table', 'fayda_identities');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('subject');
            $table->string('client_id')->nullable();
            $table->json('claims')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->unique(['subject', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('fayda.persistence.table', 'fayda_identities'));
    }
};
