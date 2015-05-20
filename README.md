# IpGeoBaseBundle

Work with the IpGeoBase's database using Propel

## Installation:

**1.** Add `it-blaster/ip-geo-base-bundle` to your `composer.json`:

```json
...
"require": {
    "it-blaster/ip-geo-base-bundle": "1.0.*"
}
...
```

**2.** Register the bundle in `AppKernel.php`:

```php
...
new Fenrizbes\IpGeoBaseBundle\FenrizbesIpGeoBaseBundle(),
...
```

**3.** Build models, generate and apply a migration.

**4.** Run a command that imports all the IpGeoBase data:

```bash
php app/console ipgeobase:update
```

## Usage:

The bundle's service `ip_geo_base` contains two methods:
1. `getIpInfo` returns information about IP (a range and a country code) or `null`.
2. `getIpCity` returns an instance of a GeoCity model or `null`. You can configure the default city which is returned
if there is no any right city in the database (see the `Configuration` section).

By default the IP-address is taken from Symfony Request but you can pass it manually if you want:

```php
$this->get('ip_geo_base')->getIpInfo('92.242.13.250');
```

## Configuration

There are two optional parameters that you can set:
1. `default_city` - the default city ID. You can look it out in the `geo_city` table.
2. `enabled` - the state of IP detection service (default `true`). You can set `false` if you need to disable
this service for a while. In this case the `getIpCity` method will always return `null` or the default city
(if it configured).

An example:

```yml
fenrizbes_ip_geo_base:
    default_city: 2732
    enabled: false
```

## Import and update data

The bundle contains a command which import data (if you run it first time) or update it:

```bash
php app/console ipgeobase:update
```

By default the data file is taken from `http://ipgeobase.ru/files/db/Main/geo_files.zip` URL. If you want to change
the data source you can pass your URL to the `source` option:

```bash
php app/console ipgeobase:update --source="http://my-syte.com/geo_files.zip"
```

or download the archive by yourself and pass a local path:

```bash
php app/console ipgeobase:update --source="/path/to/geo_files.zip"
```

Also you have an ability to update the data from text files (but remember that they must be named the same way as ones
in the IpGeoBase's archive):

```bash
php app/console ipgeobase:update --source="/path/to/cities.txt"
php app/console ipgeobase:update --source="/path/to/cidr_optim.txt"
```