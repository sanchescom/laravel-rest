# Laravel Rest

This library provides tools and interfaces for working with REST API and using Laravel Models and Collections.

## Installing

Require this package, with [Composer](https://getcomposer.org/), in the root directory of your project.

``` bash
$ composer require sanchescom/laravel-rest
```

### Laravel:

After updating composer, add the ServiceProvider to the providers array in `config/app.php`

 ```php
'providers' => [
    ...
    Sanchescom\Rest\RestServiceProvider::class,
],
```

### Lumen:

After updating composer add the following lines to register provider in `bootstrap/app.php`

```php
$app->register(Sanchescom\Rest\RestServiceProvider::class);
```

## Configuration

Change your default rest api name in `config/rest.php`:

```php
'default' => env('REST_CLIENT', 'localhost'),
```

And add a new api configuration:

```php
<?php

return [
    'clients' => [
        'localhost' => [
            'provider' => 'guzzle',
            'base_uri' => 'https://localhost/',
            'options' => [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ],
        ],
    ],
];
```

### Model

This package includes a Rest enabled Model class that you can use to define models for corresponding collections.

```php
<?php

use Sanchescom\Rest\Model;

class User extends Model {
    /** {@internal} */
    protected $dataKey = 'data';

    /** {@internal} */
    protected $fillable = [
        "id",
        "first_name",
        "last_name",
        "email",
    ];
}
```

### Examples

**URL** : `/api/users`

**Content examples**

For Users.

```json
{
    "data": [
        {
            "id": 1,
            "first_name": "Joe",
            "last_name": "Bloggs",
            "email": "joe25@example.com"
        },
        {
            "id": 2,
            "first_name": "Bob",
            "last_name": "Jonson",
            "email": "bob25@example.com"
        }
    ]
}
```

### Basic Usage

**Retrieving All Models**

```php
$users = User::get();
```

**Retrieving A Record By Id**

```php
$user = User::get('1');
```

**Retrieving Records By Ida**

```php
$user = User::getMany(['1', '2']);
```

**Wheres**

```php
$users = User::get()->('first_name', 'Bob');
```

For more information check https://laravel.com/docs/collections

### Inserts, updates and deletes

**Saving a new model**

```php
User::post(['first_name' => 'Tim']);
```

**Updating a model**

To update a model, you may retrieve it, change an attribute, and use the put method.

```php
$user = User::get('2');
$user->email = 'john@foo.com';
$user->put();
```

Or updating a model by its key

```php
User::put('2', ['email' => 'john@foo.com']);
```

**Deleting a model**

To delete a model, simply call the delete method on the instance:

```php
$user = User::get('1');
$user->delete();
```

Or deleting a model by its key:

```php
User::delete('1');
```

## Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of conduct, and the process for submitting pull requests to us.

## Versioning

We use [SemVer](http://semver.org/) for versioning. For the versions available, see the [tags on this repository](https://github.com/sanchescom/php-wifi/tags). 

## Authors

* **Efimov Aleksandr** - *Initial work* - [Sanchescom](https://github.com/sanchescom)

See also the list of [contributors](https://github.com/sanchescom/php-wifi/contributors) who participated in this project.

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details