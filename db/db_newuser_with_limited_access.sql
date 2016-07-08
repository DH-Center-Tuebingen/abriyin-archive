--- replace XXX 
create user XXX with password '***';
revoke connect on database alhamra from public; -- if not already done
revoke all on all tables in schema public from public; -- if not already done
grant connect on database alhamra to XXX;
grant usage on schema public to XXX;
grant all on table a, b to XXX;
grant select on table c, d to XXX;
grant usage on all sequences in schema public to XXX;