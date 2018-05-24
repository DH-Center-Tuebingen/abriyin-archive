alter type edit_history_status add value 'imported';

alter table documents add column import_notes text;
alter table documents_history add column import_notes text;

UPDATE pg_attribute SET atttypmod = 20+4
WHERE attrelid = 'documents'::regclass or attrelid = 'documents_history'::regclass
AND attname = 'signature';
