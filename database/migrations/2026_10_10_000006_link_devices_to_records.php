<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /*
         * Add device_id columns first without chaining constrained().
         *
         * Some earlier partial migration attempts may already have created
         * the columns or a malformed foreign key named "1". We preserve the
         * columns and repair the foreign keys below.
         */
        $tables = [
            'health_checks' => 'fk_health_checks_device',
            'incidents' => 'fk_incidents_device',
            'recovery_actions' => 'fk_recovery_actions_device',
        ];

        foreach ($tables as $tableName => $foreignKeyName) {
            if (! Schema::hasColumn($tableName, 'device_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedBigInteger('device_id')
                        ->nullable()
                        ->after('id');
                });
            }

            $databaseName = DB::getDatabaseName();

            /*
             * Find ALL existing foreign keys on device_id that point to
             * devices.id. This also detects the bad constraint called "1"
             * left behind by a partially completed migration.
             *
             * information_schema exists on MariaDB/MySQL. SQLite tests create
             * the schema from scratch, so they skip this catalog lookup.
             */
            $existingForeignKeys = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)
                ? DB::select(
                    '
                    SELECT CONSTRAINT_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = ?
                      AND TABLE_NAME = ?
                      AND COLUMN_NAME = ?
                      AND REFERENCED_TABLE_NAME = ?
                      AND REFERENCED_COLUMN_NAME = ?
                    ',
                    [
                        $databaseName,
                        $tableName,
                        'device_id',
                        'devices',
                        'id',
                    ]
                )
                : [];

            $correctForeignKeyAlreadyExists = false;

            foreach ($existingForeignKeys as $existingForeignKey) {
                $existingName = $existingForeignKey->CONSTRAINT_NAME;

                if ($existingName === $foreignKeyName) {
                    $correctForeignKeyAlreadyExists = true;
                    continue;
                }

                /*
                 * Remove malformed/old foreign keys such as constraint `1`.
                 */
                $safeTable = str_replace('`', '``', $tableName);
                $safeConstraint = str_replace('`', '``', $existingName);

                DB::statement(
                    "ALTER TABLE `{$safeTable}` DROP FOREIGN KEY `{$safeConstraint}`"
                );
            }

            /*
             * Create a properly named foreign key.
             */
            if (! $correctForeignKeyAlreadyExists) {
                Schema::table(
                    $tableName,
                    function (Blueprint $table) use ($foreignKeyName) {
                        $table->foreign(
                            'device_id',
                            $foreignKeyName
                        )
                            ->references('id')
                            ->on('devices')
                            ->nullOnDelete();
                    }
                );
            }
        }

        /*
         * Preserve health checks, incidents and recoveries that existed
         * before NetRescue Central was introduced.
         *
         * They belong to this machine, so they are attached to the
         * Central Host device instead of being deleted.
         */
        $localUuid = '00000000-0000-0000-0000-000000000001';

        $localId = DB::table('devices')
            ->where('device_uuid', $localUuid)
            ->value('id');

        if (! $localId) {
            $localId = DB::table('devices')->insertGetId([
                'device_uuid' => $localUuid,
                'display_name' => gethostname() ?: 'NetRescue Central Host',
                'hostname' => gethostname() ?: 'NetRescue Central Host',
                'connection_type' => 'local',
                'is_local' => true,
                'operating_system' => PHP_OS_FAMILY,
                'status' => 'unknown',
                'registered_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('health_checks')
            ->whereNull('device_id')
            ->update([
                'device_id' => $localId,
            ]);

        DB::table('incidents')
            ->whereNull('device_id')
            ->update([
                'device_id' => $localId,
            ]);

        DB::table('recovery_actions')
            ->whereNull('device_id')
            ->update([
                'device_id' => $localId,
            ]);
    }

    public function down(): void
    {
        $tables = [
            'health_checks' => 'fk_health_checks_device',
            'incidents' => 'fk_incidents_device',
            'recovery_actions' => 'fk_recovery_actions_device',
        ];

        foreach ($tables as $tableName => $foreignKeyName) {
            if (! Schema::hasColumn($tableName, 'device_id')) {
                continue;
            }

            $databaseName = DB::getDatabaseName();

            $foreignKeys = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)
                ? DB::select(
                    '
                    SELECT CONSTRAINT_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = ?
                      AND TABLE_NAME = ?
                      AND COLUMN_NAME = ?
                      AND REFERENCED_TABLE_NAME = ?
                    ',
                    [
                        $databaseName,
                        $tableName,
                        'device_id',
                        'devices',
                    ]
                )
                : [];

            foreach ($foreignKeys as $foreignKey) {
                $safeTable = str_replace('`', '``', $tableName);
                $safeConstraint = str_replace(
                    '`',
                    '``',
                    $foreignKey->CONSTRAINT_NAME
                );

                DB::statement(
                    "ALTER TABLE `{$safeTable}` DROP FOREIGN KEY `{$safeConstraint}`"
                );
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('device_id');
            });
        }
    }
};
