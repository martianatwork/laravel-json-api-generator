# MartianAtWork Laravel

[![Latest Stable Version](https://poser.pugx.org/martianatowrk/laravel-json-api-generator/v/stable)](https://packagist.org/packages/martianatowrk/laravel-json-api-generator)
[![Total Downloads](https://poser.pugx.org/martianatowrk/laravel-json-api-generator/downloads)](https://packagist.org/packages/martianatowrk/laravel-json-api-generator)
[![Latest Unstable Version](https://poser.pugx.org/martianatowrk/laravel-json-api-generator/v/unstable)](https://packagist.org/packages/martianatowrk/laravel-json-api-generator)
[![License](https://poser.pugx.org/martianatowrk/laravel-json-api-generator/license)](https://packagist.org/packages/martianatowrk/laravel-json-api-generator)

MartianAtWork Laravel is a collection of Laravel Components which aim is 
to help the development process of Laravel applications by 
providing some convenient code-generation capabilities.

## How does it work?

This package expects that you are using Laravel 5.1 or above.
You will need to import the `martianatowrk/laravel-json-api-generator` package via composer:

```shell
composer require martianatowrk/laravel-json-api-generator
```

### Configuration

Add the service provider to your `config/app.php` file within the `providers` key:

```php
// ...
'providers' => [
    /*
     * Package Service Providers...
     */

    MartianAtWork\Coders\CodersServiceProvider::class,
],
// ...
```
### Configuration for local environment only

If you wish to enable generators only for your local environment, you should install it via composer using the --dev option like this:

```shell
composer require MartianAtWork/laravel --dev
```

Then you'll need to register the provider in `app/Providers/AppServiceProvider.php` file.

```php
public function register()
{
    if ($this->app->environment() == 'local') {
        $this->app->register(\MartianAtWork\Coders\CodersServiceProvider::class);
    }
}
```

## Models

![Generating models with artisan](https://cdn-images-1.medium.com/max/800/1*hOa2QxORE2zyO_-ZqJ40sA.png "Making artisan code my Eloquent models")

Add the `models.php` configuration file to your `config` directory and clear the config cache:

```shell
php artisan vendor:publish --tag=MartianAtWork-models
php artisan config:clear
```

### Usage

Assuming you have already configured your database, you are now all set to go.

- Let's scaffold some of your models from your default connection.

```shell
php artisan code:models
```

- You can scaffold a specific table like this:

```shell
php artisan code:models --table=users
```

- You can also specify the connection:

```shell
php artisan code:models --connection=mysql
```

- If you are using a MySQL database, you can specify which schema you want to scaffold:

```shell
php artisan code:models --schema=shop
```

### Customizing Model Scaffolding

To change the scaffolding behaviour you can make `config/models.php` configuration file
fit your database needs. [Check it out](https://github.com/MartianAtWork/laravel/blob/master/config/models.php) ;-)

### Tips

#### 1. Keeping model changes

You may want to generate your models as often as you change your database. In order
not to lose you own model changes, you should set `base_files` to `true` in your `config/models.php`.

When you enable this feature your models will inherit their base configurations from
base models. You should avoid adding code to your base models, since you
will lose all changes when they are generated again.

> Note: You will end up with two models for the same table and you may think it is a horrible idea 
to have two classes for the same thing. However, it is up to you
to decide whether this approach gives value to your project :-)

#### Support

For the time being, this package only supports MySQL databases. Support for other databases will be added soon.
