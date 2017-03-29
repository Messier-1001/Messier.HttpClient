# Messier.HttpClient

A small HTTP client (wrapper around curl) with a small code footprint and fluent access.

## Installation

```
composer require messier1001/messier.httpclient
```

or inside the `composer.json`:

```json
{
   "require": {
      "php": ">=7.1",
      "messier1001/messier.httpclient": "^0.1.0"
   }
}
```

## Usage

# Sending a GET request

```php
try
{
   echo \Messier\HttpClient\Client::Create()
      ->setGetParameters( [ 'q' => 'Wetter Dresden' ] )
      ->sendGet( 'https://www.google.de/#' );
}
catch ( \Throwable $ex )
{
   echo $ex;
}
```

If some error is triggered a \Messier\HttpClient\ClientException is thrown expecting a own error handling function
is assigned.

## Sending a POST request

```php
echo \Messier\HttpClient\Client::Create()
   ->onError( function( $errorMessage ) { echo 'There was an error :-('; exit; } )
   ->sendPost( 'http://fooooo.baaaar.baaaaaaaazz', [ 'action' => 'foo' ] );
```

and much more :-)
