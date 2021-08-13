### Laravel CRUD & View Generator

This package generates model, controllers, request, resource and views for CRUD operations.

This package is meant primary for personal use.

#### Installation

Install the package in the development mode only. Since the package generates files, it isn't needed at all during
production.

```shell
composer require --dev adwiv/laravel-crud-generator
```

#### Usage

To use this generator, you must have an existing database table for the model and CRUD you want to generate. First,
create a migration, and migrate it to create the tables.

Then, to generate all files use one of the following commands

```shell
php artisan crud:all ModelClass
php artisan crud:all ModelClass [--prefix admin]
php artisan crud:all ModelClass [--route-prefix admin] [--view-prefix user]
php artisan crud:all ModelClass [--table table_name]
```


You can also generate individual files:
```shell
php artisan crud:model ModelClass [--table table_name]
php artisan crud:request ModelRequest [--model ModelClass]
php artisan crud:resource ModelResource [--model ModelClass] [-c | --collection]
php artisan crud:controller ModelController [--model ModelClass]
php artisan crud:controller ModelController [--parent ParentModelClass]
```
