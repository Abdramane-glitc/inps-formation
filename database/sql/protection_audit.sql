-- Protection du journal d'audit contre les modifications et suppressions ordinaires.
-- À exécuter par un administrateur MySQL si la migration n'a pas pu créer les déclencheurs :
--   mysql -u root -p inps_formation < database/sql/protection_audit.sql
DROP TRIGGER IF EXISTS audit_logs_no_update;
DROP TRIGGER IF EXISTS audit_logs_no_delete;
CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Journal d audit non modifiable';
CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Journal d audit non supprimable';
