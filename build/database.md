# Database Build Script

## Create Database

The subcommand `create` is used to create or update the database from a set of
files in a directory.

### Script Types

There are 5 different types of scripts: pre-run SQL, post-run SQL, create,
alter and migrate scripts. There are also `.build` files, but you shouldn't
need to create them yourself. Each type (except migrate) has a method name
associated with it.

Pre- and post-run SQL files are run before and after the rest of the scripts,
respectively. The method names are 'PRE' and 'POST'.

Create scripts are for the creation of database objects and are run first, the
method name is 'CREATE'.

Alter and migrate scripts are run at the same time, and have to have
a timestamp in the file name. Alter scripts are SQL files that are executed on
the database, whereas migrate scripts are executable files that are executed
directly. Migrate does not have a method name, alter scripts have the method
name 'ALTER'.

`.build` files are used for controlling the create, alter and migrate scripts.
They indicate that an object has been created and may contain a timestamp with
the most recent alter or migrate script that has been applied.

### File Naming

The files in the source folder must be named a specific way, though directory
structure is ignored. The name of a file must be like this:

    [<priority>.]<name>[.<method>][.<timestamp>][.extension]

Priority is an integer, with lower numbers being executed first. This is
ignored for ALTER and MIGRATE type scripts. This can only change execution
order relative to other scripts of the same method.

Name is the name of script set, required. Each database object should have
a unique name, preferably that matches the name of the object being created
(but not required).

Method is the method for the script, as explained in the previous section.

Timestamp is a unix timestamp (number of seconds since the Unix Epoch,
Midnight, 1st January 1970), used to indicate the time the alter or migrate
script was created. This is required on alter and migrate scripts and ignored
on others.

Extension is the file extension. `.sql` and `.build` files are recognised and
all other files are checked for the executable bit.
