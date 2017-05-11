<?php

namespace Kily\Tools1C\OData;

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Client as Guzzle;

class Client
{

    protected $requested = null;
    protected $client = null;

    protected $error_message;
    protected $error_code;

    protected $_metadata = [];

    public function __construct($url,$options=[]) {
        $this->client = new Guzzle(array_replace_recursive([
            'base_uri'=>$url,
            'timeout'=>'300',
            'headers'=>[
                'Accept'=>'application/json',
            ],
        ],$options));
    }

    public function id($id) {
        $this->requested[] = "(guid'{$id}')";
        return $this;
    }

    public function create(array $data,$options=[]) {
        $this->update(null,$data,$options);
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
        /*
        if(!$objname)
            throw new Exception('Bad request: '.$name);
        if(!in_array($type,$this->objects())) {
            throw new Exception('Object of type '.$type.' not supported');
        }
         */

        if($this->requested && is_array($this->requested) && (count($this->requested) > 0)  ) {
            $tmp = implode('',$this->requested);
            if(strrpos($tmp,'/') !== (count($tmp)-1)) {
                $name = '/'.$name;
            }
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

        try {
            $resp = $this->client->request($method,$request_str,$options);
            $this->request_ok = true;
        } catch(TransferException $e) {
            if($e instanceof TransferException) {
                if($e->hasResponse() && ($resp = $e->getResponse()) ) {
                    $this->error_code = $resp->getStatusCode();
                    $this->error_message = $resp->getReasonPhrase();
                } else {
                    $this->error_code = $e->getCode();
                    $this->error_message = $e->getMessage();
                }
            } else {
                $this->error_code = $e->getCode();
                $this->error_message = $e->getMessage();
                return null;
            }
        }
        $this->parseMetadata($resp);
        return $this->toArray($resp);
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

    public function getLastId() {
        return !empty($this->_metadata['last_id']) ? $this->_metadata['last_id'] : null;
    }

    protected function toArray(ResponseInterface $resp) {
        return json_decode($resp->getBody(),true);
    }

    protected function parseMetadata(ResponseInterface $resp) {
        if($body = $resp->getBody()) {
            $this->_metadata['body'] = $body->__toString();
        }
        if($resp->hasHeader('Location')) {
            preg_match("/guid'(.*?)'/",implode(' ',$resp->getHeader('Location')),$matches);
            if($matches) $this->_metadata['last_id'] = $matches[1];
        }
    }

    public function getMetadata($name) {
        return isset($this->_metadata[$name]) ? $this->_metadata[$name] : null;
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
			'Регистр сведений'=>'InformationRegisters',
			'Регистр накопления'=>'AccumulationRegister',
			'Регистр расчета'=>'CalculationRegister',
			'Регистр бухгалтерии'=>'AccountingRegister',
			'Бизнес-процесс'=>'BusinessProcess',
			'Задача'=>'Task',
			'Перечисления'=>'Enum',
		];
	}

    public function __call($name,$arguments=[]) {
        $this->requested[] = "/";
        $this->requested[] = ucfirst($name);
        return $this->request('POST',[]);
    }
}
