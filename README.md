# Google Trends Unofficial PHP API

This unofficial API provides an easy way to query Google Trends for certain data. The data can be returned as decoded JSON, GoogleTrendsTerm objects or as a formatted array (only suited for making your own graph). CSV exports or iFrame results are not supported.

Keep in mind that this is an unofficial API. Whether or not it continues to work depends on Google. Use at your own risk.

## Installation

To get the latest version of this package require it in your `composer.json` file.

~~~
"jonasva/google-trends": "dev-master"
~~~

Run `composer update jonasva/google-trends` to install it.

## Usage

The API works with a session object, which requires you to authenticate with a valid Google account. This is necessary as unauthenticated users will hit the trends quota limit after just a couple of requests, resulting in a 1-day ban.

First init a session object with your google account credentials
```php
    $config = [
        'email'     =>  'my.google.account@gmail.com',
        'password'  =>  'mygooglepassword',
    ];

    $session = (new GoogleSession($config))->authenticate();
```

Then create a request and add some parameters. The example below returns an array of a trend line chart's labels (date) and data points for terms 'cycling' and 'golf'.
```php
    $response = (new GoogleTrendsRequest($session)) // create request
                    ->addTerm('cycling') // add a term to compare
                    ->addTerm('golf') // add a term to compare
                    ->setDateRange(new \DateTime('2014-02-01'), new \DateTime()) // date range
                    ->getGraph() // cid (linechart)
                    ->send(); //execute the request

    $data = $response->getFormattedData(); // return formatted data suitable for creating a line chart
```

## Examples

Fetch the top google search queries in Belgium between February 2014 and now. It's equivalent to this data on Google trends: https://www.google.com/trends/explore#geo=BE&date=2%2F2014%2015m&cmpt=q&tz=
```php
    $response = (new GoogleTrendsRequest($session))
                    ->setDateRange(new \DateTime('2014-02-01'), new \DateTime()) // date range, if not passed, the past year will be used by default
                    ->setLocation('BE') // For location Belgium
                    ->getTopQueries() // cid (top queries)
                    ->send(); //execute the request

    $data = $response->getTermsObjects(); // return an array of GoogleTrendsTerm objects
```

Fetch the rising queries related to 'cycling' and in category 'Arts & Entertainment' (category id 0-3) for the past year.
```php
    $response = (new GoogleTrendsRequest($session))
                    ->addTerm('cycling') // term cycling
                    ->setCategory('0-3') // category id for arts & entertainment
                    ->getRisingQueries() // cid (rising queries)
                    ->send(); //execute the request

    $data = $response->getTermsObjects(); // return an array of GoogleTrendsTerm objects
```

## Remarks

The `getTermsObjects()` method cannot be used for a response obtained with the `getGraph()` method.
```php
    $response->getTermsObjects();
```

To get a response's raw contents, the following method can be used:
```php
    $response->getResponseContent();
```

To just json decode response content, you can use this method:
```php
    $response->jsonDecode();
```

Each request has a random delay between 0.1 and 2 seconds. This setting can changed in the `GoogleSession` object.
```php
    $session->setMaxSleepInterval(0); // disable the delay
    $session->setMaxSleepInterval(300); // set the max delay to 3 seconds
```

Some other options are available as well, check the code for more information. 