<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/** Déclencheurs MySQL interdisant UPDATE/DELETE sur audit_logs. */
final class ProtectionAudit
{
    public static function installer(): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }
        try {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete');
            DB::unprepared("CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Journal d audit non modifiable'");
            DB::unprepared("CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Journal d audit non supprimable'");

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public static function estActive(): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }
        $n = DB::selectOne("SELECT COUNT(*) n FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME IN ('audit_logs_no_update','audit_logs_no_delete')");

        return (int) $n->n === 2;
    }
}
