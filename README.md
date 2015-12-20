Sellsy Client Library [![Build Status](https://travis-ci.org/ferjul17/Sellsy-api.svg?branch=master)](https://travis-ci.org/ferjul17/Sellsy-api) [![Coverage Status](https://coveralls.io/repos/ferjul17/Sellsy-api/badge.svg?branch=master&service=github)](https://coveralls.io/github/ferjul17/Sellsy-api?branch=master)
=======================

___Work on progress___

PHP library which helps to call Sellsy's API

```php
$client = new Client(['userToken'      => 'xxx', 'userSecret'     => 'xxx',
                      'consumerToken'  => 'xxx', 'consumerSecret' => 'xxx',
                     ]);
var_dump($client->getService('Infos')->call('getInfos', []));
$promise = $client->getService('Infos')->callAsync('getInfos', [])->then(function($res) {
    var_dump($res);
});
$promise->wait();
```
