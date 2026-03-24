<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_inbox_messages', function (Blueprint $table) {
            $table->id();
            $table->string('graph_message_id')->unique();
            $table->string('internet_message_id')->nullable()->index();
            $table->string('mailbox_upn');
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->string('subject')->nullable();
            $table->dateTime('received_at')->nullable()->index();
            $table->boolean('has_attachments')->default(false);
            $table->string('status', 32)->default('new');
            $table->string('body_preview', 512)->default('');
            $table->longText('body_plain')->nullable();
            $table->longText('body_html')->nullable();
            $table->string('body_content_type', 16)->nullable();
            $table->timestamps();
        });

        Schema::create('email_inbox_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_inbox_message_id')->constrained('email_inbox_messages')->cascadeOnDelete();
            $table->string('graph_attachment_id')->nullable();
            $table->string('filename');
            $table->string('content_type')->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->string('storage_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_inbox_attachments');
        Schema::dropIfExists('email_inbox_messages');
    }
};
