# odata-1c
OData клиент для 1C 

Документация 1С для интерфейса OData: https://its.1c.ru/db/v838doc#bookmark:dev:TI000001358

Установка
------------

Рекомендуемый способ установки через
[Composer](http://getcomposer.org):

```
$ composer require kilylabs/odata-1c
```

Использование
-----
#### Инициализация
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
```

#### Получение объектов из 1С
```php
<?php

// Получение всех объектов из справочника "Номенклатура" 1С
$data = $client->{'Catalog_Номенклатура'}->get()->values();
var_dump($data);
/*
array(1) {
  [0]=>
  array(105) {
    ["Ref_Key"]=>
    string(36) "3ca886b6-aabd-11e7-1a8d-021c5dd9fc20"
    ["Description"]=>
    string(51) "ПАЛЬТО ПУХ ЖЕН HATANGA V2 БОРДО 46"
,,,
*/

// Получение всех объектов с проверкой ошибок
$data = $client->{'Catalog_Номенклатура'}->get();
if(!$client->isOk()) {
    var_dump('Something went wrong: ',$client->getHttpErrorCode(),$client->getHttpErrorMessage(),$client->getErrorCode(),$client->getErrorMessage(),$data->toArray());
    die();
}
var_dump($data->values());

// Получение по UUID (ID или Ref_Key)
$data = $client->{'Catalog_Номенклатура'}->get("40366f94-cded-11e6-e880-00155dd9fc47")->first();
$data = $client->{'Catalog_Номенклатура'}->id("40366f94-cded-11e6-e880-00155dd9fc47")->get()->first();

// Получение по фильтру
$data = $client->{'Catalog_Номенклатура'}->get("Артикул eq 'АРТ-1'")->values();
$data = $client->{'Catalog_Номенклатура'}->filter("Артикул eq 'АРТ-1'")->get()->values();

// Получение вместе с дополнительной информацией
$data = $client->{'Catalog_Номенклатура'}->expand('Производитель,Марка')->get()->values();
$data = $client->{'Catalog_Номенклатура'}->expand('ВидНоменклатуры')->get()->values();

// Ограничение по количеству в запросе
$data = $client->{'Catalog_Номенклатура'}->top(10)->get()->values();
```
#### Создание объектов в 1С
```php
<?php

// Создание 
$data = $client->{'Catalog_Номенклатура'}->create([
    'Артикул'=>'CERTANLY_NONEXISTENT',
    'Description'=>'test test test nonexistent',
]);

// Получение ID созданного объекта
echo $data->getLastId()
```

#### Обновление объектов в 1С
```php
<?php

// Обновление
$data = $client->{'Catalog_Номенклатура'}->update('40366f94-cded-11e6-e880-00155dd9fc47',[
    'Description'=>'Test description',
]);
```
#### Удаление объектов из 1С
```php
<?php
// Пометка на удаление
$data = $client->{'Catalog_Номенклатура'}->update('40366f94-cded-11e6-e880-00155dd9fc47',{
    'DeletionMark'=>true,
});

// Полное удаление объека из 1С (я бы не стал использовать...)
$data = $client->{'Catalog_Номенклатура'}->delete('40366f94-cded-11e6-e880-00155dd9fc47');
```

#### Проведение и отмена проведения документов
```php
<?php
// Проведение
$data = $client->{'Document_АктВыполненныхРабот'}->id("40366f94-cded-11e6-e880-00155dd9fc47")->post();

// Отмена проведения документа
$data = $client->{'Document_АктВыполненныхРабот'}->id("40366f94-cded-11e6-e880-00155dd9fc47")->unpost();
```
TODO
-----
- ~~сделать метод getLastId();~~
- ~~fluent интерфейс~~
- поддержка XML?
