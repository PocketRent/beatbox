-- Asset table, stores information about assets
CREATE TABLE IF NOT EXISTS "Asset" (
	"ID" SERIAL PRIMARY KEY,
	"Name" varchar(250) NOT NULL,
	"Type" varchar(100),
	"SourcePath" varchar(250) NOT NULL
)
