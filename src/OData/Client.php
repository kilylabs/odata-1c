<?php

namespace Kily\Tools1C\OData;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Client as Guzzle;

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

        try {
            $resp = $this->client->request($method,$request_str,$options);
            $this->response = $resp;
            $this->request_ok = true;
        } catch(TransferException $e) {
            if($e instanceof ClientException) {
                if($resp = $e->getResponse()) {
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
