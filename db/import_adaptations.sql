alter type edit_history_status add value 'imported';

UPDATE pg_attribute SET atttypmod = 20+4
WHERE (attrelid = 'documents'::regclass or attrelid = 'documents_history'::regclass)
AND attname = 'signature';

drop table if exists neu cascade; 
CREATE TABLE neu (
    zeile int,
    nr text,
    dif text,
    jahr text,
    datum text,
    adressat text,
    absender text,
    weitere text,
    inhalt text
);
