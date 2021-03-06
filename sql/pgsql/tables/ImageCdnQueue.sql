create table ImageCdnQueue (
	id serial,

	operation  varchar(255) not null,

	-- for copy operations, and delete operations where we're just deleting from
	-- the cdn, but not deleting from the database.
	image int default null references Image(id) on delete cascade,
	dimension int default null references ImageDimension(id) on delete cascade,
	override_http_headers text,

	-- for delete operations
	file_path varchar(255),

	error_date timestamp,

	primary key(id)
);
