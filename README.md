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
echo "CREATED!\n";

// Getting using filter....
$data = $client->{'Catalog_Номенклатура'}->get(null,"Артикул eq 'CERTANLY_NONEXISTENT'");
echo "GOT!\n";
var_dump($data);

// ... or using Ref_Key
$id = $data['value'][0]['Ref_Key'];
$data = $client->{'Catalog_Номенклатура'}->get($id);
echo "GOT BY ID!\n";
var_dump($data);

// Updating
$data = $client->{'Catalog_Номенклатура'}->update($id,[
    'Description'=>'Test description',
]);
echo "UPDATED!\n";

// deletion
$data = $client->{'Catalog_Номенклатура'}->delete($id);
echo "DELETED!\n";

// out metadata
$data = $client->getMetadata();
var_dump($data);
```

Обработка исключений
---
Компонент в случае ошибочных ответов от сервера вызывает исключения Kily\Tools1C\OData\RequestException.
Это исключение имеет свойство $request в качестве значения которого содержится экземпляр класса 
Kily\Tools1C\OData\Request со всей необходимой информацией для обработки ошибки.
Например, при поиске записи возникла непредвиденная ошибка и нужно залоггировать её:
```php
try {
    $data = $client->{'Catalog_Номенклатура'}->get($id);
    if(!$client->isOk()) {
        var_dump('Something went wrong: ',$client->getErrorCode(),$client->getErrorMessage(),$data);
        die();
    }
} catch (Exception $e) {
    log('Error while requested ' . $e->request->url . ': ' . $e->getMessage());
}
```

Профилирование
-----
Для возможности перехватывать запросы к серверу oData для профилирования или похожих целей,
необходимо реализовать абстрактный класс Profiler:
```php
use Kily\Tools1C\OData\Profiler;
class CustomProfiler extends Profiler {
    public function begin() {
        echo 'Start request ' . $this->url . ' (' . $this->method . ')';
        if (!empty($this->data)) {
            echo ' with data: ' . var_export($this->data, true) . "\n<br>";
        }
    }

    public function end() {
        echo 'Ending request ' . $this->url . ' (' . $this->method . ')' . "\n<br>"; 
    }
}
```
и передать его в конструктор клиента oData или задать через сеттер:
```php
$profiler = new Profiler;
$client = new Client($url, $options, $profiler);
$client->setProfiler($profiler);
```

TODO
-----
- сделать метод getLastId();
- поддержка XML?
