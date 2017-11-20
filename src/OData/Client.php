<?php

namespace Kily\Tools1C\OData;

use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Client as Guzzle;

class Client
{
    protected $requested = null;
    protected $client = null;
    protected $profiler = null;
    protected $_metadata = null;

    /**
     * @deprecated
     */
    protected $error_code = null;

    /**
     * @deprecated
     */
    protected $error_message = null;

    /**
     * @deprecated
     */
    protected $request_ok = false;

    /**
     * @deprecated
     * @var bool
     */
    public $isCompatibilityMode = false;

    public function __construct($url, $options, $profiler = null) {
        $this->client = new Guzzle(array_replace_recursive([
            'base_uri'=>$url,
            'timeout'=>300,
            'headers'=>[
                'Accept'=>'application/json',
            ],
        ],$options));
        $this->setProfiler($profiler);
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

    public function getLastId() {
        return !empty($this->_metadata['last_id']) ? $this->_metadata['last_id'] : null;
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

    public function getMetadata($name = null) {
        if ($name !== null) {
            return isset($this->_metadata[$name]) ? $this->_metadata[$name] : null;
        }

        $resp = $this->client->request('get','$metadata',[]);
        $xml = simplexml_load_string($resp->getBody(), 'SimpleXMLElement', 0, 'edmx', true);
        $children = $xml->children('edmx', true)->children()->children();
        $json = json_encode($children);
        $array = json_decode($json,TRUE);

        return $array;
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

    protected function getProfiler() {
        return $this->profiler;
    }

    /**
     * @param Profiler $profiler
     *
     * @return $this
     */
    public function setProfiler(/**?Profiler*/ $profiler) {
        if (!($profiler instanceof Profiler) && $profiler !== null) {
            throw new \InvalidArgumentException('Profiler must be instance of ' . Profiler::class . ' or null');
        }

        $this->profiler = $profiler;

        return $this;
    }

    /**
     * @param $options
     * @param $method
     *
     * @return Request
     */
    protected function createRequest($options, $method) {
        $requestStr = implode('',$this->requested);
        $this->requested = [];
        $request = new Request($this->client->getConfig('base_uri'), $requestStr, $options, $method);
        return $request;
    }

    public function request($method,$options=[]) {
        if ($this->isCompatibilityMode) {
            $this->error_code = null;
            $this->error_message = null;
            $this->request_ok = true;
        }

        $request = $this->createRequest($options, $method);
        if ($profiler = $this->getProfiler()) {
            $profiler->setRequest($request);
            $profiler->begin();
        }

        try {
            $resp = $this->client->request($request->getMethod(), $request->getUrl(), $request->getOptions());
        } catch (TransferException $e) {
            if ($this->isCompatibilityMode) {
                $this->request_ok = false;
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
            } else {
                if($e instanceof ClientException || $e instanceof ServerException) {
                    $errorCode = $e->getCode();
                    $errorMessage = $e->getResponse()->getBody();
                } else {
                    $errorCode = $e->getCode();
                    $errorMessage = $e->getTraceAsString();
                }

                throw new RequestException($request, $errorMessage, $errorCode);
            }
        }

        if ($profiler) {
            $profiler->end();
        }

        $this->parseMetadata($resp);

        return $this->toArray($resp);
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

    /**
     * @deprecated
     */
    public function getErrorCode() {
        return $this->error_code;
    }

    /**
     * @deprecated
     */
    public function getErrorMessage() {
        return $this->error_message;
    }

    /**
     * @deprecated
     */
    public function isOk() {
        return $this->request_ok;
    }

    public function reset() {
        $this->requested = [];
        return $this;
    }
}
