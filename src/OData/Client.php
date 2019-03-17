<?php

namespace Kily\Tools1C\OData;

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Client as Guzzle;

class Client implements \ArrayAccess
{

    protected $id = null;

    protected $requested = null;
    protected $response = null;
    protected $client = null;
    protected $request_options = [];

    protected $http_message;
    protected $http_code;
    protected $odata_message;
    protected $odata_code;

    protected $_metadata = [];

    protected $is_called = false;

    public function __construct($url,$options=[]) {
        $this->client = new Guzzle(array_replace_recursive([
            'base_uri'=>$url,
            'timeout'=>'300',
            'headers'=>[
                'Accept'=>'application/json',
            ],
        ],$options));
    }

    public function id($id=null) {
        if($id === null) return $this->getLastId();
        $this->id = $id;
        return $this;
    }

    public function create(array $data,$options=[]) {
        return $this->update(false,$data,$options);
    }

    public function expand($name) {
        $this->request_options['query']['$expand'] = $name;
        return $this;
    }

    public function top($cnt) {
        $this->request_options['query']['$top'] = $cnt;
        return $this;
    }

    public function filter($name) {
        $this->request_options['query']['$filter'] = $name;
        return $this;
    }

    public function get($id=null,$filter=null,$options=[]) {
        $query = null;
        if($id === null) {
            $id = $this->id;
        } elseif(is_array($id)) {
            $query = [];
            foreach($id as $k=>$v) {
                if(preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',$v)) {
                    $v = "guid'{$v}'";
                }
                $query[] = $k.'='.$v;
            }
            $query = '('.implode(',',$query).')';
        } elseif(!preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',$id)) {
            $options = $filter;
            $filter = $id;
            $id = null;
        }

        if($id && !is_array($id)) {
            $query .= "(guid'{$id}')";
        }
        if($filter) {
            $this->filter($filter);
        }
        if($query)
            $this->requested[] = $query;

        return $this->request('GET',$options);
    }

    public function update($id=null,$data=[],$options=[]) {
        if($id === null) $id = $this->id;
        elseif(is_array($id)) {
            $options = $data;
            $data = $id;
            $id = $this->id;
            if(!$this->id) $id = $this->getLastId();
        }

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

    public function delete($id=null,$options=[]) {
        $query = null;

        if($id === null) {
            $id = $this->id;
        } elseif(is_array($id)) {
            $query = [];
            foreach($id as $k=>$v) {
                if(preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',$v)) {
                    $v = "guid'{$v}'";
                }
                $query[] = $k.'='.$v;
            }
            $query = '('.implode(',',$query).')';
        }

        if($id && !is_array($id)) {
            $query .= "(guid'{$id}')";
        }
        if($filter) {
            $this->filter($filter);
        }
        if($query)
            $this->requested[] = $query;

        return $this->request('DELETE',$options);
    }

    public function __get($name) {
        $this->requested = [];

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

        $this->http_code = null;
        $this->http_message = null;
        $this->odata_code = null;
        $this->odata_message = null;

        $this->request_ok = false;
        $options = array_replace_recursive($this->request_options,$options!==null?$options:[]);
        $this->request_options = [];

        $request_str = implode('',$this->requested);
        if($this->is_called) {
            array_splice($this->requested,$this->id ? -3 : -2);
            $this->is_called = false;
        } elseif ($this->id) {
            array_splice($this->requested,-1);
        }

        try {
            $resp = $this->client->request($method,$request_str,$options);
            $this->request_ok = true;
        } catch(TransferException $e) {
            if($e instanceof TransferException) {
                if($e->hasResponse() && ($resp = $e->getResponse()) ) {
                    $this->http_code = $resp->getStatusCode();
                    $this->http_message = $resp->getReasonPhrase();
                } else {
                    $this->http_code = $e->getCode();
                    $this->http_message = $e->getMessage();
                }
            } else {
                $this->http_code = $e->getCode();
                $this->http_message = $e->getMessage();
                return null;
            }
        }
        $this->parseMetadata($resp);
        $this->response = new Response($this,$resp);

        return $this;
    }

    public function getHttpErrorMessage() {
        return $this->http_message;
    }

    public function getHttpErrorCode() {
        return $this->http_code;
    }

    public function getErrorMessage() {
        return $this->odata_message;
    }

    public function getErrorCode() {
        return $this->odata_code;
    }

    public function isOk() {
        return $this->request_ok;
    }

    public function getLastId() {
        return !empty($this->_metadata['last_id'])
            ? $this->_metadata['last_id']
            : isset($this->response->values()[0]['Ref_Key']) ? $this->response->values()[0]['Ref_Key'] : null;
    }

    protected function parseMetadata(ResponseInterface $resp) {
        if($body = $resp->getBody()) {
            $this->_metadata['body'] = $body->__toString();
            if($data = json_decode($this->_metadata['body'],true)) {
                if(isset($data['odata.error'])) {
                    if(isset($data['odata.error']['code']))
                        $this->odata_code = $data['odata.error']['code'];
                    if(isset($data['odata.error']['message']['value']))
                        $this->odata_message = $data['odata.error']['message']['value'];
                }
            }
        }
        if($resp->hasHeader('Location')) {
            preg_match("/guid'(.*?)'/",implode(' ',$resp->getHeader('Location')),$matches);
            if($matches) $this->_metadata['last_id'] = $matches[1];
        }
    }

    public function getMetadata($name=null) {
        if($name === null) return $this->_metadata;
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
        $this->is_called = true;
        if($this->id) {
            $this->requested[] = "(guid'{$this->id}')";
        }
        $this->requested[] = "/";
        $this->requested[] = ucfirst($name);
        return $this->request('POST',[]);
    }

    public function offsetSet($offset, $value) {
		throw new Exception('You\'re trying to write protected object');
    }

    public function offsetExists($offset) {
        return $this->response && isset($this->response->toArray()[$offset]);
    }

    public function offsetUnset($offset) {
    }

    public function offsetGet($offset) {
        return $this->response && isset($this->response->toArray()[$offset]) ? $this->response->toArray()[$offset] : null;
    }

    public function getResponse() {
        return $this->response;
    }

    public function toArray() {
        return $this->response ? $this->response->toArray() : [];
    }

    public function values() {
        return $this->response ? $this->response->values() : [];
    }

    public function first() {
        return $this->response ? $this->response->first() : [];
    }
}
