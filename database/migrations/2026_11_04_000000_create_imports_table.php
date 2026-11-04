<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('imports.table', 'imports'), function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            match (config('imports.user_key_type', 'integer')) {
                'uuid' => $table->nullableUuidMorphs('user'),
                'ulid' => $table->nullableUlidMorphs('user'),
                'string' => $this->nullableStringMorphs($table),
                default => $table->nullableMorphs('user'),
            };

            $table->string('type')->nullable()->index();
            $table->string('importer_class');
            $table->longText('importer');
            $table->string('name');
            $table->string('status', 20)->index();
            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->string('filename');
            $table->string('format', 10);
            $table->unsignedBigInteger('size')->nullable();
            $table->json('headings')->nullable();
            $table->json('mapping')->nullable();
            $table->string('duplicates', 20)->default('skip');
            $table->boolean('dry_run')->default(false);
            $table->unsignedBigInteger('total_rows')->nullable();
            $table->unsignedBigInteger('processed_rows')->default(0);
            $table->unsignedBigInteger('created_rows')->default(0);
            $table->unsignedBigInteger('updated_rows')->default(0);
            $table->unsignedBigInteger('skipped_rows')->default(0);
            $table->unsignedBigInteger('failed_rows')->default(0);
            $table->json('failures')->nullable();
            $table->string('report_path')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('imports.table', 'imports'));
    }

    protected function nullableStringMorphs(Blueprint $table): void
    {
        $table->string('user_type')->nullable();
        $table->string('user_id')->nullable();
        $table->index(['user_type', 'user_id']);
    }
};
