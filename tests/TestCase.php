<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function createAuditTables(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->id('role_id');
                $table->string('role_name');
                $table->timestamps();
            });
            Schema::create('user_roles', function (Blueprint $table): void {
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('role_id');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->id('audit_log_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_role')->nullable();
                $table->string('action');
                $table->string('module');
                $table->text('description');
                $table->nullableMorphs('auditable');
                $table->string('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at');
            });
        }
        if (! Schema::hasColumn('audit_logs', 'metadata')) {
            Schema::table('audit_logs', fn (Blueprint $table) => $table->json('metadata')->nullable());
        }
    }
}
