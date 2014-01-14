Doctrine2 RESTful-driver for OrientDB
========================

Welcome to project.

How use it
----------------------------------

1) Create you entities classes

2) Run console command for create/update you entities:

$ php app/console doctrine:schema:update --force

3) End.

p.s. if you delete entity class, this command not delete class(table) in OrientDB. If you need this action, please
delete class(table) in OrientDB with command:

orientdb {YouDatabase}> drop class YouEntityName

Help
----------------------------------

You Entity Schemas, Doctrine getting and compare in \Doctrine\ORM\Tools\SchemaTool::getUpdateSchemaSql
$fromSchema - schemas from you database
$toSchema - schemas from you classes

If you are in console client and created class(table) in OrientDB with RESTful driver, you may need to see this update
in orientDB console. For this action, please run:

orientdb {YouDatabase}> reload schema

and than:

orientdb {YouDatabase}> info

