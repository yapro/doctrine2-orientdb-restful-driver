Doctrine2 RESTful-driver for OrientDB
========================

To work with database, you must perform actions described below.

Need a 2nd version OrientDB, it can be compiled, details - https://github.com/orientechnologies/orientdb/wiki/Tutorial:-Installation#compile-your-own-the-community-edition

Start the database (complete path):

    $ /var/www/orientDB/bin/server.sh

if you receive an error:

    java: command not found

install java, example java 1.7.0_45. You can confirm the version command:

    $ java -version

Setup
----------------------------------

1) In file /app/config/config.yml replace string:

    driver:   %database_driver%

on this string:

    driver_class:   OrientDB\Driver

2) Change you settings for connect to database in file:

    /app/config/parameters.yml

for example default settings:
```
parameters:
    database_driver: pdo_mysql
    database_host: localhost
    database_port: 2480
    database_name: GratefulDeadConcerts
    database_user: admin
    database_password: admin
    mailer_transport: smtp
    mailer_host: 127.0.0.1
    mailer_user: null
    mailer_password: null
    locale: en
    secret: ThisTokenIsNotSoSecretChangeIt
```
3) Add following code into your composer.json
```
      "require": {
          ...
          "yapro/doctrin2-orientdb-restful-driver": "dev-master"
      },
```
and run command:

    $ composer update yapro/doctrin2-orientdb-restful-driver --prefer-source

All ready.

How use it
----------------------------------

1) Create you entities classes (php files)

2) Run console command for create/update you entities:

    $ php app/console doctrine:schema:update --force

If you delete entity class, this command not delete class(table) in OrientDB. If you need this action, please
delete class(table) in OrientDB with command:

    orientdb {YouDatabase}> drop class YouEntityName

UnitTest
----------------------------------

You can check you fix with next command:

    $ bin/phpunit -c app vendor/yapro/doctrin2-orientdb-restful-driver/Tests/Crud.php

Help
----------------------------------

You Entity Schemas, Doctrine getting and compare in \Doctrine\ORM\Tools\SchemaTool::getUpdateSchemaSql

    $fromSchema - schemas from you database

    $toSchema - schemas from you classes

If there is a desire to practice with queries, connect the console client to the database:

    $ /var/www/orientDB/bin/console.sh

    orientdb> connect remote:localhost root You_password ( from file config/orientdb-server-config.xml )

If you are in console client and created class(table) in OrientDB with RESTful driver, you may need to see this update
in orientDB console. For this action, please run:

    orientdb {YouDatabase}> reload schema

and than:

    orientdb {YouDatabase}> info

