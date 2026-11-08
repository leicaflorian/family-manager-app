<?php

declare(strict_types=1);

use App\Support\Schema\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The received mail (`received_mails`) and the log of the calls of Mailgun (`mail_inbox_calls`), the links from
 * files and bell notices to a mail, and the settings of the address for the bills.
 */
return new class extends Migration
{
    /**
     * Creates the tables and the columns.
     */
    public function up(): void
    {
        Schema::create('received_mails', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            $table->string('event_id');
            $table->string('sender');
            $table->string('sender_address');
            $table->string('recipient');
            $table->json('recipients');
            $table->text('subject')->nullable();
            $table->dateTime('received_at');
            $table->unsignedInteger('deliveries')->default(1);
            $table->string('outcome');
            $table->json('notes');
            $table->json('skipped');
            $table->json('body')->nullable();
            $table->string('gmail_code')->nullable();
            Columns::optional($table, 'organization_id', 'organizations');
            Columns::system($table);
            $table->unique(['family_id', 'event_id']);
            $table->index(['family_id', 'received_at']);
        });

        Schema::create('mail_inbox_calls', function (Blueprint $table): void {
            $table->id();
            $table->string('outcome');
            $table->unsignedSmallInteger('http_status');
            $table->string('event_id')->nullable();
            $table->unsignedInteger('attachments')->default(0);
            $table->unsignedInteger('saved')->default(0);
            $table->text('error')->nullable();
            $table->json('payload');
            $table->foreignId('family_id')->nullable()->constrained()->nullOnDelete();
            Columns::optional($table, 'received_mail_id', 'received_mails');
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::table('files', function (Blueprint $table): void {
            Columns::optional($table, 'received_mail_id', 'received_mails');
            $table->string('mail_key')->nullable();
            $table->unique(['family_id', 'mail_key']);
        });

        Schema::table('family_settings', function (Blueprint $table): void {
            $table->unique('mail_inbox_recipient');
            $table->boolean('mail_inbox_notify_attachments_only')->default(true);
        });

        Schema::table('reminder_notices', function (Blueprint $table): void {
            $table->foreignId('reminder_id')->nullable()->change();
            $table->date('due_date')->nullable()->change();
            $table->integer('offset')->nullable()->change();
            $table->foreignId('received_mail_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('lines')->nullable();
        });
    }

    /**
     * Drops them. The notices of a mail have no reminder, so they go first.
     */
    public function down(): void
    {
        Schema::table('reminder_notices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('received_mail_id');
            $table->dropColumn('lines');
        });
        DB::table('reminder_notices')->whereNull('reminder_id')->delete();
        Schema::table('reminder_notices', function (Blueprint $table): void {
            $table->foreignId('reminder_id')->nullable(false)->change();
            $table->date('due_date')->nullable(false)->change();
            $table->integer('offset')->nullable(false)->change();
        });

        Schema::table('family_settings', function (Blueprint $table): void {
            $table->dropUnique(['mail_inbox_recipient']);
            $table->dropColumn('mail_inbox_notify_attachments_only');
        });

        Schema::table('files', function (Blueprint $table): void {
            $table->dropUnique(['family_id', 'mail_key']);
            $table->dropConstrainedForeignId('received_mail_id');
            $table->dropColumn('mail_key');
        });

        Schema::dropIfExists('mail_inbox_calls');
        Schema::dropIfExists('received_mails');
    }
};
