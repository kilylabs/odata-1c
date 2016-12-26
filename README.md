# odata-1c
OData клиент для 1C 

Установка
------------

Рекомендуемый способ установки через
[Composer](http://getcomposer.org):

```
$ composer require kilylabs/odata-1c
```

Использование
-----

Пример кода

```php
<?php

use Kily\Tools1C\OData\Client;

require __DIR__.'/vendor/autoload.php';

$client = new Client('http://HOSTNAME/BASE/odata/standard.odata/',[
    'auth' => [
        'YOUR LOGIN', 
        'YOUR PASSWORD'
    ],
	'timeout' => 300,
]);

$product_data = [
    'Артикул'=>'CERTANLY_NONEXISTENT',
    'Description'=>'test test test nonexistent',
];

// Creation
$data = $client->{'Catalog_Номенклатура'}->create($product_data);
if(!$client->isOk()) {
    var_dump('Something went wrong: ',$client->getErrorCode(),$client->getErrorMessage(),$data);
    die();
}
echo "CREATED!\n";

// Getting using filter....
$data = $client->{'Catalog_Номенклатура'}->get(null,"Артикул eq 'CERTANLY_NONEXISTENT'");
if(!$client->isOk()) {
    var_dump('Something went wrong: ',$client->getErrorCode(),$client->getErrorMessage(),$data);
    die();
}
echo "GOT!\n";
var_dump($data);

// ... or using Ref_Key
$id = $data['value'][0]['Ref_Key'];
$data = $client->{'Catalog_Номенклатура'}->get($id);
if(!$client->isOk()) {
    var_dump('Something went wrong: ',$client->getErrorCode(),$client->getErrorMessage(),$data);
    die();
}
echo "GOT BY ID!\n";
var_dump($data);

// Updating
$data = $client->{'Catalog_Номенклатура'}->update($id,[
    'Description'=>'Test description',
]);
if(!$client->isOk()) {
    var_dump('Something went wrong: ',$client->getErrorCode(),$client->getErrorMessage(),$data);
    die();
}
echo "UPDATED!\n";

// deletion
$data = $client->{'Catalog_Номенклатура'}->delete($id);
if(!$client->isOk()) {
    var_dump('Something went wrong: ',$client->getErrorCode(),$client->getErrorMessage(),$data);
    die();
}
echo "DELETED!\n";
```

TODO
-----
- сделать метод getLastId();
- поддержка XML?
