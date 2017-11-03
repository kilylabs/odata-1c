<?php

namespace Kily\Tools1C\OData;

use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Client as Guzzle;
use yii\helpers\Html;

class Client
{
    /**
     * @var Response
     */
    public $response = null;
    protected $requested = null;
    protected $client = null;

    protected $error_message;
    protected $error_code;

    public function __construct($url,$options=[]) {
        $this->client = new Guzzle(array_replace_recursive([
            'base_uri'=>$url,
            'timeout'=>'300',
            'headers'=>[
                'Accept'=>'application/json',
            ],
        ],$options));
    }

    public function create(array $data,$options=[]) {
        return $this->update(null,$data,$options);
    }

    public function get($id=null,$filter=null,$options=[]) {
        if(is_array($filter) && !$options) {
            $options = $filter;
            $filter = null;
        }

        $query = null;
        if($id) {
            $query .= "(guid'{$id}')";
        }
        if($filter) {
            $options['query']['$filter'] = $filter;
        }
        $this->requested[] = $query;

        return $this->request('GET',$options);
    }

    public function update($id,$data=[],$options=[]) {
        $method = 'PATCH';
        if(!$id)
            $method = 'POST';

        $query = null;
        if($id) {
            $query .= "(guid'{$id}')";
        }
        $this->requested[] = $query;

        if($data) $options['json'] = $data;

        return $this->request($method,$options);
    }

    public function getMetadata() {
        $resp = $this->client->request('get','$metadata',[]);
        $xml = simplexml_load_string($resp->getBody(), 'SimpleXMLElement', 0, 'edmx', true);
        $children = $xml->children('edmx', true)->children()->children();
        $json = json_encode($children);
        $array = json_decode($json,TRUE);

        return array_merge($array['EntityType'], $array['ComplexType']);
    }

    public function delete($id,$options=[]) {
        $query = null;
        if($id) {
            $query .= "(guid'{$id}')";
        }
        $this->requested[] = $query;
        return $this->request('DELETE',$options);
    }

    public function __get($name) {
        @list($type,$objname) = explode('_',$name,2);
        if(!$objname)
            throw new Exception('Bad request: '.$name);
        if(!in_array($type,$this->objects())) {
            throw new Exception('Object of type '.$type.' not supported');
        }

        $this->requested[] = $name;

        return $this;
    }

    public function request($method,$options=[]) {
        $resp = null;
        $this->error_code = null;
        $this->error_message = null;
        $this->request_ok = false;
        $request_str = implode('',$this->requested);
        $this->requested = [];
        if (!empty($options['query'])) {
            $filter = '?' . urldecode(http_build_query($options['query']));
            if (!empty($options['query']['$filter']) && ($options['query']['$filter'] === 'true eq false' || $options['query']['$filter'] === 'false eq true')) {
                $this->request_ok = true;
                return;
            }
        } else {
            $filter = '';
        }

        $url = $this->client->getConfig('base_uri') . $request_str . $filter;
        $message = 'oData request ' . Html::a($url, $url, [
                'target' => '_blank'
            ]) . ' (' . $method . ')';
        \yii::beginProfile($message, 'oData');
        \yii::trace('Start ' . $message, 'oData');

        try {
            $resp = $this->client->request($method,$request_str,$options);
            $this->response = $resp;
            $this->request_ok = true;
        } catch(TransferException $e) {
            if($e instanceof ClientException || $e instanceof ServerException) {
//                if($resp = $e->getResponse()) {
//                    $this->error_code = $resp->getStatusCode();
//                    $this->error_message = $resp->getReasonPhrase();
//                } else {
                $this->error_code = $e->getCode();
                $this->error_message = $e->getResponse()->getBody();
//                }
            } else {
                $this->error_code = $e->getCode();
                $this->error_message = $e->getTraceAsString();
            }

            if (isset($options['json'])) {
                $data = var_export($options['json'], true);
            } else {
                $data = '';
            }

            throw new \yii\base\Exception('Error while requested ' . $url . '(' . $method . ')' . ' ' . $this->error_message . '(' . $this->error_code . ')' . $data);
        }

        \yii::endProfile($message, 'oData');
        \yii::trace('End ' . $message, 'oData');

        if ($this->request_ok) {
            return $this->toArray($resp);
        }
    }

    public function getErrorMessage() {
        return $this->error_message;
    }

    public function getErrorCode() {
        return $this->error_code;
    }

    public function isOk() {
        return $this->request_ok;
    }

    protected function toArray(ResponseInterface $resp) {
        return json_decode($resp->getBody(),true);
    }

    protected function objects() {
        return [
            'Справочник'=>'Catalog',
            'Документ'=>'Document',
            'Журнал документов'=>'DocumentJournal',
            'Константа'=>'Constant',
            'План обмена'=>'ExchangePlan',
            'План счетов'=>'ChartOfAccounts',
            'План видов расчета'=>'ChartOfCalculationTypes',
            'План видов характеристик'=>'ChartOfCharacteristicTypes',
            'Регистр сведений'=>'InformationRegister',
            'Регистр накопления'=>'AccumulationRegister',
            'Регистр расчета'=>'CalculationRegister',
            'Регистр бухгалтерии'=>'AccountingRegister',
            'Бизнес-процесс'=>'BusinessProcess',
            'Задача'=>'Task',
        ];
    }
}
