# Using Symfony

We are using Symfony 7.4, as the README says. If you want the REAL Symfony docs that tell you how it works, I recommend [Reading the official docs](https://symfony.com/doc/7.4) OR [watching this tutorial](https://youtu.be/i_jgWZItCGI?si=OpJsttT3o6r6UFsH&t=207). Note that due to the nature of our project, we have some deviations from that original tutorial

- [Using Symfony](#using-symfony)
  - [Prerequisites](#prerequisites)
  - [Deviations from default Symfony](#deviations-from-default-symfony)
    - [Webserver](#webserver)
    - [Legacy Router](#legacy-router)
    - [Composer Scripts](#composer-scripts)
    - [PHP Namespaces](#php-namespaces)
    - [Doctrine Migrations](#doctrine-migrations)
    - [Files to not touch](#files-to-not-touch)
  - [Changes to our pre-symfony workflow](#changes-to-our-pre-symfony-workflow)
    - [Making pages](#making-pages)
    - [Frontend Development](#frontend-development)

## Prerequisites

I'm assuming you read the README at least to the point where you install `composer` and the correct `php-8.3` extensions. 
Please run 

```bash 
cd src/abet_private
composer install
``` 



## Deviations from default Symfony

<!-- Make sure you also implement the autolaod files, if not automaticaly generated.


if you have not already. This will install the vendor packages for you.  -->

### Webserver

First and foremost, I don't use the Symfony CLI at all to test the application. I welcome you to, but using `docker compse` is highly recommended, as it is a really close setup to what we have on the server. 

### Legacy Router

Most symfony docs assume you are using Routes with Controllers + Twig templates.
Our application is a bit different. We have legacy PHP code that we want to handle, so we made a mechanism to handle these legacy files: `LegacyBridge.php`. AKA the Legacy Router (whatever you want to call it).
For every URI (the path after the site name), it checks if there exists a Symfony route for it.
If it doesn't exist, it falls back to looking for existing PHP files in the `public/` directory with the same name.

<img alt="flowchart showing the flow of HTTP requests to the server" src="../readme/Symfony Flowchart.png">

### Composer Scripts

Composer allows you to to make shortcut scripts for repeated actions.
These scripts are defined in composer.json

**composer.json**
```json
{
    "scripts": {
        "doctrine": "./vendor/bin/doctrine-migrations",
        "pest": "./vendor/bin/pest",
        "test": "./vendor/bin/phpunit"
    },

}
```

So, you can "shortcut" some commands if you want to

- php bin/console ... -> composer console ...
- php vendor/bin/phpunit -> composer test
- php vendor/bin/doctrine-migrations -> composer doctrine

Note that you can add more entries here, and this even transfers command line arguments to the target!

### PHP Namespaces

PHP Namespaces can be defined in the `composer.json` file. 
Note that when you update this, make sure to run `composer update` to update the filepaths. 
Namespaces let you map these PHP interfaces to real filepaths where these folders are.
Of course, its all relative to `src/abet_private`.

```json
{
    "autoload": {
        "psr-4": {
            "App\\": "src",
            "Entity\\": "database/Entity"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    }
}
```

You can then use these namespaces as such in your files:

```php
use Doctrine\ORM\EntityManager; # uses pre installed managers with Vendor

# Use a entity at src/Entity/User, since App maps to src. 
use App\Entity\User;
```

### Doctrine Migrations

You don't need to run `composer console ...` to run doctrine migrations, 
you can just run `composer doctrine migrations` to access them easier 

### Files to not touch

- phpunit.xml : phpunit config for tests
- bootstrap.php : database config for doctrine
- cli-config.php : cli config for doctrine
- composer.json : list of dependencies for composer, like `requirements.txt` or `package.json` if you have experience 

## Changes to our pre-symfony workflow

### Making pages

Instead of making a php page in `public`, Symfony allows you to separate concerns. 
Pages are separated into **Controllers** and **Templates**,
which helps you separate logic and display, respectively. 

Symfony has some [AMAZING documentation for this.](https://symfony.com/doc/7.4/page_creation.html)

Please read those docs if you want to make a page. 
They are really in depth and teach you how to generate them super easily with tools. (**MakerBundle**)

### Frontend Development

CSS files are cached. To reload CSS files on update, please follow [these instructions for firefox.](https://support.mozilla.org/en-US/kb/clear-cookies-and-site-data-firefox)