alter type edit_history_status add value 'imported';

UPDATE pg_attribute SET atttypmod = 20+4
WHERE (attrelid = 'documents'::regclass or attrelid = 'documents_history'::regclass)
AND attname = 'signature';

UPDATE pg_attribute SET atttypmod = 500+4
WHERE (attrelid = 'persons'::regclass or attrelid = 'persons_history'::regclass)
AND attname in ('forename_translit', 'lastname_translit', 'byname_translit', 'forename_arabic', 'lastname_arabic', 'byname_arabic');

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
